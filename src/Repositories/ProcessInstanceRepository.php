<?php
namespace Nirunfa\FlowProcessParser\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Nirunfa\FlowProcessParser\Models\NProcessDesign;
use Nirunfa\FlowProcessParser\Models\NProcessInstance;

class ProcessInstanceRepository extends BaseRepository
{

    protected static function buildQueryObj(){
        return NProcessInstance::query();
    }

    public static function getList($perPage, $condition = [], $keyword = null)
    {
        return static::buildQueryObj()->with(['design'])
            ->where(function (Builder $query) use (&$condition, $keyword) {
                if (Arr::has($condition, 'design_id')) {
                    $design_id = Arr::get($condition, 'design_id', []);
                    if (!empty($design_id)) {
                        $query->whereIn('id', $design_id);
                    }
                    unset($condition['design_id']);
                }
            })
            ->where(function ($query) use ($condition, $keyword) {
                self::buildQuery($query, $condition);
                if (! empty ($keyword))
                {
                    self::buildQuery($query, ['title' => $keyword]);
                }
            })
            ->orderBy('id', 'desc')
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
        return static::buildQueryObj()->with(['tasks','design'])->find($id);
    }

    public static function delete($id)
    {
        return NProcessDesign::destroy($id);
    }
}
