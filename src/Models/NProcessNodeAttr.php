<?php

namespace Nirunfa\FlowProcessParser\Models;

use Nirunfa\FlowProcessParser\Models\BaseModel;

class NProcessNodeAttr extends BaseModel
{
    protected $guarded = [];

//    {label:'为真时向下',value:'true_down'},
//    {label:'为假时向下',value:'false_down'},
//    {label:'为真时流程结束',value:'true_finish'},
//    {label:'为假时流程结束',value:'false_finish'},
//    {label:'直接向下（不论真假）',value:'direct_down'},
//    {label:'直接流程结束（不论真假）',value:'direct_finish'},
    const TRUE_DOWN = 'true_down';
    const FALSE_DOWN = 'false_down';
    const TRUE_FINISH = 'true_finish';
    const FALSE_FINISH = 'false_finish';
    const DIRECT_DOWN = 'direct_down';
    const DIRECT_FINISH = 'direct_finish';

    /*
             { label: '规则', value: 1 },
             { label: '公式', value: 2 },
            */
    const RULE = 1;
    const FORMULA = 2;

    public function belongNode(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NprocessNode::class, 'node_id');
    }
}