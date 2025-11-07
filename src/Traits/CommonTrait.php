<?php

namespace Nirunfa\FlowProcessParser\Traits;

// use Nirunfa\FlowProcessParser\Jobs\TaskDirectionJob; // 使用辅助函数 createTaskDirectionJob 替代
use Nirunfa\FlowProcessParser\Models\NProcessInstance;
use Nirunfa\FlowProcessParser\Models\NProcessTask;

trait CommonTrait
{
    /**
     * @param $taskId 节点任务 id
     * @return void
     */
    private function startTaskRedirectJob($taskId)
    {
        //开启任务走向job
        $useQueue = config('process_parser.json_parser.use_queue', false);
        if ($useQueue) {
            $queueName = config('process_parser.json_parser.queue_name');
            if (empty($queueName)) {
                $queueName = 'process_parser';
            }
            dispatch(createTaskDirectionJob($taskId))->onQueue($queueName);
        } else {
            dispatch_sync(createTaskDirectionJob($taskId));
        }
    }

    /**
     * 推进任务
     * @param $task
     * @param $formData
     * @param $formDataId
     * @param string $promoteStatus
     * @return mixed
     */
    private function taskPromote($task, $formData, $formDataId, $promoteStatus = 'pass')
    {
        if ($promoteStatus === 'pass') {
            //更新状态
            $task->update([
                'status' => NProcessTask::STATUS_COMPLETED,
            ]);

            //更新任务表单数据
            $task->assignees()->update([
                'form_data_id' => $formDataId,
                'form_data' => $formData,
            ]);

            self::startTaskRedirectJob($task->id);

            return $task;
        } else {
            //更新状态
            $task->instance->update([
                'status' => NProcessInstance::STATUS_REJECTED,
            ]);
            $task->update([
                'status' => NProcessTask::STATUS_COMPLETED,
            ]);
            //更新任务表单数据
            $task->assignees()->update([
                'form_data_id' => $formDataId,
                'form_data' => $formData,
            ]);

            //驳回目前只支持驳回到第一个节点
            $firstTask = $task->instance->tasks->first();
            $newFirstTask = $firstTask->replicate([
                'created_at',
                'updated_at',
                'deleted_at',
            ]);
            $newFirstTask->fill([
                'status' => NProcessTask::STATUS_APPROVING
            ]);
            $newFirstTask->save();

            $orgFirstTaskAssignee = $firstTask->loadMissing('assignees')->assignees->first();
            $newFirstTask->assignees()->create([
                'assignee_id' => $orgFirstTaskAssignee->assignee_id,
                'assignee' => $orgFirstTaskAssignee->assignee,
            ]);

            return $newFirstTask;
        }
        return null;
    }

    /**
     * 获取变量类型
     * @param mixed $var 变量值
     * @return string
     */
    private static function getVarType($var){
        if (is_array($var)) {
            return 'array';
        } elseif (is_object($var)) {
            return 'object';
        } elseif (is_string($var)) {
            return 'string';
        } elseif (is_int($var)) {
            return 'int';
        } elseif (is_float($var)) {
            return 'float';
        } elseif (is_bool($var)) {
            return 'bool';
        } elseif (is_null($var)) {
            return 'null';
        } else {
            return gettype($var);
        }
    }

}
