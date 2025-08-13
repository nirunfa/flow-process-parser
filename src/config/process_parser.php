<?php
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
            'definition'=>'',
            'instance'=>'',
            'task'=>'',
            'task_assignee'=>'',
        ],
    ],
    /**
     * 模型相关配置
     */
    'models'=>[
        'user'=>''
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
    ]
];