<?php

namespace Nirunfa\FlowProcessParser\Events\NodeParsing;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 节点解析事件基类
 */
abstract class NodeParsingEvent
{
    use Dispatchable, SerializesModels;

    public $designId;
    public $ver;
    public $nodeData;

    public function __construct($designId, $ver, $nodeData = null)
    {
        $this->designId = $designId;
        $this->ver = $ver;
        $this->nodeData = $nodeData;
    }
}

