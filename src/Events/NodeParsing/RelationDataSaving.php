<?php

namespace Nirunfa\FlowProcessParser\Events\NodeParsing;

/**
 * 关联数据保存前事件
 */
class RelationDataSaving extends NodeParsingEvent
{
    public $relationDataQueue;

    public function __construct($designId, $ver, $relationDataQueue)
    {
        parent::__construct($designId, $ver);
        $this->relationDataQueue = $relationDataQueue;
    }
}

