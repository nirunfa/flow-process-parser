<?php

namespace Nirunfa\FlowProcessParser\Controllers;

use Nirunfa\FlowProcessParser\Repositories\ProcessFormRepository;
use Nirunfa\FlowProcessParser\Requests\ProcessFormRequest;
use Nirunfa\FlowProcessParser\Resources\ProcessFormCollection;

class ProcessFormController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @param  ProcessFormRequest  $request
     * @param  array  $conditions
     * @return array
     */
    public function index(ProcessFormRequest $request, array $conditions = [])
    {
        $conditions = array_merge($request->validated(), $conditions);
        $data = ProcessFormRepository::getList(
            $request->get('per_page',50),
            $conditions,
            $request->keyword
        );

        if($data instanceof Collection){
            return $this->success($data);
        }
        return $this->success(new ProcessFormCollection($data));
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return array
     */
    public function show($id)
    {
        return $this->success(ProcessFormRepository::find($id));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param ProcessFormRequest $request
     * @return array
     */
    public function store(ProcessFormRequest $request)
    {
        try{
            return $this->success(ProcessFormRepository::add($request->validated()));
        } catch (\Exception $e) {
            return $this->error("保存失败！{$e->getMessage ()}");
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param ProcessFormRequest $request
     * @param int $id
     * @return array
     */
    public function update (ProcessFormRequest $request, $Group)
    {
        $data = $request->validated();

        try {
            $res = ProcessFormRepository::update ($Group, $data);
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
                $group = ProcessFormRepository::find($id);
            });
            return $this->success(ProcessFormRepository::delete($idArray));
        } catch (\Exception $e) {
            return $this->error("操作失败！{$e->getMessage ()}");
        }
    }

}
