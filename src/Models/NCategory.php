<?php

namespace Nirunfa\FlowProcessParser\Models;

use Nirunfa\FlowProcessParser\Models\BaseModel;

class NCategory extends BaseModel
{
    const STATUS_ENABLE = 1;
    const STATUS_DISABLE = 0;

    public function parent(){
        return $this->belongsTo(NCategory::class,'pid');
    }

    public function children(){
        return $this->hasMany(NCategory::class,'pid');
    }

}