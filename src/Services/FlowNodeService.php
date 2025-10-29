<?php

namespace Nirunfa\FlowProcessParser\Services;

use Nirunfa\FlowProcessParser\Models\NProcessNode;
use Nirunfa\FlowProcessParser\Models\NProcessNodeAttr;
use Nirunfa\FlowProcessParser\Models\NProcessNodeCondition;


class FlowNodeService
{

    /**
     * 将流程设计的 json 字符串解析，生成对应线路的结点数组
     *
     * @param string $json
     * @param array $variables 流程变量
     * @return array
     * @throws \InvalidArgumentException
     */
    public static function parserStart(string $json,array $variables)
    {
        $data = json_decode($json, true);
        if ($data === null) {
            throw new \InvalidArgumentException('Invalid JSON');
        }

        $nodes = [];
        $nextNode = $data;
        $tasks = [];

        $branchChildNodes = [];

        do{
            $nodeType = $nextNode["type"];
            $attr = $nextNode["attr"] ?? []; //额外属性
            $approverList = $nextNode["approverGroups"] ?? []; //审批人｜处理人集合
            $formDesignData = $nextNode["formDesignData"] ?? null;

            $curNode = new NProcessNode([
                "id" => str_replace('-', '&', $nextNode["id"]),
                "name" => $nextNode["name"],
                "n_uuid" => $nextNode["id"],
                "type" => $nodeType,
                "prev_node_id" => 0,
                "prev_node_uuid" => $nextNode["pid"] ?? null,
                'is_branch_child' => false,
                'form_id' => $formDesignData["chooseFormId"] ?? null,
            ]);
            $nodes[] = $curNode;

            if (
                in_array($nodeType, [
                    NProcessNode::TYPE_INITIATOR,
                    NProcessNode::TYPE_APPROVER,
                    NProcessNode::TYPE_ASSIGNEE,
                ])
            ) {
                $task =[
                    "id" => $curNode->getAttribute('id'),
                    'name' => $curNode->getAttribute('name'),
                    "assignee_type" => $attr["approvalMethod"] ?? 1,
                    "assignee_mode" => $attr["approvalMode"] ?? 1,
                    "initiator_same" => $attr["sameMode"] ?? null,
                    "approver_empty" => $attr["noHander"] ?? null,
                ];
                $formInfo = [];
                $curNode->loadMissing('form');
                if (isset($curNode->form)) {
                    $formInfo = $curNode->form->toArray();
                    if ($curNode->form instanceof NProcessForm) {
                        $formInfo['fields'] = json_decode($curNode->form->json_content, true);
                        unset($formInfo['json_content']);
                    }
                }
                $task['form'] = $formInfo;
                $task['assignees'] = [];
                foreach ($approverList as $approverItem) {
                    $task['assignees'][] = [
                        'id' => $approverItem["id"] ?? null,
                        'name' => '',
                        'level_mode' => $approverItem["levelMode"] ?? null,
                        'loop_count' => isset($approverItem["loopCount"]) ? (is_array($approverItem["loopCount"])?($approverItem["loopCount"][0] ??
                        0):$approverItem["loopCount"]) : 0,
                        'approver' => is_array($approverItem["approverIds"])?($approverItem["approverIds"][0] ??
                        ""):$approverItem["approverIds"],
                        'approver_name' => is_array($approverItem["approverNames"])?($approverItem["approverNames"][0] ??
                         ""):$approverItem["approverNames"],
                        'approver_type' => $approverItem["approverType"] ?? null,
                        'order' => $approverItem["sort"],
                    ];
                }
                $tasks[] = $task;

                $nextNode = $nextNode['childNode'] ?? null;

            }else if($nodeType === NProcessNode::TYPE_BRANCH){
                if($nextNode['childNode']){
                    $branchChildNodes[] = $nextNode['childNode'];
                }

                //条件分叉中间分支
                $nextNode = self::branchNodeCheck($nextNode,$variables);
            }else if($nodeType === NProcessNode::TYPE_CONDITION){
                $checkRes = self::conditionNodeCheck($nextNode,$variables);
                if($checkRes !== 'finish'){
                    $nextNode = $nextNode['childNode'] ?? null;
                }else{
                    break;
                }
            }

            if(empty($nextNode)){
                $nextNode = array_pop($branchChildNodes) ?? null;
            }

        }while(!empty($nextNode));

        return ['nodes'=>$nodes,'tasks'=>$tasks];
    }

