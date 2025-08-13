<?php

namespace Nirunfa\FlowProcessParser\Services;

use Illuminate\Support\Facades\DB;
use Nirunfa\FlowProcessParser\Jobs\TaskDirectionJob;
use Nirunfa\FlowProcessParser\Models\NProcessDefinitionVersion;
use Nirunfa\FlowProcessParser\Models\NProcessInstance;
use Nirunfa\FlowProcessParser\Models\NProcessNode;
use Nirunfa\FlowProcessParser\Models\NProcessTask;
use Nirunfa\FlowProcessParser\Models\NProcessVariable;
use Nirunfa\FlowProcessParser\Repositories\ProcessDefinitionRepository;
use Nirunfa\FlowProcessParser\Repositories\ProcessInstanceRepository;
use Nirunfa\FlowProcessParser\Resources\ProcessInstanceResource;

/**
 * 流程服务类
 */
class FlowProcessService
{
    /**
     * 创建一个流程
     * @param $dataParam
     * @param int $definitionId 流程定义 id
     * @param int $userId 发起人 id
     * @param string $userName 发起人名称或昵称等字符串信息
     * @param string $title 流程标题,不传默认和流程定义的名称
     * @param string $code 流程单号
     * @return mixed
     */
    public static function createProcess($dataParam = []){
        $definitionId = $dataParam['definition_id'] ?? null;
        $userId = $dataParam['user_id'] ?? null;//发起人 id
        $userName = $dataParam['user_name'] ?? null;//发起人名称或昵称等字符串信息
        $title = $dataParam['title'] ?? null;//流程标题
        $code = $dataParam['code'] ?? getCode();//流程单号
        if(!empty($definitionId) && $definitionId > 0 && !empty($userId) && $userId > 0){
            $definition = ProcessDefinitionRepository::find($definitionId);
            if(empty($definition)){
                return ('流程定义不存在!');
            }

            return DB::transaction(function () use ($definitionId, $definition, $userId, $title, $code,$userName){
                $instanceData = [
                    'title'=>$title ?? $definition->name,
                    'definition_id'=>$definitionId,
                    'code'=>$code,
                    'initiator_id'=>$userId,
                    'initiator'=>$userName,
                    'status'=>NProcessInstance::STATUS_UNSTARTED,
                    'is_archive'=>NProcessInstance::IS_ARCHIVE_NO,
                ];
                //创建流程并返回
                $processInstance = ProcessInstanceRepository::add($instanceData);
                $processInstance->loadMissing(['tasks','definition']);

                $definition->loadMissing('versions');
                if($enableVer = $definition->versions->firstWhere('status',NProcessDefinitionVersion::STATUS_ENABLE)){
                    //有启用的版本
                    $instanceData['ver'] = $enableVer->ver;

                    //查找发起人节点
                    $firstNode = NProcessNode::query()->where('definition_id',$definitionId)
                        ->where('ver',$enableVer->ver)
                        ->orderBy('id')
                        ->first();

                    //创建任务
                    $taskData = [
                        'name' => $firstNode->name,
                        'node_id' => $firstNode->id,
                        'status' =>NProcessTask::STATUS_APPROVING
                    ];
                    $task = $processInstance->tasks()->create($taskData);

                    $task->assignees()->create([
                        'assignee_id'=>$userId,
                        'assignee'=>$userName,
                    ]);
                }

                return new ProcessInstanceResource($processInstance);
            });
        }else{
            return ('参数丢失或错误!');
        }
    }

    /**
     * 流程启动
     * @param $dataParam
     * @param int $taskId 任务 id
     * @param int $instanceId 流程实例 id
     * @param array $formData 表单数据
     * @return mixed
     */
    public static function startProcess($dataParam = []){
        $taskId = $dataParam['task_id'] ?? null;
        $instanceId = $dataParam['instance_id'] ?? null;
        $formData = $dataParam['form_data'] ?? null;
        $formDataId = $dataParam['form_data_id'] ?? 0;
        return DB::transaction(function () use ($taskId, $instanceId, $formData,$formDataId){
            if(!empty($instanceId) && $instanceId > 0){
                $processInstance = ProcessInstanceRepository::findWithRelations($instanceId);
                if(empty($processInstance)){
                    return ('流程实例不存在!');
                }
                if($processInstance->status != NProcessInstance::STATUS_UNSTARTED){
                    return ('流程实例已启动!');
                }
                $lastTask = $processInstance->tasks->last();
                if(empty($lastTask)){
                    return ('流程任务不存在!');
                }
                //更新状态
                $processInstance->setStart();
                $processInstance->save();

                $lastTask->update([
                    'status'=>NProcessTask::STATUS_APPROVING,
                ]);
                //更新任务表单数据
                $lastTask->assignees()->update([
                    'form_data_id'=>$formDataId,
                    'form_data'=>$formData,
                ]);

                $this->startTaskRedirectJob($lastTask->id);

                return $lastTask;
            }else if(!empty($taskId) && $taskId > 0){
                $task = NProcessTask::query()->with('instance')->find($taskId);
                if(empty($task)){
                    return ('流程任务不存在!');
                }
                if($task->status != NProcessTask::STATUS_APPROVING){
                    return ('流程任务已处理!');
                }
                //更新状态
                $task->instance->setStart();
                $task->instance->save();

                $task->update([
                    'status'=>NProcessTask::STATUS_APPROVING,
                ]);
                //更新任务表单数据
                $task->assignees()->update([
                    'form_data_id'=>$formDataId,
                    'form_data'=>$formData,
                ]);

                $this->startTaskRedirectJob($task->id);

                return $task;
            }else{
                return ('参数丢失或错误!');
            }
        });
    }

