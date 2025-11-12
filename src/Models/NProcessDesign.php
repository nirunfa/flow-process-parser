<?php

namespace Nirunfa\FlowProcessParser\Models;

class NProcessDesign extends BaseModel
{
    const STATUS_ENABLE = 1;
    const STATUS_DISABLE = 0;

    protected $guarded = [];

    public function getTable()
    {
        $table = getParserConfig('process_parser.db.tables.design','');
        if(!empty($table)){
            return $table;
        }
        return parent::getTable();
    }

    public function nodes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NProcessNode::class);
    }

    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NCategory::class);
    }

    public function group(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NGroup::class);
    }

    public function versions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NProcessDesignVersion::class,'design_id');
    }
}
