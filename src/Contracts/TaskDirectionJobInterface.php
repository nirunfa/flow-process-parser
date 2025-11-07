<?php

namespace Nirunfa\FlowProcessParser\Contracts;

/**
 * TaskDirectionJob 接口
 * 允许外部项目实现自定义的任务走向逻辑
 */
interface TaskDirectionJobInterface
{
    /**
     * 处理任务走向
     * 
     * @param int $taskId 任务ID
     * @return void
     */
    public function handle();
}

