<?php

namespace Nirunfa\FlowProcessParser\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BaseModel extends Model
{
    public function getTable()
    {
        // 动态添加表前缀
        $prefix = config('process_parser.db.prefix'); // 替换为你的前缀

        // 获取原始表名
        $tableName = parent::getTable();

        // 添加前缀并返回
        return  preg_replace('/^n_/', $prefix, $tableName);
    }
}