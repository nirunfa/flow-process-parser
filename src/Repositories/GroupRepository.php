<?php

namespace Nirunfa\FlowProcessParser\Repositories;

use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Nirunfa\FlowProcessParser\Models\NGroup;

class GroupRepository extends BaseRepository
{

    public static function getList($perPage, $condition = [], $keyword = null)
    {
        return NGroup::query()
            ->where(function ($query) use ($condition, $keyword) {
                self::buildQuery($query, $condition);
                if (! empty ($keyword))
                {
                    self::buildQuery($query, ['name' => ['operator' => 'LIKE', 'value' => '%' . $keyword . '%']]);
                }
            })
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public static function add($data)
    {
        return NGroup::query()->create($data);
    }

    public static function update($id, $data)
    {
        return NGroup::find($id)->update($data);
    }

    public static function find($id)
    {
        return NGroup::query()->find($id);
    }

    public static function delete($id)
    {
        return NGroup::destroy($id);
    }

    public static function select ($keyword = null)
    {
        return NGroup::query()
            ->select(['id', 'name'])
            ->where(function ($query) use ($keyword) {
               if (! empty ($keyword))
                {
                    $query->where('name', 'like', '%' . $keyword . '%');
                }
            })
            ->orderBy('order_sort', 'desc')
            ->get()
            ->map(function (NGroup $item) {
                $map = $item->toArray();
                $map['value'] = $item->id;
                $map['text'] = $item->name;
                $map['label'] = $item->name;
                return $map;
            });
    }

    public static function selectTree()
    {
        return NGroup::select('id', 'name', 'order_sort')
            ->get()
            ->map(function (NGroup $model) {
                $data = $model->toArray();
                return $data;
            })->sortBy('order_sort');

    }
}

