<?php

namespace Nirunfa\FlowProcessParser\Models;

use Nirunfa\FlowProcessParser\Models\BaseModel;

class NProcessVariable extends BaseModel
{
    protected $guarded = [];

    public function processInstance(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NProcessInstance::class);
    }
    public function task(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NProcessTask::class);
    }
}