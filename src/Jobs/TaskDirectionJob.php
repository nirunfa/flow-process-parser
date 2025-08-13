<?php

namespace Nirunfa\FlowProcessParser\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Nirunfa\FlowProcessParser\Models\NProcessInstance;
use Nirunfa\FlowProcessParser\Models\NProcessNode;
use Nirunfa\FlowProcessParser\Models\NProcessNodeAttr;
use Nirunfa\FlowProcessParser\Models\NProcessTask;

/**
 * 任务走向 job
 */
class TaskDirectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $taskId;

    public function __construct($taskId){
        $this->taskId = $taskId;
    }

    public function handle(){
        $task = NProcessTask::query()->find($this->taskId);
        //查找下一个任务节点并生成新任务
        $task->loadMissing(['instance.variables','node.nextNodes.approvers']);
        $nextNodes = $task->node->nextNodes ?? null;
        if(empty($nextNodes) || $nextNodes->isEmpty()){
            return ('流程已完成!');
        }

        //条件分支类型
        /**
         * 条件分支判断需要根据 variables变量表取值匹配判断,如果咩有变量则报错卡住
         * 1.优先任务变量判断
         * 2.在是流程实例变量判断
         * 3.如果都没有则报错
         */

        $taskVariables = $task->node->variables ?? null;//任务变量
        $instanceVariables = $task->instance->variables->filter(function($iv){
            return !($iv->task_id > 0);
        });//流程实例变量

        $nextNode = $nextNodes->first();
        //节点
        $nextNode->loadMissing(['nextNodes','attr']);
        //取 节点attr
        $nextNodeAtt = $nextNode->attr;
        if($nextNode->type === NProcessNode::TYPE_CONDITION || $nextNode->type === NProcessNode::TYPE_BRANCH){
            $towards = $nextNodeAtt->towards;
            $conditionType = $nextNodeAtt->condition_type;

            $conditionNodes = $nextNode->nextNodes ?? [];
            foreach ($conditionNodes as $conditionNode){
                $conditionNode->loadMissing('conditions');
                $conditionsGroup = $conditionNode->conditions->groupBy('group_id');
                /*[
                    'group_id'=>[
                        [
                            'condition_type'=>1,
                            'condition_value'=>'变量1==1'
                        ]
                    ],
                    'group_id2'=>[
                        [
                            'column_value'=>'变量1',
                            'opt_type'=>'lt',
                            'condition_value'=>'1'
                        ]
                    ]
                ]*/
                $groupSumFlag = false;//所有组条件或
                foreach ($conditionsGroup as $conditionGroup){
                    $groupFlag = true;
                    foreach ($conditionGroup as $condition)
                    {
                        $conditionVal = $condition['condition_value'];
                        $optType = $condition['opt_type'];
                        $conditionField = $condition['column_value'];
                        $conditionFlag = true;
                        if($conditionType === NProcessNodeAttr::FORMULA){
                            //公式
                            $conditionVals = [];
                            if(mb_strpos($conditionVal,">=") !== false){
                                $conditionVals = explode(">=",$conditionVal);
                                $optType='gte';
                            }else if(mb_strpos($conditionVal,"<=") !== false){
                                $conditionVals = explode("<=",$conditionVal);
                                $optType='lte';
                            }else if(mb_strpos($conditionVal,">") !== false){
                                $conditionVals = explode("<",$conditionVal);
                                $optType='gt';
                            }else if(mb_strpos($conditionVal,"<") !== false){
                                $conditionVals = explode(">",$conditionVal);
                                $optType='lt';
                            }else if(mb_strpos($conditionVal,"!=") !== false || mb_strpos($conditionVal,"<>") !== false){
                                //等于
                                $conditionVals = explode("!=",Str::replace("<>","!=",$conditionVal));
                                $optType='neq';
                            }else{
                                //等于
                                $conditionVals = explode("==",$conditionVal);
                                $optType='eq';
                            }
                            $conditionField = $conditionVals[0] ?? '';
                            $conditionVal = $conditionVals[1] ?? '';
                            //先判断任务变量中是否存在，在从流程实例变量中取
                            if( ( $taskVariables && $varFind = $taskVariables->first(function($tv) use ($conditionField){
                                    return $tv->name === $conditionField;
                                }) )
                                || ( $instanceVariables &&$varFind = $instanceVariables->first(function($tv) use ($conditionField){
                                    return $tv->name === $conditionField;
                                }) )
                            ){
                                switch ($optType){
                                    case 'gte':
                                        $conditionFlag = $varFind->value >= $conditionVal;
                                        break;
                                    case 'lte':
                                        $conditionFlag = $varFind->value <= $conditionVal;
                                        break;
                                    case 'gt':
                                        $conditionFlag = $varFind->value > $conditionVal;
                                        break;
                                    case 'lt':
                                        $conditionFlag = $varFind->value < $conditionVal;
                                        break;
                                    case 'neq':
                                        $conditionFlag = $varFind->value != $conditionVal;
                                        break;
                                    case 'eq':
                                        $conditionFlag = $varFind->value == $conditionVal;
                                        break;
                                }
                            }else{
                                $conditionFlag = false;
                            }
                        }

                        $groupFlag = $groupFlag && $conditionFlag;
                    }

                    $groupSumFlag = $groupSumFlag || $groupFlag;
                }

                if($nextNode->type === NProcessNode::TYPE_CONDITION && !empty($towards)){
                    //走向
                    $nextNode = $conditionNode;
//                    if( ($towards === NProcessNodeAttr::TRUE_DOWN && $groupSumFlag)
//                        || ($towards === NProcessNodeAttr::FALSE_DOWN && !$groupSumFlag)
//                        || $towards === NProcessNodeAttr::DIRECT_DOWN
//                    ){
//                        $nextNode = $conditionNode;
//                    }else
                        if( ($towards === NProcessNodeAttr::TRUE_FINISH && $groupSumFlag)
                        || ($towards === NProcessNodeAttr::FALSE_FINISH && !$groupSumFlag)
                        || $towards === NProcessNodeAttr::DIRECT_FINISH
                    ){
                        $nextNode = null;
                    }
                    break;
                }

                if($groupSumFlag){
                    //匹配到了就退出 foreach
                    $nextNode = $conditionNode;
                    break;
                }
            }
        }

        if($nextNode){
            //创建任务
            $taskData = [
                'name' => $nextNode->name,
                'node_id' => $nextNode->id,
                'status' =>NProcessTask::STATUS_UNASSIGNED
            ];
            $newTask = $task->instance->tasks()->create($taskData);
            $approvers = $nextNode->approvers ?? [];
            foreach ($approvers as $approver){
                $newTask->assignees()->create([
                    'node_approver_id'=>$approver->id,
                ]);
            }
        }else{
            //没有表示卡主或者流程完成
            $task->instance->setComplete();
            $task->instance->save();
        }

    }
}