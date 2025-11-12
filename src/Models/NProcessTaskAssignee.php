<?php

namespace Nirunfa\FlowProcessParser\Models;

class NProcessTaskAssignee extends BaseModel
{
    protected $guarded = [];

    public $timestamps = false;

    public function getTable()
    {
        $table = getParserConfig('process_parser.db.tables.task_assignee','');
        if(!empty($table)){
            return $table;
        }
        return parent::getTable();
    }

    public function task(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NProcessTask::class,'task_id');
    }
    public function nodeApprover(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NProcessNodeApprover::class,'node_approver_id','id');
    }

    public function assignee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(getParserConfig('process_parser.models.user',''),'assignee_id');
    }
}