    /**
     * 条件分叉节点条件检查
     * @param $node
     * @param $variables
     * return array
     */
    private static function branchNodeCheck($node,$variables){
        $conditionChilds = collect($node['conditionNodes'])->sortBy(function($item, $key) {
            if(empty($item['conditionGroup'])) {
                return 1;
            }
            return 0;
        });

        foreach($conditionChilds->toArray() as $conditionChild){
            if(self::conditionNodeCheck($conditionChild,$variables)){
                return $conditionChild['childNode'];
            }
        }

        return $conditionChilds->last()['childNode'] ?? null;
    }

    /**
     * 条件结点 check
     *
     * @param array $node 节点信息
     * @param array $variables 变量信息
     * @return boolean|string
     */
    private static function conditionNodeCheck(&$node,$variables){
        //当前条件节点相关信息
        $conditionNodeAttr = $node["attr"] ?? []; //额外属性
        $towards = $conditionNodeAttr['towards'] ?? null;
        $conditionType = $conditionNodeAttr['conditionType'] ?? $conditionNodeAttr['branchType'];

        $groupSumFlag = false;//所有组条件或
        $groupConditions = $node['conditionGroup'] ?? [];
        foreach($groupConditions as $groupCondition){
            $groupFlag = true;
            $conditionList = $groupCondition["conditions"] ?? []; //且条件
            foreach($conditionList as $condition){
                $conditionVal = $condition['conditionValue'][0];
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
                        $conditionVals = explode("!=",str_replace("<>","!=",$conditionVal));
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
                    $optType = $groupCondition['optType'];
                    $conditionValueType = intval($condition['valueType']);
                    if($conditionValueType === NProcessNodeCondition::VALUE_TYPE_CONSTANT){
                        if($conditionVal === '假' || $conditionVal === '真'){
                            $conditionVal = $conditionVal === '假' ? false : true;
                        }
                    }
                }

                //先判断任务变量中是否存在，在从流程实例变量中取
                if( ( $variables && $varFind = $variables[$conditionField])
                ){
                    switch ($optType){
                        case 'gte':
                            $conditionFlag = $varFind >= $conditionVal;
                            break;
                        case 'lte':
                            $conditionFlag = $varFind <= $conditionVal;
                            break;
                        case 'gt':
                            $conditionFlag = $varFind > $conditionVal;
                            break;
                        case 'lt':
                            $conditionFlag = $varFind < $conditionVal;
                            break;
                        case 'neq':
                            if(is_string($varFind)){
                                $conditionFlag = $varFind !== $conditionVal;
                            }else if(is_numeric($varFind)){
                                if(is_integer($varFind)){
                                    $conditionFlag = $varFind !== intval($conditionVal);
                                }else if(is_double($varFind)){
                                    $conditionFlag = $varFind !== doubleval($conditionVal);
                                }else if(is_float($varFind)){
                                    $conditionFlag = $varFind !== floatval($conditionVal);
                                }
                            }else if(is_bool($varFind)){
                                $conditionFlag = $varFind !== ($conditionVal=== 'true');
                            }
                            break;
                        case 'eq':
                            if(is_string($varFind)){
                                $conditionFlag = $varFind === $conditionVal;
                            }else if(is_numeric($varFind)){
                                if(is_integer($varFind)){
                                    $conditionFlag = $varFind === intval($conditionVal);
                                }else if(is_double($varFind)){
                                    $conditionFlag = $varFind === doubleval($conditionVal);
                                }else if(is_float($varFind)){
                                    $conditionFlag = $varFind === floatval($conditionVal);
                                }
                            }else if(is_bool($varFind)){
                                $conditionFlag = $varFind === ($conditionVal=== 'true');
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

        if( ($towards === NProcessNodeAttr::TRUE_FINISH && $groupSumFlag)
            || ($towards === NProcessNodeAttr::FALSE_FINISH && !$groupSumFlag)
            || $towards === NProcessNodeAttr::DIRECT_FINISH
        )
        {
            return "finish";
        }

        if( ($towards === NProcessNodeAttr::TRUE_DOWN_SKIP && $groupSumFlag)
        || ($towards === NProcessNodeAttr::FALSE_DOWN_SKIP && !$groupSumFlag) )
        {
            $node = $node['childNode'];
        }

        return $groupSumFlag;
    }


    public static function getFlowAllLines($json){
        
    }

}
