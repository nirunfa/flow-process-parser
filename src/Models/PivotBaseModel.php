<?php

namespace Nirunfa\FlowProcessParser\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class PivotBaseModel extends Pivot
{
    public function getTable(): array|string|null
    {
        // 动态添加表前缀
        $prefix = getParserConfig('process_parser.db.prefix'); // 替换为你的前缀

        // 获取原始表名
        $tableName = parent::getTable();

        // 添加前缀并返回
        return  preg_replace('/^n_/', $prefix, $tableName);
    }
}
