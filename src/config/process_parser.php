<?php

/**
 *  ---------------流程解析器配置---------------
 *
 *
 */
return [
    /**
     * 数据库相关配置
     */
    'db' =>[
        'prefix'=>'fpp_', //表前缀
        /**
         * 自定义表名设置地方，若需要使用非拓展自带的表则这里填写
         */
        'tables'=>[
            'group'=>'',
            'category'=>'',
            'form'=>'',
            'design'=>'',
            'instance'=>'',
            'task'=>'',
            'task_assignee'=>'',
        ],
    ],
    /**
     * 模型相关配置
     */
    'models'=>[
        'user'=>null,
        'form'=>null,
    ],
    /**
     * 流程版本起始号，整数
     */
    'start_ver'=> 0,
    /**
     * 流程设计 json解析配置
     */
    'json_parser'=>[
        'use_queue'=>false,/*是否使用异步队列,默认不启用*/
        'queue_name'=>'process_parser',/* 异步队列所在组，对应 ->onQueue()方法的参数； 不填写或者为空则为拓展默认的 */
        
        /**
         * 自定义 Job 类绑定
         * 设置为 null 使用默认的 JsonNodeParserJob
         * 设置为自定义类名（需实现 JsonNodeParserJobInterface 接口）
         * 例如：'custom_job' => \App\Jobs\CustomJsonNodeParserJob::class
         */
        'custom_job' => null,
        
        /**
         * 自定义 TaskDirectionJob 类绑定
         * 设置为 null 使用默认的 TaskDirectionJob
         * 设置为自定义类名（需实现 TaskDirectionJobInterface 接口）
         * 例如：'custom_task_direction_job' => \App\Jobs\CustomTaskDirectionJob::class
         */
        'custom_task_direction_job' => null,
    ],
    /*
     * 路由相关配置
     * */
    'route'=>[
        'prefix'=>'/api',//初始有一个前缀 n_process, 若设置 prefix，则前缀为 设置值 + /n_process, 默认值为 /api
        'middleware'=>[],
    ],
];
