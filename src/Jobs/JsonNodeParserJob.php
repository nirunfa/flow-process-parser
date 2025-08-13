<?php

namespace Nirunfa\FlowProcessParser\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Nirunfa\FlowProcessParser\Models\NProcessDefinitionVersion;
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

    private $definitionId = 0;
    private $ver = 0;

    public function __construct($definitionId,$ver){
        $this->definitionId = $definitionId;
        $this->ver = $ver;
    }

    public function handle(){
        $versionRecord = NProcessDefinitionVersion::query()->where('definition_id',$this->definitionId)
            ->where('ver',$this->ver)->first();
        $jsonContent = $versionRecord->json_content;
        $orgNodeData = json_decode($jsonContent,true);
        if($orgNodeData){
            DB::transaction(function () use ($orgNodeData){
                $this->combineChildNode($orgNodeData,null);
            });
        }else{
            throw new \Exception("JsonNodeParserJob jsonContent is empty or error format string");
        }
    }

    /**
     * @param $orgNodeData
     * @return void
     *
     * type: 1审批人节点 2抄送结点 3条件节点 4分支节点 5事件节点 6处理人节点
     * 7意见分支节点  8意见分支节点中各分支
     * 9并行节点 10并行节点中各分支 11并行节点后的聚合节点
     * 12通知节点
     */
    private function combineChildNode($orgNodeData,$preProcessNode){
        $nodeType = $orgNodeData['type'];
        $attr = $orgNodeData['attr'] ?? [];//额外属性
        $approverList = $orgNodeData['approverGroups'] ?? [];//审批人｜处理人集合
        $conditionList = $orgNodeData['conditionGroup'] ?? [];//条件
        $configures = $orgNodeData['configure'] ?? [];

        //保存的数据
        $nodeAttr = [];
        $conditions = [];//条件或分支节点的
        $approverDatas = [];

        switch ($nodeType){
            case NProcessNode::TYPE_APPROVER:
            case NProcessNode::TYPE_ASSIGNEE:
                //审批人或者处理人节点
                $nodeAttr=[
                    'approve_type'=>$attr['approvalMethod'],
                    'approve_mode'=>$attr['approvalMode'],
                    'approver_same_initiator'=>$attr['sameMode'],
                    'approver_same_prev'=>$attr['noHander'],
                ];
                foreach ($approverList as $approverItem) {
                    $approverDatas[]=new NProcessNodeApprover([
                        'id'=>$approverItem['id'],
                        'approver_type'=>$approverItem['approverType'],
                        'approve_direct'=>$approverItem['levelMode'],
                        'approver_ids'=>$approverItem['approverIds'][0] ?? $approverItem['approverIds'],
                        'approver_names'=>$approverItem['approverNames'][0] ?? $approverItem['approverNames'],
                        'order_sort'=>$approverItem['sort']
                    ]);
                }
                break;
            case NProcessNode::TYPE_CONDITION:
            case NProcessNode::TYPE_BRANCH:
            case NProcessNode::TYPE_OPINION_BRANCH:
            case NProcessNode::TYPE_OPINION_BRANCH_NODE:
                //条件或分支或意见分支节点
                $nodeAttr=[
                    'approve_type'=>$attr['branchType'] ?? 1,
                ];
                foreach ($conditionList as $conditionListItem) {
                    $conditionItems = $conditionListItem['conditions'];
                    foreach ($conditionItems as $conditionItem) {
                        $conditions[]=new NProcessNodeCondition([
                            'id'=>$conditionItem['id'],
                            'group_id'=>$conditionListItem['id'],
                            'column_id'=>$conditionItem['columnId'],
                            'column_name'=>$conditionItem['columnName'],
//                            'column_type'=>,
                            'column_value'=>$conditionItem['columnValue'],
                            'opt_type'=>$conditionItem['optType'],
                            'opt_type_name'=>$conditionItem['optTypeName'],
                            'value_type'=>$conditionItem['valueType'],
                            'condition_value'=>$conditionItem['conditionValue'][0] ?? $conditionItem['conditionValue'],
                            'condition_value_name'=>$conditionItem['conditionValueName'][0] ?? $conditionItem['conditionValueName'],
                        ]);
                    }
                }
                break;
        }

        //开始解析
        $initNode = [
            'name'=>$orgNodeData['name'],
            'n_uuid'=>$orgNodeData['id'],
            'type'=>$nodeType,
            'ver'=>$this->ver,
            'definition_id'=>$this->definitionId,
            'prev_node_id'=>$preProcessNode->id ?? null,
            'prev_node_uuid'=>$orgNodeData['pid'] ?? null,
        ];

        if(in_array($nodeType,[NProcessNode::TYPE_APPROVER, NProcessNode::TYPE_ASSIGNEE])){
            $initFormDesignData = $orgNodeData['formDesignData'];
            $isNewForm = $initFormDesignData['isNew'] ?? true;
            $chooseFormId = $initFormDesignData['chooseFormId'];
            $formFields = $initFormDesignData['fields'];
            $curDateTime= date('YmdHis');
            $formName = $initFormDesignData['name'] ?? "{$orgNodeData['name']}-{$curDateTime}-{$this->ver}";

            if($isNewForm){//新表单
                $formInfo =  NProcessForm::find($chooseFormId);
                if(!empty($formInfo)){
                    $fieldArray = json_decode($formInfo['fields'],true);
                    array_push($fieldArray,...json_decode($formFields,true));
                    $newFormInfo = $formInfo->replicate()->fill([
                        'name'=>$formName,
                        'fields'=>json_encode($fieldArray),
                        'ver'=>$this->ver,
                    ]);
                    $newFormInfo->save();
                    $newFormInfo->refresh();
                    $initNode['form_id']=$newFormInfo->id;
                }
            }else{
                $initNode['form_id'] = $chooseFormId;
            }
        }

        //创建 node
        $processNode = NProcessNode::query()->create($initNode);
        //保存相关配置等信息
        //attr属性
        if(empty($nodeAttr)){
            $processNode->attr()->save(new NProcessNodeAttr(
                $nodeAttr
            ));
        }
        //条件
        if(count($conditions) > 0){
            $processNode->conditions()->saveMany($conditions);
        }
        //审批人｜处理人
        if(count($approverDatas) > 0){
            $processNode->approvers()->saveMany($approverDatas);
        }

        //非条件子节点
        $childNodes = $orgNodeData['childNode'];
        if(!empty($childNodes)){
            $this->combineChildNode($childNodes,$processNode);
        }

        //条件结点
        $conditionNodes = $orgNodeData['conditionNode'] ?? [];
        if(!empty($conditionNodes)){
            foreach ($conditionNodes as $conditionNode) {
                $this->combineChildNode($conditionNode,$processNode);
            }
        }
    }

}