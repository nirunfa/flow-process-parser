<?php

namespace Nirunfa\FlowProcessParser\Events\TaskDirection;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 任务走向事件基类
 */
abstract class TaskDirectionEvent
{
    use Dispatchable, SerializesModels;

    public $taskId;
    public $task;

    public function __construct($taskId, $task = null)
    {
        $this->taskId = $taskId;
        $this->task = $task;
    }
}

