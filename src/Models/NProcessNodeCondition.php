<?php

namespace Nirunfa\FlowProcessParser\Models;

use Nirunfa\FlowProcessParser\Models\BaseModel;

class NProcessNodeCondition extends BaseModel
{
    protected $guarded = [];

    const VALUE_TYPE_VARIABLE = 1;
    const VALUE_TYPE_CONSTANT = 2; //数据选项 目前默认只有 3 个值  真、假、2(法定假日加班)
}
