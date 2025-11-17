<?php

namespace Nirunfa\FlowProcessParser\Models;

use Nirunfa\FlowProcessParser\Models\BaseModel;

class NProcessDesignVersion extends BaseModel
{
    protected $guarded = [];

    public $timestamps = false;

    const STATUS_ENABLE = 1;
    const STATUS_DISABLE = 0;

    public function addVersion(){
        return $this->ver + 1;
    }

    /**
     * 获取版本对应的节点
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function nodes(){
        return $this->hasMany(NProcessNode::class,'design_id','design_id')->where('ver',$this->ver);
    }
}
