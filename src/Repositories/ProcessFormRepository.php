<?php
namespace Nirunfa\FlowProcessParser\Repositories;

use Nirunfa\FlowProcessParser\Models\NProcessForm;

class ProcessFormRepository extends BaseRepository
{
    public static function getList($perPage, $condition = [], $keyword = null)
    {
        return NProcessForm::query()
            ->where(function ($query) use ($condition, $keyword) {
                self::buildQuery($query, $condition);
                if (! empty ($keyword))
                {
                    self::buildQuery($query, ['name' => ['operator' => 'LIKE', 'value' => '%' . $keyword . '%'],
                    'description' => ['operator' => 'LIKE', 'value' => '%' . $keyword . '%']]);
                }
            })
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public static function add($data)
    {
        return NProcessForm::query()->create($data);
    }

    public static function update($id, $data)
    {
        return NProcessForm::find($id)->update($data);
    }

    public static function find($id)
    {
        return NProcessForm::query()->find($id);
    }

    public static function delete($id)
    {
        return NProcessForm::destroy($id);
    }

    public static function select ($keyword = null)
    {
        return NProcessForm::query()
            ->select(['id', 'name','fields','ver','status','json_content'])
            ->where(function ($query) use ($keyword) {
               if (! empty ($keyword))
                {
                    $query->where('name', 'like', '%' . $keyword . '%')->orWhere('description', 'like', '%' . $keyword . '%');
                }
            })
            ->orderBy('id', 'desc')
            ->get()
            ->map(function (NProcessForm $item) {
                $map = $item->toArray();
                $map['value'] = $item->id;
                $map['text'] = $item->name;
                $map['label'] = $item->name;
                return $map;
            });
    }

    public static function selectTree()
    {
        return NProcessForm::select(['id', 'name','fields','ver','status','json_content'])
            ->get()
            ->map(function (NProcessForm $model) {
                $data = $model->toArray();
                return $data;
            })->sortBy('id','desc');

    }
}
