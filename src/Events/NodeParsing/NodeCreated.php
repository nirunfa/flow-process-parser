<?php

namespace Nirunfa\FlowProcessParser\Events\NodeParsing;

/**
 * 节点创建后事件
 */
class NodeCreated extends NodeParsingEvent
{
    public $processNode;

    public function __construct($designId, $ver, $processNode)
    {
        parent::__construct($designId, $ver);
        $this->processNode = $processNode;
    }
}

