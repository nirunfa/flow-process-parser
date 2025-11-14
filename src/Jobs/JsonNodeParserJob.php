<?php

namespace Nirunfa\FlowProcessParser\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Nirunfa\FlowProcessParser\Contracts\JsonNodeParserJobInterface;
use Nirunfa\FlowProcessParser\Events\NodeParsing\NodeParsingStarted;
use Nirunfa\FlowProcessParser\Events\NodeParsing\NodeParsingCompleted;
use Nirunfa\FlowProcessParser\Events\NodeParsing\NodeCreating;
use Nirunfa\FlowProcessParser\Events\NodeParsing\NodeCreated;
use Nirunfa\FlowProcessParser\Events\NodeParsing\RelationDataSaving;
use Nirunfa\FlowProcessParser\Models\NProcessDesignVersion;
use Nirunfa\FlowProcessParser\Models\NProcessForm;
use Nirunfa\FlowProcessParser\Models\NProcessNode;
use Nirunfa\FlowProcessParser\Models\NProcessNodeApprover;
use Nirunfa\FlowProcessParser\Models\NProcessNodeAttr;
use Nirunfa\FlowProcessParser\Models\NProcessNodeCondition;
use Nirunfa\FlowProcessParser\Repositories\ProcessDesignRepository;

/**
 * 流程设计 Json 节点解析 job
 * 
 * 扩展说明：
 * 1. 继承此类并重写 protected 方法来自定义逻辑
 * 2. 监听事件来自定义行为
 * 3. 通过配置文件绑定自定义实现
 */
