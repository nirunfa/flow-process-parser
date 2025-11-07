<?php

namespace Nirunfa\FlowProcessParser\Events\TaskDirection;

/**
 * 新任务创建前事件
 */
class NewTaskCreating extends TaskDirectionEvent
{
    public $nextNode;
    public $taskData;

    public function __construct($taskId, $task, $nextNode, $taskData)
    {
        parent::__construct($taskId, $task);
        $this->nextNode = $nextNode;
        $this->taskData = $taskData;
    }
}

