<?php
namespace Nirunfa\FlowProcessParser\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Nirunfa\FlowProcessParser\Models\NProcessDesign;

class ProcessDesignRepository extends BaseRepository
{
    protected static function buildQueryObj()
    {
        return NProcessDesign::query();
    }

    public static function getList($perPage, $condition = [], $keyword = null)
    {
        return static::buildQueryObj()
            ->with(["category", "group"])
            ->where(function (Builder $query) use (&$condition, $keyword) {
                if (Arr::has($condition, "category")) {
                    $category = Arr::get($condition, "category", []);
                    if (!empty($category)) {
                        $query->whereIn("id", $category);
                    }
                    unset($condition["category"]);
                }
            })
            ->where(function ($query) use ($condition, $keyword) {
                self::buildQuery($query, $condition);
                if (!empty($keyword)) {
                    self::buildQuery($query, ["name" => $keyword]);
                }
            })
            ->orderBy("id", "desc")
            ->paginate($perPage);
    }

    public static function add($data)
    {
        return static::buildQueryObj()->create($data);
    }

    public static function update($id, $data)
    {
        return static::buildQueryObj()->find($id)->update($data);
    }

    public static function find($id)
    {
        return static::buildQueryObj()->find($id);
    }

    public static function findWithRelations($id)
    {
        return static::buildQueryObj()
            ->with(["category", "group", "versions"])
            ->find($id);
    }

    public static function delete($id)
    {
        return NProcessDesign::destroy($id);
    }

    public static function select($keyword = null)
    {
        return static::buildQueryObj()
            ->select(["id", "name"])
            ->where(function ($query) use ($keyword) {
                if (!empty($keyword)) {
                    $query->where("name", "like", "%" . $keyword . "%");
                }
            })
            ->orderBy("id", "desc")
            ->get()
            ->map(function (NProcessDesign $item) {
                $map = $item->toArray();
                $map["value"] = $item->id;
                $map["text"] = $item->name;
                $map["label"] = $item->name;
                return $map;
            });
    }

    public static function selectTree()
    {
        return static::buildQueryObj()
            ->select("id", "name", "order_sort")
            ->get()
            ->map(function (NProcessDesign $model) {
                $data = $model->toArray();
                return $data;
            })
            ->sortBy("order_sort", "desc");
    }
}
