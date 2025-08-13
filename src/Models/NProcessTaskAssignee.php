<?php

namespace Nirunfa\FlowProcessParser\Models;

class NProcessTaskAssignee extends BaseModel
{
    protected $guarded = [];

    public function getTable()
    {
        $table = config('process_parser.db.tables.task_assignee','');
        if(!empty($table)){
            return $table;
        }
        return parent::getTable();
    }

    public function task(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NProcessTask::class);
    }
    public function nodeApprover(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NProcessNodeApprover::class,'node_approver_id','uuid');
    }

    public function assignee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(config('process_parser.models.user',''),'assignee_id');
    }
}