<?php

namespace Nirunfa\FlowProcessParser\Contracts;

/**
 * JsonNodeParserJob 接口
 * 允许外部项目实现自定义的节点解析逻辑
 */
interface JsonNodeParserJobInterface
{
    /**
     * 处理节点解析
     * 
     * @param int $designId 设计ID
     * @param int $ver 版本号
     * @return void
     */
    public function handle();
}

