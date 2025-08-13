<?php

namespace Nirunfa\FlowProcessParser\Services;

use Nirunfa\FlowProcessParser\Models\NProcessTask;
use Nirunfa\FlowProcessParser\Models\NProcessVariable;
use Nirunfa\FlowProcessParser\Resources\ProcessTaskCollection;
use Nirunfa\FlowProcessParser\Resources\ProcessTaskResource;

class FlowTaskService
{
    /**
     * 根据相关条件获取任务列表
     * @param array $searchParam
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function getTasks(array $searchParam =[]): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $instanceCode = $searchParam['code'] ?? '';
        $instanceId = $searchParam['instance_id'] ?? '';
        $taskName = $searchParam['task_name'] ?? '';
        $status = $searchParam['status'] ?? '';

        $query = NProcessTask::query();
        if($instanceCode){
            $query->whereHas('instance',function($q)use($instanceCode){
                $q->where('code','like','%'.$instanceCode.'%');
            });
        }
        if($instanceId){
            $query->where('instance_id',$instanceId);
        }
        if($taskName){
            $query->where('name','like','%'.$taskName.'%');
        }
        if($status){
            $query->where('status',$status);
        }
        $tasks = $query->paginate($searchParam['per_page'] ?? 30);
        $tasks->transform(function ($item){
            return new ProcessTaskResource($item);
        });
        return $tasks;
    }
    /**
     * 添加流程任务变量
     * @param $taskId
     * @param array $variableData
     *   type 变量值类型  string字符串 等
     *   name 变量名
     *   value 变量值
     * @return mixed
     */
    public static function addTaskVariables($taskId, array $variableData = []){
        $task = NProcessTask::query()->find($taskId);
        if(empty($task)){
            return ('流程任务不存在!');
        }
        if(isset($variableData['name'])){
            //表示传的一维
            $variableData['instance_id'] = $task->instance_id;
            $variableData['task_id'] = $taskId;
            if(!isset($variableData['type'])){
                $variableData['type'] = gettype($variableData['value']);
            }
            $variable = NProcessVariable::query()->create($variableData);
            return [$variable];
        }else if(count($variableData) > 0){
            $variables = [];
            foreach ($variableData as $item){
                if(!isset($item['type'])){
                    $item['type'] = gettype($item['value']);
                }
                $item['instance_id'] = $task->instance_id;
                $item['task_id'] = $taskId;
                $variables[] = NProcessVariable::query()->create($item);
            }
            if(count($variables) > 0){
                return $variables;
            }
            return '变量添加失败';
        }else{
            return ('数据为空!');
        }
    }
}