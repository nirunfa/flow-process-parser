<?php

namespace Nirunfa\FlowProcessParser\Events\TaskDirection;

/**
 * 新任务创建后事件
 */
class NewTaskCreated extends TaskDirectionEvent
{
    public $newTask;

    public function __construct($taskId, $task, $newTask)
    {
        parent::__construct($taskId, $task);
        $this->newTask = $newTask;
    }
}

