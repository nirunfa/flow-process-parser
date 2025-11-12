<?php

namespace Nirunfa\FlowProcessParser\Services;

use Illuminate\Support\Facades\DB;
use Nirunfa\FlowProcessParser\Models\NProcessDesignVersion;
use Nirunfa\FlowProcessParser\Models\NProcessInstance;
use Nirunfa\FlowProcessParser\Models\NProcessNode;
use Nirunfa\FlowProcessParser\Models\NProcessTask;
use Nirunfa\FlowProcessParser\Models\NProcessVariable;
use Nirunfa\FlowProcessParser\Repositories\ProcessDesignRepository;
use Nirunfa\FlowProcessParser\Repositories\ProcessInstanceRepository;
use Nirunfa\FlowProcessParser\Resources\ProcessInstanceResource;
use Nirunfa\FlowProcessParser\Traits\CommonTrait;

/**
 * 流程服务类
 */
class FlowInstanceService
{
    use CommonTrait;
    /**
     * 创建一个流程
     * @param array{
     *     designId: int,
     *     userId: int,
     *     userName: string,
     *     title: string,
     *     code: string,
     * } $dataParam 用户数据数组，需包含以下键：
     *               - designId 流程模型 id
     *               - userId 发起人 id
     *               - userName 发起人名称或昵称等字符串信息
     *               - title 流程标题,不传默认和流程模型的名称
     *               - code 流程单号
     * @return string | array
     *
     */
    public static function createProcess($dataParam = []){
        $designId = $dataParam['design_id'] ?? null;
        $userId = $dataParam['user_id'] ?? null;//发起人 id
        $userName = $dataParam['user_name'] ?? null;//发起人名称或昵称等字符串信息
        $title = $dataParam['title'] ?? null;//流程标题
        $code = $dataParam['code'] ?? getCode();//流程单号
        if(!empty($designId) && $designId > 0 && !empty($userId) && $userId > 0){
            $design = ProcessDesignRepository::find($designId);
            if(empty($design)){
                return ('流程模型不存在!');
            }

            return DB::transaction(function () use ($designId, $design, $userId, $title, $code,$userName){
                $instanceData = [
                    'title'=>$title ?? $design->name,
                    'design_id'=>$designId,
                    'code'=>$code,
                    'initiator_id'=>$userId,
                    'initiator'=>$userName,
                    'status'=>NProcessInstance::STATUS_UNSTARTED,
                    'is_archived'=>NProcessInstance::IS_ARCHIVE_NO,
                    'ver'=>0,
                ];

                $design->loadMissing('versions');
                if($enableVer = $design->versions->firstWhere('status',NProcessDesignVersion::STATUS_ENABLE)){

                    //有启用的版本
                    $instanceData['ver'] = $enableVer->ver;

                    //创建流程并返回
                    $processInstance = ProcessInstanceRepository::add($instanceData);
                    $processInstance->loadMissing(['tasks','design']);

                    //查找发起人节点
                    $firstNode = NProcessNode::query()->where('design_id',$designId)
                        ->where('ver',$enableVer->ver)
                        ->orderBy('id')
                        ->first();

                    //创建任务
                    $taskData = [
                        'name' => $firstNode->name,
                        'node_id' => $firstNode->id,
                        'status' =>NProcessTask::STATUS_UNASSIGNED
                    ];
                    $task = $processInstance->tasks()->create($taskData);

                    $task->assignees()->create([
                        'assignee_id'=>$userId,
                        'assignee'=>$userName,
                    ]);
                    return (new ProcessInstanceResource($processInstance))->toArray(null);
                }else{
                    return '流程模型没有可用的版本';
                }
            });
        }else{
            return ('参数丢失或错误!');
        }
    }

