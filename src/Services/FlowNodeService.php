<?php

namespace Nirunfa\FlowProcessParser\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Nirunfa\FlowProcessParser\Models\NProcessForm;
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
                if( ( $variables && isset($variables[$conditionField]))
                ){
                    $varFind = $variables[$conditionField];
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
            if(isset($node['childNode'])){
                $node = $node['childNode'];
            }else{
                return 1;
            }
        }

        return $groupSumFlag 
            || ($towards === NProcessNodeAttr::TRUE_DOWN_SKIP && !$groupSumFlag)
            || ($towards === NProcessNodeAttr::FALSE_DOWN_SKIP && $groupSumFlag);
    }


    /**
     * 
     * 模拟流程启动，去 getFlowAllLines 获取所有路径，然后根据路径模拟流程启动
     * 返回最长的
     * @param string|array $linesData 
     * @param array $variables
     * @return array
     */
    public static function flowStart($linesData,$variables){
        if(is_string($linesData)){
            $linesData = self::getFlowAllLines($linesData);
        }
        $taskList = [];

        //遍历所有路径，模拟流程启动
        $line = Arr::first(
            Arr::sort($linesData,function($line)use($variables){
                $linePathNodes = $line['path_nodes'];
                //条件节点
                $conditionMap = Arr::where($linePathNodes,function($line){
                    return intval($line['type']) === NProcessNode::TYPE_CONDITION;
                });
                $conditionMatchStr = self::conditionCheck($conditionMap,$variables);
                
                return -1*strlen($conditionMatchStr);
            })
        );
        // foreach($linesData as $line){
            $linePathNodes = $line['path_nodes'];
           
            $tasks = [];
            for($i =0;$i<count($linePathNodes);$i++){
                $nextNode = $linePathNodes[$i];
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
    
                }else if($nodeType === NProcessNode::TYPE_CONDITION){
                    $checkRes = self::conditionNodeCheck($nextNode,$variables);
                    if($checkRes === false || $checkRes === 'finish'){
                        break;
                    }
                    if(is_numeric($checkRes)){
                        $i+=$checkRes;
                    }
                }
            }
            $taskList[] = $tasks;
        // }

        return ['tasks'=>array_values(Arr::sort($taskList,function($tasks){
            return -count($tasks);
        }))[0] ?? [],'lines'=>$linesData];
    }

    private static function conditionCheck($conditionNodeList,$variables){
        $conditionCheck = '1';
        foreach($conditionNodeList as $conditionNode){
            $checkRes = self::conditionNodeCheck($conditionNode,$variables);
            // || $checkRes === 1
            if($checkRes === false || $checkRes === 'finish'){
                break;
            }else{
                $conditionCheck .= '1';
            }
        }
        return $conditionCheck;
    }

    /**
     * 将流程 JSON 解析成所有可能的分支路径
     * 
     * @param string $json JSON 字符串
     * @return array 返回所有分支路径的数组，每条分支是一个节点数组
     * @throws \InvalidArgumentException
     */
    public static function getFlowAllLines($json){
        // 解析 JSON
        $data = json_decode($json, true);
        if ($data === null) {
            throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        // 存储所有分支路径
        $allLines = [];
        
        // 从根节点开始遍历，获取所有路径
        // 使用空数组作为初始的汇聚节点栈
        self::traverseAllPaths($data, [], $allLines, []);
        
        // 格式化输出，添加路径描述
        return self::formatFlowLines($allLines);
    }

    /**
     * 递归遍历所有可能的路径
     * 
     * @param array|null $node 当前节点
     * @param array $currentPath 当前路径（节点数组）
     * @param array &$allLines 所有路径的引用
     * @param array $mergeNodeStack 分支汇聚节点栈（用于处理嵌套分支）
     * @return void
     */
    private static function traverseAllPaths($node, $currentPath, &$allLines, $mergeNodeStack = [])
    {
        // 如果节点为空
        if (empty($node)) {
            // 如果有汇聚节点栈，依次处理栈中的汇聚节点
            if (!empty($mergeNodeStack)) {
                // 取出栈顶的汇聚节点
                $nextMergeNode = array_shift($mergeNodeStack);
                // 继续遍历汇聚节点，并传递剩余的栈
                self::traverseAllPaths($nextMergeNode, $currentPath, $allLines, $mergeNodeStack);
            } else if (!empty($currentPath)) {
                // 栈为空且路径不为空，保存当前路径
                $allLines[] = $currentPath;
            }
            return;
        }

        // 将当前节点信息添加到路径中
        $nodeInfo = [
            'id' => $node['id'] ?? null,
            'name' => $node['name'] ?? '',
            'type' => $node['type'] ?? null,
            'pid' => $node['pid'] ?? null,
            'status' => $node['status'] ?? null,
        ];

        // 根据节点类型添加额外信息
        if (isset($node['attr'])) {
            $nodeInfo['attr'] = $node['attr'];
        }
        if (isset($node['approverGroups'])) {
            $nodeInfo['approverGroups'] = $node['approverGroups'];
        }
        if (isset($node['conditionGroup'])) {
            $nodeInfo['conditionGroup'] = $node['conditionGroup'];
            $nodeInfo['conditionDescription'] = self::parseConditionGroup($node['conditionGroup']);
        }
        if (isset($node['content'])) {
            $nodeInfo['content'] = $node['content'];
        }
        if (isset($node['formDesignData'])) {
            $nodeInfo['formDesignData'] = $node['formDesignData'];
        }

        $currentPath[] = $nodeInfo;

        $nodeType = $node['type'] ?? null;

        // 处理不同类型的节点
        if ($nodeType == 4) {
            // 分支节点 (TYPE_BRANCH) - 路由节点
            // 分支节点的 childNode 是所有条件分支汇聚后要走的节点
            $currentMergeNode = $node['childNode'] ?? null;
            
            // 创建新的汇聚节点栈：将当前分支的汇聚节点加入栈顶
            $newMergeNodeStack = $mergeNodeStack;
            if ($currentMergeNode !== null) {
                array_unshift($newMergeNodeStack, $currentMergeNode);
            }
            
            // 需要遍历所有条件分支，每个分支独立生成路径
            if (isset($node['conditionNodes']) && is_array($node['conditionNodes'])) {
                // 补充"其他情况"节点的条件表达式
                $processedConditionNodes = self::fillDefaultConditionExpressions($node['conditionNodes']);
                
                foreach ($processedConditionNodes as $conditionNode) {
                    // 为每个条件分支创建独立的路径
                    // 将新的汇聚节点栈传递下去
                    self::traverseAllPaths($conditionNode, $currentPath, $allLines, $newMergeNodeStack);
                }
            } else {
                // 如果没有条件分支，直接处理汇聚节点栈
                if (!empty($newMergeNodeStack)) {
                    $nextMergeNode = array_shift($newMergeNodeStack);
                    self::traverseAllPaths($nextMergeNode, $currentPath, $allLines, $newMergeNodeStack);
                } else {
                    // 没有子节点也没有汇聚节点，保存当前路径
                    $allLines[] = $currentPath;
                }
            }
        } elseif ($nodeType == 3) {
            // 条件节点 (TYPE_CONDITION) - 带薪假、大于3天等条件判断节点
            // 继续遍历条件节点内部的子节点
            if (isset($node['childNode'])) {
                // 将汇聚节点栈继续传递下去
                self::traverseAllPaths($node['childNode'], $currentPath, $allLines, $mergeNodeStack);
            } else {
                // 条件节点链结束，处理汇聚节点栈
                if (!empty($mergeNodeStack)) {
                    $nextMergeNode = array_shift($mergeNodeStack);
                    self::traverseAllPaths($nextMergeNode, $currentPath, $allLines, $mergeNodeStack);
                } else {
                    // 没有汇聚节点，保存当前路径
                    $allLines[] = $currentPath;
                }
            }
        } else {
            // 其他节点类型（发起人、审批人等）
            // 继续遍历子节点
            if (isset($node['childNode'])) {
                self::traverseAllPaths($node['childNode'], $currentPath, $allLines, $mergeNodeStack);
            } else {
                // 节点链结束，处理汇聚节点栈
                if (!empty($mergeNodeStack)) {
                    $nextMergeNode = array_shift($mergeNodeStack);
                    self::traverseAllPaths($nextMergeNode, $currentPath, $allLines, $mergeNodeStack);
                } else {
                    // 没有汇聚节点，保存当前路径
                    $allLines[] = $currentPath;
                }
            }
        }
    }

    /**
     * 补充"其他情况"节点的条件表达式
     * 
     * @param array $conditionNodes 条件节点数组
     * @return array 处理后的条件节点数组
     */
    private static function fillDefaultConditionExpressions($conditionNodes)
    {
        if (empty($conditionNodes) || count($conditionNodes) <= 1) {
            return $conditionNodes;
        }

        // 收集所有有明确条件的节点
        $explicitConditions = [];
        $defaultNodeIndex = null;
        
        foreach ($conditionNodes as $index => $node) {
            $conditionGroup = $node['conditionGroup'] ?? [];
            
            // 判断是否为"其他情况"节点（没有条件或条件为空）
            if (empty($conditionGroup)) {
                $defaultNodeIndex = $index;
            } else {
                // 收集所有条件表达式
                foreach ($conditionGroup as $group) {
                    $conditions = $group['conditions'] ?? [];
                    foreach ($conditions as $condition) {
                        // 判断是新格式还是旧格式
                        if (isset($condition['columnValue']) && isset($condition['optType'])) {
                            // 新格式：构建表达式
                            $conditionExpr = self::buildConditionExpression($condition);
                        } else {
                            // 旧格式：直接使用
                            $conditionExpr = $condition['conditionValue'][0] ?? '';
                        }
                        
                        if (!empty($conditionExpr)) {
                            $explicitConditions[] = $conditionExpr;
                        }
                    }
                }
            }
        }
        
        // 如果找到了"其他情况"节点且有其他明确条件
        if ($defaultNodeIndex !== null && !empty($explicitConditions)) {
            // 生成否定条件
            $negatedConditions = self::generateNegatedConditions($explicitConditions);
            
            // 更新"其他情况"节点的条件
            if (!empty($negatedConditions)) {
                $conditionNodes[$defaultNodeIndex]['attr']['branchType'] = NProcessNodeAttr::FORMULA;
                $conditionNodes[$defaultNodeIndex]['conditionGroup'] = [[
                    'id' => 'generated_' . uniqid(),
                    'condition' => 'AND',
                    'conditions' => array_map(function($expr) {
                        return [
                            'id' => 'generated_' . uniqid(),
                            'conditionValue' => [$expr],
                            'conditionValueName' => [$expr]
                        ];
                    }, $negatedConditions)
                ]];
                
                // 更新描述
                $conditionNodes[$defaultNodeIndex]['content'] = '[' . implode('] 且 [', $negatedConditions) . ']';
            }
        }
        
        return $conditionNodes;
    }
    
    /**
     * 生成条件表达式的否定形式
     * 
     * @param array $conditions 条件表达式数组
     * @return array 否定条件数组
     */
    private static function generateNegatedConditions($conditions)
    {
        $negated = [];
        
        foreach ($conditions as $condition) {
            $condition = trim($condition);
            
            // 处理不同的运算符
            if (strpos($condition, '>=') !== false) {
                // a >= b 的否定是 a < b
                $negated[] = str_replace('>=', '<', $condition);
            } elseif (strpos($condition, '<=') !== false) {
                // a <= b 的否定是 a > b
                $negated[] = str_replace('<=', '>', $condition);
            } elseif (strpos($condition, '>') !== false) {
                // a > b 的否定是 a <= b
                $negated[] = str_replace('>', '<=', $condition);
            } elseif (strpos($condition, '<') !== false) {
                // a < b 的否定是 a >= b
                $negated[] = str_replace('<', '>=', $condition);
            } elseif (strpos($condition, '!=') !== false || strpos($condition, '<>') !== false) {
                // a != b 的否定是 a == b
                $negated[] = str_replace(['!=', '<>'], '==', $condition);
            } elseif (strpos($condition, '==') !== false) {
                // a == b 的否定是 a != b
                $parts = explode('==', $condition);
                if (count($parts) == 2) {
                    $negated[] = trim($parts[0]) . ' != ' . trim($parts[1]);
                }
            }
        }
        
        return $negated;
    }

    /**
     * 解析条件组，生成易读的条件描述
     * 
     * @param array $conditionGroups 条件组数组
     * @return string 条件描述
     */
    private static function parseConditionGroup($conditionGroups)
    {
        if (empty($conditionGroups)) {
            return '其他情况';
        }

        $descriptions = [];
        foreach ($conditionGroups as $group) {
            $conditions = $group['conditions'] ?? [];
            $groupCondition = $group['condition'] ?? 'AND';
            
            $conditionTexts = [];
            foreach ($conditions as $condition) {
                // 判断是新格式还是旧格式
                if (isset($condition['columnValue']) && isset($condition['optType'])) {
                    // 新格式：包含 columnValue, optType 等字段
                    $conditionText = self::buildConditionExpression($condition);
                } else {
                    // 旧格式：直接使用 conditionValue
                    $conditionText = $condition['conditionValue'][0] ?? '';
                }
                
                if (!empty($conditionText)) {
                    $conditionTexts[] = $conditionText;
                }
            }
            
            if (!empty($conditionTexts)) {
                $descriptions[] = implode(' 且 ', $conditionTexts);
            }
        }
        
        return !empty($descriptions) ? implode(' 或 ', $descriptions) : '其他情况';
    }
    
    /**
     * 根据新格式条件字段构建条件表达式
     * 
     * @param array $condition 条件数组
     * @return string 条件表达式
     */
    private static function buildConditionExpression($condition)
    {
        $columnValue = $condition['columnValue'] ?? '';
        $optType = $condition['optType'] ?? '';
        $value = $condition['conditionValue'][0] ?? '';
        
        // 操作符映射
        $operatorMap = [
            'eq' => '==',
            'neq' => '!=',
            'gt' => '>',
            'gte' => '>=',
            'lt' => '<',
            'lte' => '<=',
            'like' => 'like',
            'not_like' => 'not like',
            'in' => 'in',
            'not_in' => 'not in',
        ];
        
        $operator = $operatorMap[$optType] ?? $optType;
        
        // 构建表达式
        if ($operator === 'in' || $operator === 'not in') {
            return "{$columnValue} {$operator} ({$value})";
        } else {
            return "{$columnValue} {$operator} {$value}";
        }
    }

    /**
     * 格式化流程路径，添加路径描述
     * 
     * @param array $allLines 所有路径
     * @return array 格式化后的路径数组
     */
    private static function formatFlowLines($allLines)
    {
        $formattedLines = [];
        
        foreach ($allLines as $index => $line) {
            // 提取路径关键信息
            $pathDescription = [];
            $approverNodes = [];
            $conditionNodes = [];
            
            foreach ($line as $node) {
                $nodeType = $node['type'];
                
                // 收集审批节点
                if ($nodeType == 1) {
                    $approverNodes[] = $node['name'];
                }
                
                // 收集条件节点
                if ($nodeType == 3 && isset($node['conditionDescription'])) {
                    $conditionNodes[] = [
                        'name' => $node['name'],
                        'condition' => $node['conditionDescription']
                    ];
                }
            }
            
            $formattedLines[] = [
                'line_number' => $index + 1,
                'path_nodes' => $line,
                'approver_sequence' => $approverNodes,
                'conditions' => $conditionNodes,
                'description' => self::generatePathDescription($line),
            ];
        }
        
        return $formattedLines;
    }

    /**
     * 生成路径描述文本
     * 
     * @param array $nodes 节点数组
     * @return string 路径描述
     */
    private static function generatePathDescription($nodes)
    {
        $description = [];
        
        foreach ($nodes as $node) {
            $nodeType = $node['type'];
            $nodeName = $node['name'];
            
            // 根据节点类型生成描述
            switch ($nodeType) {
                case 0: // 发起人
                    $description[] = "【发起】{$nodeName}";
                    break;
                case 1: // 审批人
                    $description[] = "【审批】{$nodeName}";
                    break;
                case 2: // 抄送人
                    $description[] = "【抄送】{$nodeName}";
                    break;
                case 3: // 条件
                    if (isset($node['conditionDescription'])) {
                        $description[] = "【条件】{$nodeName}（{$node['conditionDescription']}）";
                    }
                    break;
                case 4: // 分支
                    $description[] = "【分支】{$nodeName}";
                    break;
            }
        }
        
        return implode(' → ', $description);
    }

}
