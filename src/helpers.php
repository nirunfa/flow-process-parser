<?php

use Nirunfa\FlowProcessParser\Contracts\JsonNodeParserJobInterface;
use Nirunfa\FlowProcessParser\Contracts\TaskDirectionJobInterface;
use Nirunfa\FlowProcessParser\Interfaces\ProcessParserConfigInterface;

if(!function_exists('getCode')){
    function getCode(){
        return 'NF'.date('YmdHis').rand(10000,99999);
    }
}

if(!function_exists('createJsonNodeParserJob')){
    /**
     * 创建 JsonNodeParserJob 实例
     * 支持通过配置自定义 Job 类
     * 
     * @param int $designId 设计ID
     * @param int $ver 版本号
     * @param bool $isPublish 是否发布
     * @return JsonNodeParserJobInterface|\Nirunfa\FlowProcessParser\Jobs\JsonNodeParserJob
     */
    function createJsonNodeParserJob($designId, $ver,$isPublish = false){
        $customJob = getParserConfig('process_parser.json_parser.custom_job');
        if ($customJob && is_string($customJob) && class_exists($customJob)) {
            // 检查是否实现了接口
            $reflection = new \ReflectionClass($customJob);
            if ($reflection->implementsInterface(JsonNodeParserJobInterface::class)) {
                /** @var JsonNodeParserJobInterface $instance */
                $instance = new $customJob($designId, $ver,$isPublish);
                return $instance;
            }
        }
        return new \Nirunfa\FlowProcessParser\Jobs\JsonNodeParserJob($designId, $ver,$isPublish);
    }
}

if(!function_exists('createTaskDirectionJob')){
    /**
     * 创建 TaskDirectionJob 实例
     * 支持通过配置自定义 Job 类
     * 
     * @param int $taskId 任务ID
     * @return TaskDirectionJobInterface|\Nirunfa\FlowProcessParser\Jobs\TaskDirectionJob
     */
    function createTaskDirectionJob($taskId){
        $customJob = getParserConfig('process_parser.json_parser.custom_task_direction_job');
        if ($customJob && is_string($customJob) && class_exists($customJob)) {
            // 检查是否实现了接口
            $reflection = new \ReflectionClass($customJob);
            if ($reflection->implementsInterface(TaskDirectionJobInterface::class)) {
                /** @var TaskDirectionJobInterface $instance */
                $instance = new $customJob($taskId);
                return $instance;
            }
        }
        return new \Nirunfa\FlowProcessParser\Jobs\TaskDirectionJob($taskId);
    }
}


if(!function_exists('getParserConfig')){
    /**
     * 获取流程解析器配置
     * 
     * @param mixed $key
     * @param mixed $default
     * @return Illuminate\Config\Repository|mixed
     */
    function getParserConfig($key,$default = null){
        return app(ProcessParserConfigInterface::class)->getConfig($key,$default);
    }
}