class JsonNodeParserJob implements ShouldQueue, JsonNodeParserJobInterface
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $designId = 0;
    private $ver = 0;
    
    /**
     * 批量更新 next_node_id 的队列
     * [node_id => [next_node_id, next_node_uuid]]
     */
    private $nextNodeUpdates = [];
    
    /**
     * 批量保存的关联数据队列
     */
    private $relationDataQueue = [];
    
    /**
     * 表单缓存，避免重复查询
     */
    private $formCache = [];


    public function __construct($designId, $ver)
    {
        $this->designId = $designId;
        $this->ver = $ver;
    }

    public function handle()
    {
        $versionRecord = NProcessDesignVersion::query()
            ->where("design_id", $this->designId)
            ->where("ver", $this->ver)
            ->first();
        if(!$versionRecord){
            throw new \RuntimeException("NProcessDesignVersion not found: design_id={$this->designId}, ver={$this->ver}");
        }
        $jsonContent = $versionRecord->json_content;
        $orgNodeData = json_decode($jsonContent, true);
        
        // 触发解析开始事件
        event(new NodeParsingStarted($this->designId, $this->ver, $orgNodeData));
        
        if ($orgNodeData) {
            DB::transaction(function () use ($orgNodeData,$versionRecord) {
                //开始之前先清理相关版本的模型节点等数据
                //清理流程设计版本节点数据
                $nodes = NProcessNode::query()
                    ->where("design_id", $this->designId)
                    ->where("ver", $this->ver)
                    ->get();
                foreach ($nodes as $node) {
                    $node->approvers()->delete();
                    $node->conditions()->delete();
                    $node->attr()->delete();
                    $node->delete();
                }


                $this->combineChildNode($orgNodeData, null, '');
                
                // 批量保存关联数据
                $this->flushRelationData();
                
                // 批量更新 next_node_id
                $this->flushNextNodeUpdates();

                //查询分支 node
                $branchNodes = NProcessNode::query()
                    ->with(['branchNextNodes'=>function($query){
                        $query->where('is_branch_child', NProcessNode::ENABLE_BRANCH_CHILD);
                    }])
                    ->where('type', NProcessNode::TYPE_BRANCH)
                    ->where("design_id", $this->designId)
                    ->where("ver", $this->ver)
                    ->get()
                    ->keyBy('id');
                    
                //更新 next_node_id 和 next_node_uuid 的映射关系
                $nullNextNodeNodes = NProcessNode::query()
                    ->where("design_id", $this->designId)
                    ->where("ver", $this->ver)
                    ->where(function($query){
                        $query->where('next_node_id', 0)
                              ->orWhereNull('next_node_id');
                    })
                    ->get();
                    
                // 批量更新 null 节点
                $this->batchUpdateNullNodes($nullNextNodeNodes, $branchNodes);
        
                //更新版本状态 
                //1. 先查询当前版本$ver 是否启用
                //2. 如果禁用，则关闭其他版本，设置$ver版本为启用   
                $designRecords = ProcessDesignRepository::find($this->designId);    
                $curVersionRecord = $designRecords->versions()->where('ver',$this->ver)->first();
                if($curVersionRecord && intval($curVersionRecord->status) === NProcessDesignVersion::STATUS_DISABLE){
                    $designRecords->each(function($record){
                        $record->update(['status'=>NProcessDesignVersion::STATUS_DISABLE]);
                    });
                    $curVersionRecord->update(['status'=>NProcessDesignVersion::STATUS_ENABLE]);
                }
            });
            
            // 触发解析完成事件
            event(new NodeParsingCompleted($this->designId, $this->ver, $orgNodeData));
        } else {
            throw new \Exception(
                "JsonNodeParserJob jsonContent is empty or error format string",
            );
        }
    }

    /**
     * @param $orgNodeData
     * @param $preProcessNode
     * @param $path
     * @param $isBranchChild
     * @return mixed
     *
     * type: 1审批人节点 2抄送结点 3条件节点 4分支节点 5事件节点 6处理人节点
     * 7意见分支节点  8意见分支节点中各分支
     * 9并行节点 10并行节点中各分支 11并行节点后的聚合节点
     * 12通知节点
     * 
     * 可重写方法：子类可以重写此方法来自定义节点解析逻辑
     */
    protected function combineChildNode($orgNodeData, $preProcessNode,$path,$isBranchChild=0)
    {
        $nodeType = $orgNodeData["type"];
        $attr = $orgNodeData["attr"] ?? []; //额外属性
        $approverList = $orgNodeData["approverGroups"] ?? []; //审批人｜处理人集合
        $conditionList = $orgNodeData["conditionGroup"] ?? []; //条件
        $configures = $orgNodeData["configure"] ?? [];

        //保存的数据
        $nodeAttr = [];
        $conditions = []; //条件或分支节点的
        $approverDatas = [];

        switch ($nodeType) {
            case NProcessNode::TYPE_APPROVER:
            case NProcessNode::TYPE_ASSIGNEE:
                //审批人或者处理人节点
                $nodeAttr = [
                    "approve_type" => $attr["approvalMethod"] ?? null,
                    "approve_mode" => $attr["approvalMode"] ?? null,
                    "approver_same_initiator" => $attr["sameMode"] ?? null,
                    "approver_empty" => $attr["noHander"] ?? null,
                ];
                foreach ($approverList as $approverItem) {
                    $approverDatas[] = new NProcessNodeApprover([
                        "uuid" => $approverItem["id"],
                        "approver_type" => $approverItem["approverType"],
                        "approve_direct" => $approverItem["levelMode"],
                        'level_mode' => $approverItem["levelMode"] ?? null,
                        'loop_count' =>isset($approverItem["loopCount"]) && is_array($approverItem["loopCount"])?($approverItem["loopCount"][0] ??
                        0):($approverItem["loopCount"]??0),
                        "approver_ids" =>isset($approverItem["approverIds"]) && is_array($approverItem["approverIds"])?($approverItem["approverIds"][0] ??
                        ""):$approverItem["approverIds"],
                        "approver_names" =>isset($approverItem["approverNames"]) && is_array($approverItem["approverNames"])?($approverItem["approverNames"][0] ??
                        ""):$approverItem["approverNames"],
                        "order_sort" => $approverItem["sort"],
                    ]);
                }
                break;
            case NProcessNode::TYPE_CONDITION:
            case NProcessNode::TYPE_BRANCH:
            case NProcessNode::TYPE_OPINION_BRANCH:
            case NProcessNode::TYPE_OPINION_BRANCH_NODE:
                //条件或分支或意见分支节点
                $nodeAttr = [
                    "approve_type" => $attr["branchType"] ?? 1,
                    "towards" => $attr["towards"] ?? null,
                    "condition_type" => $attr["conditionType"] ?? null,
                ];
                foreach ($conditionList as $conditionListItem) {
                    $conditionItems = $conditionListItem["conditions"] ?? [];
                    foreach ($conditionItems as $conditionItem) {
                        $conditions[] = new NProcessNodeCondition([
                            "uuid" => $conditionItem["id"],
                            "group_id" => $conditionListItem["id"],
                            "column_id" => $conditionItem["columnId"] ?? 0,
                            "column_name" => $conditionItem["columnName"] ?? null,
                            //                            'column_type'=>,
                            "column_value" => $conditionItem["columnValue"] ?? null,
                            "opt_type" => $conditionItem["optType"] ?? null,
                            "opt_type_name" => $conditionItem["optTypeName"] ?? null,
                            "value_type" => $conditionItem["valueType"] ?? null,
                            "condition_value" =>
                                is_array($conditionItem["conditionValue"]) ? ($conditionItem["conditionValue"][0] ?? "") : $conditionItem["conditionValue"],
                            "condition_value_name" =>
                                is_array($conditionItem["conditionValueName"]) ? ($conditionItem["conditionValueName"][0] ?? "") : $conditionItem["conditionValueName"],
                        ]);
                    }
                }
                break;
        }

        //开始解析
        $initNode = [
            "name" => $orgNodeData["name"],
            "n_uuid" => $orgNodeData["id"],
            "type" => $nodeType,
            "ver" => $this->ver,
            "design_id" => $this->designId,
            "prev_node_id" => $preProcessNode->id ?? null,
            "prev_node_uuid" => $orgNodeData["pid"] ?? null,
            'is_branch_child' => $isBranchChild ?? false,
            'n_path' => $path,
        ];

        if (
            in_array($nodeType, [
                NProcessNode::TYPE_INITIATOR,
                NProcessNode::TYPE_APPROVER,
                NProcessNode::TYPE_ASSIGNEE,
            ])
        ) {
            $initFormDesignData = $orgNodeData["formDesignData"] ?? [];
            $isNewForm = $initFormDesignData["isNew"] ?? true;
            if(is_string($initFormDesignData["isNew"]) && $initFormDesignData["isNew"] == "false"){
                $isNewForm = false;
            }
            $chooseFormId = $initFormDesignData["chooseFormId"] ?? 0;
            $formFields = $initFormDesignData["fields"] ?? "{}";
            $jsonContent = $initFormDesignData["jsonContent"] ?? "{}";
            $curDateTime = date("YmdHis");
            $formName =
                $initFormDesignData["name"] ??
                "{$orgNodeData["name"]}-{$curDateTime}-{$this->ver}";

            if ($isNewForm) {
                //新表单 - 使用缓存避免重复查询
                if (!isset($this->formCache[$chooseFormId])) {
                    $formInfo = NProcessForm::find($chooseFormId);
                    $this->formCache[$chooseFormId] = $formInfo;
                } else {
                    $formInfo = $this->formCache[$chooseFormId];
                }
                
                if (!empty($formInfo)) {
                    $fieldArray = json_decode($formInfo["fields"], true) ?: [];
                    $newFields = json_decode($formFields, true) ?: [];
                    $fieldArray = array_merge($fieldArray, $newFields);
                    
                    $newFormInfo = $formInfo->replicate()->fill([
                        "name" => $formName,
                        "fields" => json_encode($fieldArray),
                        "ver" => $this->ver,
                        "json_content" => $jsonContent,
                    ]);
                    $newFormInfo->save();
                    $newFormInfo->refresh();
                    $initNode["form_id"] = $newFormInfo->id;
                }
            } else {
                $initNode["form_id"] = $chooseFormId;
            }
        }

        // 触发节点创建前事件，允许外部修改 $initNode
        event(new NodeCreating($this->designId, $this->ver, $orgNodeData, $initNode));
        
        //创建 node
        $processNode = NProcessNode::query()->create($initNode);
        
        // 触发节点创建后事件
        event(new NodeCreated($this->designId, $this->ver, $processNode));
        
        // 延迟更新前一个节点的 next_node_id
        if(isset($preProcessNode)){
            $this->nextNodeUpdates[$preProcessNode->id] = [
                'next_node_id' => $processNode->id,
                'next_node_uuid' => $processNode->n_uuid,
            ];
        }
        
        // 优化路径拼接：直接拼接，避免重复替换
        $path = empty($path) ? (string)$processNode->id : $path . '>' . $processNode->id;

        // 延迟保存关联数据，批量处理
        if (!empty($nodeAttr)) {
            $this->relationDataQueue[] = [
                'type' => 'attr',
                'node_id' => $processNode->id,
                'data' => $nodeAttr,
            ];
        }
        if (!empty($conditions)) {
            $this->relationDataQueue[] = [
                'type' => 'conditions',
                'node_id' => $processNode->id,
                'data' => $conditions,
            ];
        }
        if (!empty($approverDatas)) {
            $this->relationDataQueue[] = [
                'type' => 'approvers',
                'node_id' => $processNode->id,
                'data' => $approverDatas,
            ];
        }

        //非条件子节点
        $childNode = $orgNodeData["childNode"] ?? [];
        if (!empty($childNode)){
            $this->combineChildNode($childNode, $processNode, $path, $nodeType === NProcessNode::TYPE_BRANCH?1:0);
        }

        //条件结点
        $conditionNodes = $orgNodeData["conditionNode"] ?? ($orgNodeData["conditionNodes"] ?? []);
        if (!empty($conditionNodes)) {
            foreach ($conditionNodes as $conditionNode) {
                // 条件分支路径：添加分支标记
                $this->combineChildNode($conditionNode, $processNode, $path . '-' . $processNode->id);
            }
        }
    }
    
    /**
     * 批量保存关联数据
     * 可重写方法：子类可以重写此方法来自定义关联数据保存逻辑
     */
    protected function flushRelationData()
    {
        if (empty($this->relationDataQueue)) {
            return;
        }
        
        // 触发关联数据保存前事件，允许外部修改数据
        event(new RelationDataSaving($this->designId, $this->ver, $this->relationDataQueue));
        
        // 按类型分组，批量插入
        $attrData = [];
        $conditionData = [];
        $approverData = [];
        
        // 定义允许的 attr 字段（按表结构，排除 timestamps）
        $allowedAttrFields = [
            'towards',
            'condition_type',
            'approve_type',
            'approve_mode',
            'approver_same_initiator',
            'approver_same_prev',
            'approver_empty',
        ];
        
        foreach ($this->relationDataQueue as $item) {
            $nodeId = $item['node_id'];
            $data = $item['data'];
            
            switch ($item['type']) {
                case 'attr':
                    // 只保留允许的字段
                    $filteredData = ['node_id' => $nodeId];
                    foreach ($allowedAttrFields as $field) {
                        if (isset($data[$field])) {
                            $filteredData[$field] = $data[$field];
                        }
                    }
                    $attrData[] = $filteredData;
                    break;
                case 'conditions':
                    foreach ($data as $condition) {
                        // 获取模型的原始属性（排除 timestamps 和 id）
                        $attributes = $condition->getAttributes();
                        unset($attributes['id'], $attributes['created_at'], $attributes['updated_at'], $attributes['node_id']);
                        $conditionData[] = array_merge($attributes, ['node_id' => $nodeId]);
                    }
                    break;
                case 'approvers':
                    foreach ($data as $approver) {
                        // 获取模型的原始属性（排除 timestamps 和 id）
                        $attributes = $approver->getAttributes();
                        unset($attributes['id'], $attributes['created_at'], $attributes['updated_at'], $attributes['node_id']);
                        $approverData[] = array_merge($attributes, ['node_id' => $nodeId]);
                    }
                    break;
            }
        }
        
        // 批量插入
        if (!empty($attrData)) {
            // 使用固定的字段列表，确保所有行字段一致
            $fixedFields = [
                'node_id',
                'towards',
                'condition_type',
                'approve_type',
                'approve_mode',
                'approver_same_initiator',
                'approver_same_prev',
                'approver_empty',
            ];
            
            // 标准化所有行，确保字段顺序和数量一致
            $normalizedAttrData = [];
            foreach ($attrData as $row) {
                $normalizedRow = [];
                foreach ($fixedFields as $field) {
                    $normalizedRow[$field] = $row[$field] ?? null;
                }
                $normalizedAttrData[] = $normalizedRow;
            }
            
            DB::table((new NProcessNodeAttr())->getTable())->insert($normalizedAttrData);
        }
        if (!empty($conditionData)) {
            DB::table((new NProcessNodeCondition())->getTable())->insert($conditionData);
        }
        if (!empty($approverData)) {
            DB::table((new NProcessNodeApprover())->getTable())->insert($approverData);
        }
        
        // 清空队列
        $this->relationDataQueue = [];
    }
    
    /**
     * 批量更新 next_node_id
     * 可重写方法：子类可以重写此方法来自定义更新逻辑
     */
    protected function flushNextNodeUpdates()
    {
        if (empty($this->nextNodeUpdates)) {
            return;
        }
        
        // 使用 CASE WHEN 批量更新
        $caseNextNodeId = "CASE id ";
        $caseNextNodeUuid = "CASE id ";
        $updateIds = [];
        
        foreach ($this->nextNodeUpdates as $nodeId => $update) {
            $updateIds[] = $nodeId;
            $caseNextNodeId .= "WHEN {$nodeId} THEN {$update['next_node_id']} ";
            $caseNextNodeUuid .= "WHEN {$nodeId} THEN " . DB::getPdo()->quote($update['next_node_uuid']) . " ";
        }
        
        $caseNextNodeId .= "END";
        $caseNextNodeUuid .= "END";
        
        NProcessNode::query()
            ->whereIn('id', $updateIds)
            ->where("design_id", $this->designId)
            ->where("ver", $this->ver)
            ->update([
                'next_node_id' => DB::raw($caseNextNodeId),
                'next_node_uuid' => DB::raw($caseNextNodeUuid),
            ]);
        
        // 清空队列
        $this->nextNodeUpdates = [];
    }
    
    /**
     * 批量更新 null 节点
     * 可重写方法：子类可以重写此方法来自定义 null 节点更新逻辑
     */
    protected function batchUpdateNullNodes($nullNextNodeNodes, $branchNodes)
    {
        if ($nullNextNodeNodes->isEmpty()) {
            return;
        }
        
        $updates = [];
        
        foreach($nullNextNodeNodes as $nullNextNodeNode){
            $nPath = $nullNextNodeNode->n_path;
            if (empty($nPath)) {
                continue;
            }
            
            // 优化：使用更高效的字符串处理（使用 strpos 而不是 mb_strpos）
            $nPaths = [];
            $pathParts = explode('>', $nPath);
            foreach ($pathParts as $part) {
                if (strpos($part, '-') !== false) {
                    $nPaths[] = $part;
                }
            }
            
            if (empty($nPaths)) {
                continue;
            }
            
            $nPaths = array_reverse($nPaths);
            
            foreach($nPaths as $nPathItem){
                $branchId = (int)explode('-', $nPathItem)[0];
                $branchNodeFind = $branchNodes->get($branchId);
                
                if($branchNodeFind){
                    $branchChildNodes = $branchNodeFind->branchNextNodes;
                    if($branchChildNodes->isNotEmpty()){
                        $firstChild = $branchChildNodes->first();
                        $updates[] = [
                            'id' => $nullNextNodeNode->id,
                            'next_node_id' => $firstChild->id,
                            'next_node_uuid' => $firstChild->n_uuid,
                        ];
                        break;
                    }
                }
            }
        }
        
        // 批量更新
        if (!empty($updates)) {
            $caseNextNodeId = "CASE id ";
            $caseNextNodeUuid = "CASE id ";
            $updateIds = [];
            
            foreach ($updates as $update) {
                $updateIds[] = $update['id'];
                $caseNextNodeId .= "WHEN {$update['id']} THEN {$update['next_node_id']} ";
                $caseNextNodeUuid .= "WHEN {$update['id']} THEN " . DB::getPdo()->quote($update['next_node_uuid']) . " ";
            }
            
            $caseNextNodeId .= "END";
            $caseNextNodeUuid .= "END";
            
            NProcessNode::query()
                ->whereIn('id', $updateIds)
                ->where("design_id", $this->designId)
                ->where("ver", $this->ver)
                ->update([
                    'next_node_id' => DB::raw($caseNextNodeId),
                    'next_node_uuid' => DB::raw($caseNextNodeUuid),
                ]);
        }
    }

}
