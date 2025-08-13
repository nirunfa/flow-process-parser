<?php

namespace Nirunfa\FlowProcessParser\Controllers;

use Illuminate\Support\Collection;
use Nirunfa\FlowProcessParser\Repositories\ProcessInstanceRepository;
use Nirunfa\FlowProcessParser\Requests\ProcessInstanceRequest;
use Nirunfa\FlowProcessParser\Resources\ProcessInstanceCollection;

class ProcessInstanceController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @param  ProcessInstanceRequest  $request
     * @param  array  $conditions
     * @return array
     */
    public function index(ProcessInstanceRequest $request, array $conditions = [])
    {
        $conditions = array_merge($request->validated(), $conditions);
        $data = ProcessInstanceRepository::getList(
            $request->get('per_page',50),
            $conditions,
            $request->keyword
        );

        if($data instanceof Collection){
            return $this->success($data);
        }
        return $this->success(new ProcessInstanceCollection($data));
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return array
     */
    public function show($id)
    {
        return $this->success(ProcessInstanceRepository::find($id));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param ProcessInstanceRequest $request
     * @return array
     */
    public function store(ProcessInstanceRequest $request)
    {
        try{
            return $this->success(ProcessInstanceRepository::add($request->validated()));
        } catch (\Exception $e) {
            return $this->error("保存失败！{$e->getMessage ()}");
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param ProcessInstanceRequest $request
     * @param int $id
     * @return array
     */
    public function update (ProcessInstanceRequest $request, $Group)
    {
        $data = $request->validated();

        try {
            $res = ProcessInstanceRepository::update ($Group, $data);
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
                $group = ProcessInstanceRepository::find($id);
            });
            return $this->success(ProcessInstanceRepository::delete($idArray));
        } catch (\Exception $e) {
            return $this->error("操作失败！{$e->getMessage ()}");
        }
    }
}