<?php

namespace Nirunfa\FlowProcessParser\Events\TaskDirection;

/**
 * 节点检查前事件
 */
class NodeChecking extends TaskDirectionEvent
{
    public $nextNode;
    public $taskVariables;
    public $instanceVariables;

    public function __construct($taskId, $task, $nextNode, $taskVariables, $instanceVariables)
    {
        parent::__construct($taskId, $task);
        $this->nextNode = $nextNode;
        $this->taskVariables = $taskVariables;
        $this->instanceVariables = $instanceVariables;
    }
}

