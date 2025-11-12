<?php

namespace Nirunfa\FlowProcessParser\Models;

use Nirunfa\FlowProcessParser\Models\BaseModel;

class NGroup extends BaseModel
{
    const STATUS_ENABLE = 1;
    const STATUS_DISABLE = 0;

    public function getTable()
    {
        $table = getParserConfig('process_parser.db.tables.group','');
        if(!empty($table)){
            return $table;
        }
        return parent::getTable();
    }
}