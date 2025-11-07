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
use Nirunfa\FlowProcessParser\Models\NProcessDesignVersion;
use Nirunfa\FlowProcessParser\Models\NProcessForm;
use Nirunfa\FlowProcessParser\Models\NProcessNode;
use Nirunfa\FlowProcessParser\Models\NProcessNodeApprover;
use Nirunfa\FlowProcessParser\Models\NProcessNodeAttr;
use Nirunfa\FlowProcessParser\Models\NProcessNodeCondition;

/**
 * 流程设计 Json 节点解析 job
 */
class JsonNodeParserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $designId = 0;
    private $ver = 0;


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
        if ($orgNodeData) {
            DB::transaction(function () use ($orgNodeData) {
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

                //查询分支 node
                $branchNodes = NProcessNode::query()
                    ->with(['branchNextNodes'=>function($query){
                        $query->where('is_branch_child', NProcessNode::ENABLE_BRANCH_CHILD);
                    }])
                    ->where('type', NProcessNode::TYPE_BRANCH)
                    ->get();
                //更新 next_node_id 和 next_node_uuid 的映射关系
                $nullNextNodeNodes = NProcessNode::query()
                    ->where('next_node_id', 0)
                    ->orWhereNull('next_node_id')
                    ->get();
                foreach($nullNextNodeNodes as $nullNextNodeNode){
                    $nPath = $nullNextNodeNode->n_path;
                    $nPaths = Arr::where(explode('>', $nPath), function($value){
                        return mb_strpos($value, '-') !== false;
                    });
                    $nPaths = array_reverse($nPaths);
                    foreach($nPaths as $nPath){
                        $branchId = explode('-', $nPath)[0];
                        $branchNodeFind = $branchNodes->firstWhere('id', $branchId);
                        if($branchNodeFind){
                            $branchChildNodes = $branchNodeFind->branchNextNodes;
                            if($branchChildNodes->isNotEmpty()){
                                $nullNextNodeNode->update([
                                    'next_node_id' => $branchChildNodes->first()->id,
                                    'next_node_uuid' => $branchChildNodes->first()->n_uuid,
                                ]);
                                break;
                            }
                        }
                    }
                }
        
            });
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
     */
    private function combineChildNode($orgNodeData, $preProcessNode,$path,$isBranchChild=0)
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
            'n_path' => trim($path,'-'),
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
                //新表单
                $formInfo = NProcessForm::find($chooseFormId);
                if (!empty($formInfo)) {
                    $fieldArray = json_decode($formInfo["fields"], true);
                    array_push($fieldArray, ...json_decode($formFields, true));
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

        //创建 node
        $processNode = NProcessNode::query()->create($initNode);
        if(isset($preProcessNode)){
            $preProcessNode->update([
                "next_node_id" => $processNode->id,
                "next_node_uuid" => $processNode->n_uuid,
            ]);
        }
        if(!$path || strlen($path) === 0){
            $path = $processNode->id;
        }else{
            $path = $path .'>'. $processNode->id;
        }
        
        $path = str_replace('->', '-', $path);

        //保存相关配置等信息
        //attr属性
        if (!empty($nodeAttr)) {
            $processNode->attr()->save(new NProcessNodeAttr($nodeAttr));
        }
        //条件
        if (count($conditions) > 0) {
            $processNode->conditions()->saveMany($conditions);
        }
        //审批人｜处理人
        if (count($approverDatas) > 0) {
            // dump(Arr::flatten($approverDatas));
            $processNode->approvers()->saveMany($approverDatas);
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
                $this->combineChildNode($conditionNode, $processNode, $path.'-');
            }
        }
    }

}
