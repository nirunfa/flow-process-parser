<?php

namespace Nirunfa\FlowProcessParser\Events\NodeParsing;

/**
 * 节点创建前事件
 */
class NodeCreating extends NodeParsingEvent
{
    public $orgNodeData;
    public $initNode;

    public function __construct($designId, $ver, $orgNodeData, $initNode)
    {
        parent::__construct($designId, $ver, $orgNodeData);
        $this->orgNodeData = $orgNodeData;
        $this->initNode = $initNode;
    }
}

