<?php

namespace Nirunfa\FlowProcessParser\Models;

use Nirunfa\FlowProcessParser\Models\BaseModel;

class NProcessTask extends BaseModel
{
    protected $guarded = [];

    //流程状态0待分配、1待审批、2已完成
    const STATUS_UNASSIGNED = 0;
    const STATUS_APPROVING = 1;
    const STATUS_COMPLETED = 2;

    public function getTable()
    {
        $table = getParserConfig('process_parser.db.tables.task','');
        if(!empty($table)){
            return $table;
        }
        return parent::getTable();
    }

    public function instance(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NProcessInstance::class,'instance_id');
    }

    public function assignees(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NProcessTaskAssignee::class,'task_id','id');
    }

    public function node(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NProcessNode::class,'node_id');
    }

    public function variables(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NProcessVariable::class,'task_id','id');
    }

    public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NProcessComment::class,'task_id','id');
    }

}