    /**
     * 完成任务并推进流程
     * @param $dataParam
     * @param int $taskId 任务 id
     * @param array $formData 表单数据
     * @param int $formDataId 表单数据 id
     * @param bool $promoteDown 是否向下推进,默认 true，审批节点的审批意见是驳回false 还是同意 true
     * @return mixed
     */
    public static function promoteProcess($dataParam = []){
        $taskId = $dataParam['task_id'] ?? null;
        $formData = $dataParam['form_data'] ?? null;
        $formDataId = $dataParam['form_data_id'] ?? 0;
        $promoteDown = $dataParam['promote_down'] ?? true;
        $task = NProcessTask::query()->with('instance')->find($taskId);
        if(empty($task)){
            return ('流程任务不存在!');
        }
        if($task->status != NProcessTask::STATUS_APPROVING){
            return ('流程任务已处理!');
        }

        return DB::transaction(function () use ($task,$formData,$formDataId,$promoteDown){
            if($promoteDown){
                //更新状态
                $task->update([
                    'status'=>NProcessTask::STATUS_COMPLETED,
                ]);

                //更新任务表单数据
                $task->assignees()->update([
                    'form_data_id'=>$formDataId,
                    'form_data'=>$formData,
                ]);

                $this->startTaskRedirectJob($task->id);

                return $task;
            }else{
                //更新状态
                $task->instance->update([
                    'status'=>NProcessInstance::STATUS_REJECTED,
                ]);
                $task->update([
                    'status'=>NProcessTask::STATUS_COMPLETED,
                ]);
                //更新任务表单数据
                $task->assignees()->update([
                    'form_data_id'=>$formDataId,
                    'form_data'=>$formData,
                ]);

                //驳回目前只支持驳回到第一个节点
                $firstTask = $task->instance->tasks->first();
                $newFirstTask = $firstTask->replicate([
                    'created_at','updated_at','deleted_at',
                ]);
                $newFirstTask->fill([
                    'status' =>NProcessTask::STATUS_APPROVING
                ]);
                $newFirstTask->save();

                $orgFirstTaskAssignee = $task->assignees->first();
                $newFirstTask->assignees()->create([
                    'assignee_id'=>$orgFirstTaskAssignee->assignee_id,
                    'assignee'=>$orgFirstTaskAssignee->assignee,
                ]);

                return $newFirstTask;
            }
        });
    }

    /**
     * 添加流程变量
     * @param $processInstanceId
     * @param $variableData
     *    type 变量值类型  string字符串 等
     *    name 变量名
     *    value 变量值
     * @return mixed
     */
    public static function addProcessVariables($processInstanceId =0,$variableData = []){
        $processInstance = ProcessInstanceRepository::find($processInstanceId);
        if(empty($processInstance)){
            return ('流程实例不存在!');
        }
        if(isset($variableData['name'])){
            //表示传的一维
            $variableData['instance_id'] = $processInstanceId;
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
                $item['instance_id'] = $processInstanceId;
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

    /**
     * @param $taskId
     * @return void
     */
    private function startTaskRedirectJob($taskId){
        //开启任务走向job
        $useQueue = config('process_parser.json_parser.use_queue',false);
        if($useQueue){
            $queueName = config('process_parser.json_parser.queue_name');
            if(empty($queueName)){
                $queueName = 'process_parser';
            }
            dispatch(new TaskDirectionJob($taskId))->onQueue($queueName);
        }else{
            dispatch_sync(new TaskDirectionJob($taskId));
        }
    }
}