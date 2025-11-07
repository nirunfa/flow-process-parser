<?php

namespace Nirunfa\FlowProcessParser\Services;

use Nirunfa\FlowProcessParser\Models\NProcessForm;
use Nirunfa\FlowProcessParser\Models\NProcessTask;
use Nirunfa\FlowProcessParser\Models\NProcessVariable;

class FlowTaskService
{
    /**
     * 根据相关条件获取任务列表
     * @param array{
     *     code: string,
     *     instance_id: int,
     *     task_name?: string,
     *     status?: number
     * } $searchParam 用户数据数组, code、instance_id 至少要传一个 tansk_name、status 为可选参数
     * @return array {
     *     current_page: int,
     *     data: array,
     *     total: int,
     *     last_page: int,
     *     per_page: int,
     * }
     */
    public static function getTasks(array $searchParam = [])
    {
        $instanceCode = $searchParam['code'] ?? '';
        $instanceId = $searchParam['instance_id'] ?? '';
        $taskName = $searchParam['task_name'] ?? '';
        $status = $searchParam['status'] ?? ['0', 1];

        if (empty($instanceCode) && empty($instanceId)) {
            return ['code'=>400,'message'=>'流程单号或流程ID 不能都为空!'];
        }

        $query = NProcessTask::query()->with(['assignees', 'node']);
        if (!empty($instanceCode)) {
            $query->whereHas('instance', function ($q) use ($instanceCode) {
                $q->where('code', 'like', '%' . $instanceCode . '%');
            });
        }
        if ($instanceId > 0) {
            $query->where('instance_id', $instanceId);
        }
        if (!empty($taskName)) {
            $query->where('name', 'like', '%' . $taskName . '%');
        }
        if (!empty($status)) {
            if (!is_array($status)) {
                $status = [$status];
            }
            $query->whereIn('status', $status);
        }
        $tasks = $query->paginate($searchParam['per_page'] ?? 30);
        $tasks->transform(function ($item) {
            $item->loadMissing(['node.attr', 'node.form', 'assignees.nodeApprover']);
            $data = [
                'id' => $item->id,
                'name' => $item->name,
                'description' => $item->description,
                'status' => $item->status,
                'assignees' => $item->assignees->isNotEmpty() ? $item->assignees->sortBy('nodeApprover.order_sort')
                    ->map(function ($assigneeItem) {
                        $assigneeItem->loadMissing('nodeApprover');
                        $approver = $assigneeItem->nodeApprover;
                        if (is_null($approver)) {
                            return $assigneeItem->assignee_id > 0 ? [
                                'id' => 0,
                                'name' => $assigneeItem->assignee,
                                'approver' => $assigneeItem->assignee_id,
                                'approver_name' => [],
                                'approver_type' => '',
                                'order' => 0,
                            ] : null;
                        }
                        return [
                            'id' => $approver->id,
                            'name' => $approver->name,
                            'level_mode' => $approver->level_mode,
                            'loop_count' => $approver->loop_count,
                            'approver' => $approver->approver_ids,
                            'approver_name' => $approver->approver_names,
                            'approver_type' => $approver->approver_type,
                            'order' => $approver->order_sort,
                        ];
                    })->toArray() : [],
                'assignee_type' => $item->node->attr->approve_type ?? null,
                'assignee_mode' => $item->node->attr->approve_mode ?? null,
                'initiator_same' => $item->node->attr->approver_same_initiator ?? null,
                'approver_empty' => $item->node->attr->approver_empty ?? null,

                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
                'deleted_at' => $item->deleted_at
            ];

            $formInfo = [];
            if (isset($item->node->form)) {
                $formInfo = $item->node->form->toArray();
                if ($item->node->form instanceof NProcessForm) {
                    $formInfo['fields'] = json_decode($item->node->form->json_content, true);
                    unset($formInfo['json_content']);
                }
            }
            $data['form'] = $formInfo;
            return $data;
        });
        return $tasks->toArray();
    }
    /**
     * 添加或修改流程任务变量
     * @param $taskId
     * @param array $variableData
     *   type 变量值类型  string字符串 等
     *   name 变量名
     *   value 变量值
     * @return mixed
     */
    public static function addTaskVariables($taskId, array $variableData = [])
    {
        $task = NProcessTask::query()->find($taskId);
        if (empty($task)) {
            return ('流程任务不存在!');
        }
        if (isset($variableData['name'])) {
            //表示传的一维
            $variableData['instance_id'] = $task->instance_id;
            $variableData['task_id'] = $taskId;
            if (!isset($variableData['type'])) {
                $variableData['type'] = gettype($variableData['value']);
            }
            $variable = NProcessVariable::query()
                ->updateOrCreate(['name' => $variableData['name'], 'instance_id' => $task->instance_id, 'task_id' => $taskId], $variableData);
            return [$variable];
        } else if (count($variableData) > 0) {
            $variables = [];
            foreach ($variableData as $item) {
                if (!isset($item['type'])) {
                    $item['type'] = gettype($item['value']);
                }
                $item['instance_id'] = $task->instance_id;
                $item['task_id'] = $taskId;
                if ($item['type'] == 'array') {
                    $item['value'] = json_encode($item['value']);
                }
                $variables[] = NProcessVariable::query()
                    ->updateOrCreate(['name' => $item['name'], 'instance_id' => $task->instance_id, 'task_id' => $taskId], $item);
            }
            if (count($variables) > 0) {
                return $variables;
            }
            return '变量添加失败';
        } else {
            return ('数据为空!');
        }
    }


