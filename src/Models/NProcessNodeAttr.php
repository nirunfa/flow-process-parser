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
//    {label:'直接流程结束（不论真假）',value:'direct_finish'}
//    {label:'为真时向下跳过一个节点',value:'true_down_skip'},
//    {label:'为假时向下跳过一个节点',value:'false_down_skip'},
    const TRUE_DOWN = 'true_down';
    const FALSE_DOWN = 'false_down';
    const TRUE_FINISH = 'true_finish';
    const FALSE_FINISH = 'false_finish';
    const DIRECT_DOWN = 'direct_down';
    const DIRECT_FINISH = 'direct_finish';
    const TRUE_DOWN_SKIP = 'true_down_skip';
    const FALSE_DOWN_SKIP = 'false_down_skip';

    /*
             { label: '规则', value: 1 },
             { label: '公式', value: 2 },
            */
    const RULE = 1;
    const FORMULA = 2;

    /*审批方式 */
    const ORDER_SEQUENCE = 1; //依次审批(一人通过再到下一个人处理)
    const COUNTERSIGN_ALL_PASS = 2; //多人会签(所有人都通过才到下一个环节)
    const COUNTERSIGN_ALL_FAIL = 3; //多人会签(通过只需一人,否决需全员)
    const COUNTERSIGN_ONE = 4; //多人或签(一人通过或否决)
    const ORDER_MATCH = 5; //匹配审批(顺序依次检索审批人设置,匹配到人停止检索)

    /*审批类型 */
    const PEOPLE = 1; //人工审核
    const AUTO_PASS = 2; //自动通过
    const AUTO_FAIL = 3; //自动否决

    public function belongNode(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NprocessNode::class, 'node_id');
    }

    public function isAutoPass(): bool
    {
        return $this->approve_type === self::AUTO_PASS;
    }

    public function isAutoFail(): bool
    {
        return $this->approve_type === self::AUTO_FAIL;
    }

    public function isPeople(): bool
    {
        return $this->approve_type === self::PEOPLE;
    }
}
