<?php

namespace Nirunfa\FlowProcessParser\Repositories;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpParser\Builder;

class BaseRepository
{

    const TRANSFORM_TYPE_JSON                       = 'json';

    protected static function transform ($type, array &$array, $field)
    {
        if (! empty (Arr::get ($array, $field, '')))
        {
            switch ($type)
            {
                case self::TRANSFORM_TYPE_JSON :
                    Arr::set ($array, $field, json_decode (Arr::get ($array, $field), true));
                    break;
            }
        }
    }

    public static function generateCode($prefix,$modelClass)
    {
        do{
            $code = "{$prefix}-".date('YmdHis').'-'.Str::upper(Str::random(6));
        }while($modelClass::where('code', $code)->exists());
        return $code;
    }

    public static function tree(Collection $data, $pid = 0, $level = 0, $path = [])
    {
        if(isset($data)){
            return $data->where('pid', $pid)
                ->map(function ($model) use ($data, $level, $path) {
                    $mapped = array_merge(is_array($model) ? $model : $model->toArray(), [
                        'id' => $model['id'],
                        'name' => $model['name'],
                        'level' => $model['level'] ?? null,
                        'pid' => $model['pid'],
                        'path' => $model['path'] ?? null
                    ]);

                    $child = $data->where('pid', $mapped['id']);
                    if ($child->isEmpty()) {
                        return $mapped;
                    }

                    array_push($path, $mapped['id']);
                    $mapped['children'] = self::tree($data, $model['id'], $level + 1, $path)->values()->all();
                    return $mapped;
                })
                ->values();
        }
        return \collect([]);
    }

    public static function buildQuery(Builder $query, array $condition){
        if(!empty($conditions)){
            foreach ($conditions as $key => $value){
                $query->where($key,$value['operator'] ?? '=' ,$value['value'] ?? $value);
            }
        }
    }

}