    /**
     * 获取流程任务变量
     * @param int $taskId
     * @param ?string $name names 变量名称
     * @return array|string
     */
    public static function getTaskVariables($taskId, $name = '')
    {
        $task = NProcessTask::query()->with('variables')->find($taskId);
        if (empty($task)) {
            return ('流程任务不存在!');
        }
        if (!empty($name)) {
            return $task->variables->where('name', $name)->first()->toArray();
        }
        return $task->variables->toArray();
    }

    /**
     * 分配任务执行人
     * @param int $taskId 任务 id
     * @param array{{
       id:int,
       name:string
       order:int
     }} $assignees 二维数组 id-执行人id  name-执行人名称
     * @return array|string
     */
    public static function assignTaskAssinees($taskId, $assignees)
    {
        $task = NProcessTask::query()->find($taskId);
        if (empty($task)) {
            return ('流程任务不存在!');
        }

        //排序
        $assignees = collect($assignees)->sortBy('order')->values();

        $nodeAssigneesByOrder = $task->loadMissing('assignees.nodeApprover')->assignees
            ->sortBy('nodeApprover.order_sort');

        $syncIds = [];
        $assigneeIds = [];
        $nodeAssigneesByOrder->each(function ($orderItem, $key) use ($assignees, $taskId, &$syncIds, &$assigneeIds) {
            $matchAssignee = $assignees->where('order', $key)->first();
            if ($matchAssignee) {
                $syncIds[] = [
                    'assignee_id' => $matchAssignee['id'],
                    'assignee' => $matchAssignee['name'],
                    'task_id' => $taskId,
                    'node_approver_id' => $orderItem->node_approver_id
                ];
                $assigneeIds[] = $orderItem['id'];
            }
        });

        $task->assignees()->whereIn('id', $assigneeIds)->delete();
        $task->assignees()->createMany($syncIds);
        return $task;
    }

    /**
     * 添加任务评论
     * @param int $taskId
     * @param string $comment
     * @return mixed
     */
    public static function addComment($taskId, $comment)
    {
        $task = NProcessTask::query()->find($taskId);
        if (empty($task)) {
            return ('流程任务不存在!');
        }
        $task->comments()->create([
            'content' => $comment,
            'instance_id' => $task->instance_id,
        ]);
        return $task;
    }
}
