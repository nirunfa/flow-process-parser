<?php

namespace Nirunfa\FlowProcessParser\Observers;

use Nirunfa\FlowProcessParser\Models\NProcessDesign;

class NProcessDesignObserver
{
    public function deleted(NProcessDesign $design){
        //删除设计下的所有版本
        $design->versions()->delete();
        //删除设计下的所有节点
        $design->nodes()->delete();
    }
}