<?php

namespace Nirunfa\FlowProcessParser\Models;

class NProcessInstance extends BaseModel
{
    protected $guarded = [];

    //状态，0未启动、1进行中、2已完成、3撤回、4废弃、5驳回
    const STATUS_UNSTARTED = 0;
    const STATUS_PROCESSING = 1;
    const STATUS_COMPLETED = 2;
    const STATUS_REVOKED = 3;
    const STATUS_ABANDONED = 4;
    const STATUS_REJECTED = 5;

    //是否存档
    const IS_ARCHIVE_NO = 0;
    const IS_ARCHIVE_YES = 1;

    protected $casts = [
        'start_time'=>'datetime',
        'end_time'=>'datetime',
    ];

    public function getTable()
    {
        $table = config('process_parser.db.tables.instance','');
        if(!empty($table)){
            return $table;
        }
        return parent::getTable();
    }

    public function tasks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NProcessTask::class);
    }

    public function definition(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NProcessDefinition::class);
    }

    public function applier(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(config('process_parser.models.user',''),'initiator_id');
    }

    public function variables(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NProcessVariable::class);
    }

    public function setStart(): NProcessInstance
    {
        $this->status = self::STATUS_PROCESSING;
        $this->start_time = now();
        return $this;
    }
    public function setComplete(): NProcessInstance
    {
        $this->status = self::STATUS_COMPLETED;
        $this->end_time = now();
        $this->duration = $this->end_time->diffInSeconds($this->start_time);
        $this->setArchived();
        return $this;
    }

    public function setArchived(): NProcessInstance
    {
        $this->is_archived = self::IS_ARCHIVE_YES;
        return $this;
    }
}