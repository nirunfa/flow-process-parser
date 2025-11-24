<?php

namespace Nirunfa\FlowProcessParser\Models;

class NProcessConfig extends BaseModel
{
    protected $guarded = [];

    const CONFIG_VARS = [
        'creator' => '发起人是否流程创建者',
        'turn' => '转交给他人办理，是否依然在当前节点',
        'cc' => '是否允许选择抄送给谁，可以在待阅和已阅中查看',
        'back' => '是否允许退回给申请人（申请人修改完成后，流程按节点开始走）',
    ];
}