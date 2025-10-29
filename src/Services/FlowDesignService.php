<?php

namespace Nirunfa\FlowProcessParser\Services;

use Nirunfa\FlowProcessParser\Models\NProcessDesign;
use Nirunfa\FlowProcessParser\Models\NProcessDesignVersion;
use Nirunfa\FlowProcessParser\Repositories\ProcessDesignRepository;
use Nirunfa\FlowProcessParser\Resources\ProcessNodeResource;

class FlowDesignService
{
    /**
     * 根据相关流程模型设计列表
     * @param array $searchParam
     * @return array{
     current_page: int,
     data: array,
     total: int,
     last_page: int,
     per_page: int,
     }
     */
    public static function getDesigns(array $searchParam =[]): array
    {
        $keyword = $searchParam['keyword'] ?? '';
        $status = $searchParam['status'] ?? '';
        $groupId = $searchParam['groupId'] ?? '';
        $categoryId = $searchParam['categoryId'] ?? '';

        $query = NProcessDesign::query();
        if(!empty($keyword)){
            $query->where(function($query) use($keyword){
                $query->where('name','like','%'.$keyword.'%')
                    ->orWhere('description','like','%'.$keyword.'%')
                    ->orWhereHas('group',function($query) use($keyword){
                        $query->where('name','like','%'.$keyword.'%');
                    })
                    ->orWhereHas('category',function($query) use($keyword){
                        $query->where('name','like','%'.$keyword.'%');
                    });
            });
        }
        if($groupId){
            $query->where('group_id',$groupId);
        }
        if($categoryId){
            $query->where('category_id',$categoryId);
        }
        if($status){
            $query->where('status',$status);
        }
        $designs = $query->paginate($searchParam['per_page'] ?? 30);
        $designs->transform(function ($item){
            return [
                "id" => $item->id,
                "category_id" => $item->category_id,
                "category" => $item->category,

                "group_id" => $item->group_id,
                "group" => $item->group,

                "versions" => $item->versions,

                "name" => $item->name,
                "define_key" => $item->define_key,

                "description" => $item->description,
                "order_sort" => $item->order_sort,
                "status" => $item->status,

                "created_at" => $item->created_at,
                "updated_at" => $item->updated_at,
                "deleted_at" => $item->deleted_at,
            ];
        });
        return $designs->toArray();
    }

    /**
     * 获取流程设计对应的节点
     * @param int $id
     * @param $param
     * @return mixed
     */
    public static function getNodes(int $id,$param=[]){
         $design = ProcessDesignRepository::find($id);

         if(empty($design)){
            return collect([]);
         }

         $design->loadMissing(['versions','nodes']);

         $curEnableVersion = $design->versions->where('status',NProcessDesignVersion::STATUS_ENABLE)->first();

         $nodes = $design->nodes->where('ver',$param['ver'] ?? $curEnableVersion->ver);
         $nodes->transform(function ($item){
             return new ProcessNodeResource($item)->toArray(null);
         });

         return $nodes->toArray();
    }
}
