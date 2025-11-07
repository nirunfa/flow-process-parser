<?php

namespace Nirunfa\FlowProcessParser\Events\TaskDirection;

/**
 * 任务走向处理完成事件
 */
class TaskDirectionCompleted extends TaskDirectionEvent
{
    public $nextNode;
    public $newTask;

    public function __construct($taskId, $task, $nextNode = null, $newTask = null)
    {
        parent::__construct($taskId, $task);
        $this->nextNode = $nextNode;
        $this->newTask = $newTask;
    }
}

