<?php

namespace Nirunfa\FlowProcessParser;

use Illuminate\Config\Repository;
use Nirunfa\FlowProcessParser\Interfaces\ProcessParserConfigInterface;

class ProcessParser implements ProcessParserConfigInterface
{
    protected $config;
    /**
     * 构造方法
     */
    public function __construct(Repository $config)
    {
        $this->config = $config->get('process_parser');
    }
    
    /**
     * 获取流程解析器配置
     * 
     * @param mixed $key
     * @param mixed $default
     * @return mixed
     */
    public function getConfig($key,$default = null){
        return $this->config[$key] ?? $default;
    }

    /**
     * 设置流程解析器配置
     * 
     * @param mixed $key
     * @param mixed $value
     * @return void
     */ 
    public function setConfig($key,$value){
        $this->config[$key] = $value;
        return $this;
    }
}