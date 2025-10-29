<?php

namespace Nirunfa\FlowProcessParser\Models;

use Nirunfa\FlowProcessParser\Models\BaseModel;

class NProcessVariable extends BaseModel
{
    protected $guarded = [];

    public function getRealValueAttribute()
    {
        if($this->type === 'boolean'){
            if($this->value === 'true' || $this->value === '1'){
                return true;
            }else if($this->value === 'false' || $this->value === '0'){
                return false;
            }
            return false;
        }else if($this->type === 'integer'){
            return intval($this->value);
        }else if($this->type === 'double'){
            return doubleval($this->value);
        }else if($this->type === 'float'){
            return floatval($this->value);
        }else if($this->type === 'array'){
            return json_decode($this->value,true);
        }else if($this->type === 'object'){
            return json_decode($this->value,true);
        }
        return $this->value;
    }

    public function processInstance(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NProcessInstance::class,'instance_id');
    }
    public function task(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NProcessTask::class,'task_id');
    }

}