    /**
     * 流程启动
     * @param array{
     *     task_id: int,
     *     instance_id: int,
     *     form_data: array,
     *     form_data_id: int,
     * } $dataParam 用户数据数组，需包含以下键：
     *                -  $taskId 任务 id
     *                -  $instanceId 流程实例 id
     *                -  $formData 表单数据
     * @return mixed
     */
    public static function startProcess($dataParam = []){
        $taskId = str_replace('process_parser_','',$dataParam['task_id'] ?? null);
        $instanceId = str_replace('process_parser_','',$dataParam['instance_id'] ?? null);
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

                return $lastTask->toArray();
            }else if(!empty($taskId) && $taskId > 0){
                $task = NProcessTask::query()->with('instance')->find($taskId);
                if(empty($task)){
                    return ('流程任务不存在!');
                }
                if($task->status === NProcessTask::STATUS_COMPLETED){
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

                return (self::taskPromote($task,$formData,$formDataId,'pass'))->toArray();
            }else{
                return ('参数丢失或错误!');
            }
        });
    }

    /**
     * 完成任务并推进流程
     * @param array{
     *     task_id: int,
     *     form_data: array,
     *     form_data_id: int,
     *     promote_down: bool
     * } $dataParam 用户数据数组，需包含以下键：
     *                - taskId 任务 id
     *                - formData 表单数据
     *                - formDataId 表单数据 id
     *                - promoteDown 是否向下推进,默认 true，审批节点的审批意见是驳回false 还是同意 true
     * @return array | string
     */
    public static function promoteProcess($dataParam = []){
        $taskId = str_replace('process_parser_','',$dataParam['task_id'] ?? null);
        $formData = $dataParam['form_data'] ?? null;
        $formDataId = $dataParam['form_data_id'] ?? 0;
        $promoteDown = $dataParam['promote_down'] ?? true;
        $task = NProcessTask::query()->with('instance')->find($taskId);
        if(empty($task)){
            return ('流程任务不存在!');
        }
        if($task->status === NProcessTask::STATUS_COMPLETED){
            return ('流程任务已处理!');
        }

        return DB::transaction(function () use ($task,$formData,$formDataId,$promoteDown){
            return (self::taskPromote($task,$formData,$formDataId,$promoteDown?'pass':'reject'))->toArray();
        });
    }

    /**
     * 添加或修改流程变量
     * @param $processInstanceId 流程Id
     * @param array{
     *     type: string,
     *     name: string,
     *     value: mixed,
     * } $variableData 变量数据参数:
     *                - type 变量值类型 (string字符串 等)
     *                - name 变量名
     *                - value 变量值
     * @return array | string
     */
    public static function addProcessVariables($processInstanceId =0,$variableData = []){
        $processInstanceId = str_replace('process_parser_','',$processInstanceId);
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
            $variable = NProcessVariable::query()
                            ->updateOrCreate(['name'=>$variableData['name'],'instance_id'=>$processInstanceId],$variableData);
            return [$variable];
        }else if(count($variableData) > 0){
            $variables = [];
            foreach ($variableData as $item){
                if(!isset($item['type'])){
                    $item['type'] = gettype($item['value']);
                }
                $item['instance_id'] = $processInstanceId;
                if($item['type'] === 'array'){
                    $item['value'] = json_encode($item['value']);
                }
                $variables[] = NProcessVariable::query()
                                ->updateOrCreate(['name'=>$item['name'],'instance_id'=>$processInstanceId],$item);
            }
            if(count($variables) > 0){
                return $variables;
            }
            return '变量添加失败';
        }else{
            return '数据为空!';
        }
    }

    /**
     * 获取流程实例
     * @param $processInstanceId 流程实例ID
     * @return array|string
     */
    public static function getProcessInstance($processInstanceId){
        $processInstanceId = str_replace('process_parser_','',$processInstanceId);
        $processInstance = ProcessInstanceRepository::find($processInstanceId);
        if(empty($processInstance)){
            return '流程实例不存在!';
        }

        $processInstance->loadMissing(['variables']);

        return [
            'id' => $processInstance->id,
            'title' => $processInstance->title,
            'name' => $processInstance->name,
            'code' => $processInstance->code,
            'description' => $processInstance->description,
            'completed' => $processInstance->isCompleted(),
            'start_time' => $processInstance->start_time,
            'end_time' => $processInstance->end_time,
            'duration' => $processInstance->duration,
            'variables' => $processInstance->variables->toArray()
        ];
    }

    /**
     * 获取流程实例变量
     * @param $processInstanceId 流程实例ID
     * @param ?string $name names 变量名称
     * @return array|string
     */
    public static function getProcessInstanceVariables($processInstanceId,$name){
        $processInstanceId = str_replace('process_parser_','',$processInstanceId);
        $processInstance = ProcessInstanceRepository::find($processInstanceId);
        if(empty($processInstance)){
            return '流程实例不存在!';
        }

        $processInstance->loadMissing(['variables']);
        if($name){
            return $processInstance->variables->where('name',$name)->first()->toArray();
        }
        return $processInstance->variables->toArray();
    }

    /**
     * 销毁流程实例
     * @param int $processInstanceId 流程实例ID
     * @param string|null $reason 销毁原因
     * @return bool|string
     */
    public static function destroyProcessInstance(int $processInstanceId,string $reason = null){
        $processInstanceId = str_replace('process_parser_','',$processInstanceId);
        if(!empty($processInstanceId) && $processInstanceId > 0){
            $processInstance = ProcessInstanceRepository::findWithRelations($processInstanceId);
            if(empty($processInstance)){
                return ('流程实例不存在!');
            }

            if(!$processInstance->isCanRemove()){
                return ('流程实例已经处理!');
            }

            $res = $processInstance->setAbandoned()->save();
            if($res){
                $processInstance->comments()->create([
                    'content' => $reason,
                ]);
                return true;
            }
            return '没有数据被删除';
        }

        return '参数错误';
    }
}
