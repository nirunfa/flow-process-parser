<?php

namespace Nirunfa\FlowProcessParser\Models;

use Nirunfa\FlowProcessParser\Models\BaseModel;

class NProcessNode extends BaseModel
{
    protected $guarded = [];

//    * type: 0发起人节点 1审批人节点 2抄送结点 3条件节点 4分支节点 5事件节点 6处理人节点
//    * 7意见分支节点  8意见分支节点中各分支
//    * 9并行节点 10并行节点中各分支 11并行节点后的聚合节点
//    * 12通知节点
    const TYPE_INITIATOR = 0;
    const TYPE_APPROVER = 1;
    const TYPE_CC = 2;
    const TYPE_CONDITION = 3;
    const TYPE_BRANCH = 4;
    const TYPE_EVENT = 5;
    const TYPE_ASSIGNEE = 6;
    const TYPE_OPINION_BRANCH = 7;
    const TYPE_OPINION_BRANCH_NODE = 8;
    const TYPE_PARALLEL = 9;
    const TYPE_PARALLEL_NODE = 10;
    const TYPE_PARALLEL_FINISH = 11;
    const TYPE_NOTIFY = 12;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function configs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NProcessConfig::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function form(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(NProcessForm::class, 'id', 'form_id');
    }

    public function approvers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NProcessNodeApprover::class,'node_id')->orderBy('order_sort');
    }

    public function conditions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NProcessNodeCondition::class,'node_id');
    }

    public function attr(){
        return $this->hasOne(NProcessNodeAttr::class,'node_id');
    }

    public function definition(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NProcessDefinition::class,'node_id');
    }

    public function nextNodes(): \Illuminate\Database\Eloquent\Relations\hasMany
    {
        return $this->hasMany(NProcessNode::class, 'prev_node_id', 'id');
    }
}