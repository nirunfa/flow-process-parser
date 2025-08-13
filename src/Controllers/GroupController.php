<?php

namespace Nirunfa\FlowProcessParser\Controllers;

use Illuminate\Support\Collection;
use Nirunfa\FlowProcessParser\Repositories\GroupRepository;
use Nirunfa\FlowProcessParser\Requests\GroupRequest;
use Nirunfa\FlowProcessParser\Resources\GroupCollection;

class GroupController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @param  GroupRequest  $request
     * @param  array  $conditions
     * @return array
     */
    public function index(GroupRequest $request, array $conditions = [])
    {
        $conditions = array_merge($request->validated(), $conditions);
        $data = GroupRepository::getList(
            $request->get('per_page',50),
            $conditions,
            $request->keyword
        );

        if($data instanceof Collection){
            return $this->success($data);
        }
        return $this->success(new GroupCollection($data));
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return array
     */
    public function show($id)
    {
        return $this->success(GroupRepository::find($id));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param GroupRequest $request
     * @return array
     */
    public function store(GroupRequest $request)
    {
        try{
            return $this->success(GroupRepository::add($request->validated()));
        } catch (\Exception $e) {
            return $this->error("保存失败！{$e->getMessage ()}");
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param GroupRequest $request
     * @param int $id
     * @return array
     */
    public function update (GroupRequest $request, $Group)
    {
        $data = $request->validated();

        try {
            $res = GroupRepository::update ($Group, $data);
            return $this->success($res);
        } catch (\Exception $e) {
            return $this->error("保存失败！{$e->getMessage ()}");
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return array
     */
    public function destroy($id)
    {
        $idArray = array_filter(explode(',',$id),function($item){
            return is_numeric($item);
        });

        try{
            collect($idArray)->each(function ($id){
                $group = GroupRepository::find($id);
            });
            return $this->success(GroupRepository::delete($idArray));
        } catch (\Exception $e) {
            return $this->error("操作失败！{$e->getMessage ()}");
        }
    }
}
