<?php

namespace Nirunfa\FlowProcessParser\Jobs;

use Illuminate\Collection\Collection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Nirunfa\FlowProcessParser\Models\NProcessNode;
use Nirunfa\FlowProcessParser\Models\NProcessNodeAttr;
use Nirunfa\FlowProcessParser\Models\NProcessNodeCondition;
use Nirunfa\FlowProcessParser\Models\NProcessTask;
use Nirunfa\FlowProcessParser\Traits\CommonTrait;

/**
 * 任务走向 job
 */
class TaskDirectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels,CommonTrait;

    private $taskId;

    public function __construct($taskId){
        $this->taskId = $taskId;
    }

    public function handle(){
        $task = NProcessTask::query()->find($this->taskId);
        //查找下一个任务节点并生成新任务
        $task->loadMissing(['instance.variables','variables','node.nextNodes.approvers']);
        $nextNodes = $task->node->nextNodes ?? null;
        if(!empty($nextNodes) && $nextNodes->isNotEmpty()){
            //条件分支类型
            /**
             * 条件分支判断需要根据 variables变量表取值匹配判断,如果咩有变量则报错卡住
             * 1.优先任务变量判断
             * 2.在是流程实例变量判断
             * 3.如果都没有则报错
             */

            $taskVariables = $task->variables ?? null;//任务变量
            $instanceVariables = $task->instance->variables->filter(function($iv){
                return !($iv->task_id > 0);
            });//流程实例变量

            $nextNode = $nextNodes->first();
            $nextNode = self::nodeCheck($nextNode,$taskVariables,$instanceVariables);
        }

        if(!isset($nextNode) || $nextNode === 'finish' || $nextNode === null){
            //没有表示卡主或者流程完成
            $task->instance->setComplete();
            $task->instance->save();
        }else{

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

            $nextNode->loadMissing('attr');
            if(!$nextNode->attr->isPeople()){
                //自动完成或自动拒绝
                $this->taskPromote($newTask,null,0,$nextNode->attr->isAutoPass()?'pass':'reject');
            }
        }

    }

    /**
     * 节点检查
     * @param $nextNode
     * @param $taskVariables
     * @param $instanceVariables
     */
    private function nodeCheck($nextNode,$taskVariables,$instanceVariables){
        if(!empty($nextNode)){
            if($nextNode->type === NProcessNode::TYPE_BRANCH){
                $nextNode = self::branchNodeCheck($nextNode,$taskVariables,$instanceVariables);
            }else if($nextNode->type === NProcessNode::TYPE_CONDITION){
                $nextNode = self::conditionNodeCheck($nextNode,$taskVariables,$instanceVariables);
            }
        }

        return $nextNode;
    }

    /**
    * 路由分支节点检查
    * @param $branchNode
    * @param $taskVariables
    * @param $instanceVariables
    */
    private function branchNodeCheck($branchNode,$taskVariables,$instanceVariables){
        $branchNode->loadMissing(['nextNodes.nextNodes', 'nextNodes.conditions', 'attr']);

        //分支节点，需要先获取到下面的分支
        $branchChildNodes =  $branchNode->branchNextNodes->filter(function($node) use($branchNode){
            return $node->is_branch_child === NProcessNode::DISABLE_BRANCH_CHILD;
        })->sortBy(function(NProcessNode $node){
            return $node->conditions->isEmpty() ? 1 : 0;
        });
        foreach($branchChildNodes as $branchChildNode){
            if($branchChildNode->conditions->isEmpty()){
                $nextNode = $branchChildNode->nextNodes->first();
            }else{
                $res = self::conditionNodeCheck($branchChildNode,$taskVariables,$instanceVariables);
                if($res){
                    $nextNode = $branchChildNode->nextNodes->first();
                    break;
                }
            }
        }
        //如何是条件或者分支节点，又重新算一次
        $nextNode = self::nodeCheck($nextNode,$taskVariables,$instanceVariables);

        $nextNode = $nextNode ?? $branchChildNodes->nextNodes->filter(function($node) use($branchNode){
            return $node->is_branch_child === NProcessNode::ENABLE_BRANCH_CHILD;
        })->first();
        return $nextNode;
    }

    /**
     * 条件节点检查
     * @param NProcessNode $conditionNode
     * @param Collection $taskVariables
     * @param Collection $instanceVariables
     * @return boolean|string
     */
    private function conditionNodeCheck($conditionNode,$taskVariables,$instanceVariables){
        $conditionNode->loadMissing(['nextNodes.nextNodes', 'nextNodes.conditions', 'attr']);
        //当前条件节点相关信息
        $conditionNodeAttr = $conditionNode->attr;
        $towards = $conditionNodeAttr->towards;
        $conditionType = $conditionNodeAttr->condition_type ?? $conditionNodeAttr->approve_type;

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
                if(intval($conditionType) === NProcessNodeAttr::FORMULA){
                    //公式
                    $conditionVals = [];
                    if(mb_strpos($conditionVal,">=") !== false){
                        $conditionVals = explode(">=",$conditionVal);
                        $optType='gte';
                    }else if(mb_strpos($conditionVal,"<=") !== false){
                        $conditionVals = explode("<=",$conditionVal);
                        $optType='lte';
                    }else if(mb_strpos($conditionVal,">") !== false){
                        $conditionVals = explode(">",$conditionVal);
                        $optType='gt';
                    }else if(mb_strpos($conditionVal,"<") !== false){
                        $conditionVals = explode("<",$conditionVal);
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
                    $conditionField = trim($conditionVals[0] ?? '');
                    $conditionVal = trim($conditionVals[1] ?? '');
                }else if(intval($conditionType) === NProcessNodeAttr::RULE){
                    //选择非公式的逻辑判断
                    $conditionValueType = $condition['value_type'];
                    if($conditionValueType === NProcessNodeCondition::VALUE_TYPE_CONSTANT){
                        if($conditionVal === '假' || $conditionVal === '真'){
                            $conditionVal = $conditionVal === '假' ? false : true;
                        }
                    }
                }

                //先判断任务变量中是否存在，在从流程实例变量中取
                if( ( $taskVariables && $varFind = $taskVariables->first(function($tv) use ($conditionField){
                        return $tv->name === $conditionField;
                    }) )
                    || ( $instanceVariables && $varFind = $instanceVariables->first(function($tv) use ($conditionField){
                        return $tv->name === $conditionField;
                    }) )
                ){
                    switch ($optType){
                        case 'gte':
                            $conditionFlag = $varFind->real_value >= $conditionVal;
                            break;
                        case 'lte':
                            $conditionFlag = $varFind->real_value <= $conditionVal;
                            break;
                        case 'gt':
                            $conditionFlag = $varFind->real_value > $conditionVal;
                            break;
                        case 'lt':
                            $conditionFlag = $varFind->real_value < $conditionVal;
                            break;
                        case 'neq':
                            if($varFind->type === 'string'){
                                $conditionFlag = $varFind->real_value !== $conditionVal;
                            }else if($varFind->type === 'integer'){
                                $conditionFlag = $varFind->real_value !== intval($conditionVal);
                            }else if($varFind->type === 'double'){
                                $conditionFlag = $varFind->real_value !== doubleval($conditionVal);
                            }else if($varFind->type === 'float'){
                                $conditionFlag = $varFind->real_value !== floatval($conditionVal);
                            }else if($varFind->type === 'boolean'){
                                $conditionFlag = $varFind->real_value !== ($conditionVal === 'true');
                            }else if($varFind->type === 'array'){
                                $conditionFlag = $varFind->real_value !== json_decode($conditionVal, true);
                            }else if($varFind->type === 'object'){
                                $conditionFlag = $varFind->real_value !== json_decode($conditionVal, true);
                            }
                            break;
                        case 'eq':
                            if($varFind->type === 'string'){
                                $conditionFlag = $varFind->real_value === $conditionVal;
                            }else if($varFind->type === 'integer'){
                                $conditionFlag = $varFind->real_value === intval($conditionVal);
                            }else if($varFind->type === 'double'){
                                $conditionFlag = $varFind->real_value === doubleval($conditionVal);
                            }else if($varFind->type === 'float'){
                                $conditionFlag = $varFind->real_value === floatval($conditionVal);
                            }else if($varFind->type === 'boolean'){
                                $conditionFlag = $varFind->real_value === ($conditionVal === 'true');
                            }else if($varFind->type === 'array'){
                                $conditionFlag = $varFind->real_value === json_decode($conditionVal, true);
                            }else if($varFind->type === 'object'){
                                $conditionFlag = $varFind->real_value === json_decode($conditionVal, true);
                            }
                            break;
                    }
                }else{
                    $conditionFlag = false;
                }

                $groupFlag = $groupFlag && $conditionFlag;
            }

            $groupSumFlag = $groupSumFlag || $groupFlag;
        }

        if(!empty($towards))
        {
            //进入这里标识是特殊的条件节点
            if( ($towards === NProcessNodeAttr::TRUE_FINISH && $groupSumFlag)
                || ($towards === NProcessNodeAttr::FALSE_FINISH && !$groupSumFlag)
                || $towards === NProcessNodeAttr::DIRECT_FINISH
            )
            {
                return "finish";
            }else{
                $nextNode = self::nodeCheck($conditionNode->nextNodes->first(),$taskVariables,$instanceVariables);
                if( ($towards === NProcessNodeAttr::TRUE_DOWN_SKIP && $groupSumFlag)
                || ($towards === NProcessNodeAttr::FALSE_DOWN_SKIP && !$groupSumFlag) )
                {
                     $nextNode = self::nodeCheck($nextNode->nextNodes->first(),$taskVariables,$instanceVariables);
                }
                return $nextNode;
            }
        }else{
           return $groupSumFlag;//返回条件节点或分支节点的公式元算结果
        }
    }

}
