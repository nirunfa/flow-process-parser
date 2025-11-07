<?php

namespace Nirunfa\FlowProcessParser\Events\TaskDirection;

/**
 * 节点检查后事件
 */
class NodeChecked extends TaskDirectionEvent
{
    public $nextNode;
    public $result;

    public function __construct($taskId, $task, $nextNode, $result)
    {
        parent::__construct($taskId, $task);
        $this->nextNode = $nextNode;
        $this->result = $result;
    }
}

