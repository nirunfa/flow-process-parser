<?php

namespace Nirunfa\FlowProcessParser\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Nirunfa\FlowProcessParser\Models\NCategory;

class CategoryRepository extends  BaseRepository
{

    public static function getList($perPage, $condition = [], $keyword = null)
    {
        return NCategory::query()
            ->with (['parent'])
            ->where(function (Builder $query) use (&$condition, $keyword) {
                if (Arr::has($condition, 'category')){
                    $category = Arr::get($condition, 'category', []);
                    if(!empty($category)){
                        $query->whereIn('id', $category);
                    }
                    unset($condition['category']);
                }
                if (! empty ($keyword))
                {
                    $query->where('name','like', "%{$keyword}%");
                }
            })
            ->orderBy('order_sort', 'desc')
            ->paginate($perPage);
    }


    public static function categoryTree($conditions = []){

        return self::tree(
            NCategory::query()
                ->where(function ($query) use ($conditions) {
                    self::buildQuery($query, $conditions);
                })
                ->orderBy('order_sort', 'desc')
                ->get()
        );

    }

    public static function select ($condition, $keyword = null)
    {
        return NCategory::query()
            ->select(['id', 'name'])
            ->where(function ($query) use ($condition, $keyword) {
                self::buildQuery($query, $condition);
                if (! empty ($keyword))
                {
                    $query->where('name', 'like', '%' . $keyword . '%');
                }
            })
            ->orderBy('order_sort', 'desc')
            ->get()
            ->map(function (NCategory $item) {
                $map = $item->toArray();
                $map['value'] = $item->id;
                $map['text'] = $item->name;
                $map['label'] = $item->name;
                return $map;
            });
    }
    public static function all($condition = []){
        return NCategory::query()
            ->where(function ($query) use ($condition) {
                self::buildQuery($query, $condition);
            })
            ->orderBy('order_sort', 'desc')
            ->get();
    }

    public static function add ($data)
    {
        return NCategory::query ()->create ($data);
    }

    public static function update ($id, $data)
    {
        return NCategory::find ($id)->update ($data);
    }

    public static function find ($id)
    {
        return NCategory::query ()->find ($id);
    }

    public static function delete ($id)
    {
        return NCategory::destroy ($id);
    }

    public static function selectTree ($pid = 0)
    {
        return NCategory::select('id', 'pid', 'name', 'order_sort')
            ->with ('children')
            ->where('pid', $pid)
            ->orderBy('order_sort','desc')
            ->get ()
            ->reduce(function ($arr, NCategory $model){
                $data = $model->toArray ();
                $data ['isLeaf'] = ($model->children->count () === 0);
                unset($data['children']);
                $arr[] = $data;
                return $arr;
            }, \collect([]));

    }
}

