<?php

namespace Nirunfa\FlowProcessParser\Controllers;


use Illuminate\Support\Collection;
use Nirunfa\FlowProcessParser\Repositories\CategoryRepository;
use Nirunfa\FlowProcessParser\Requests\CategoryRequest;
use Nirunfa\FlowProcessParser\Resources\CategoryCollection;

class CategoryController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @param  CategoryRequest  $request
     * @param  array  $conditions
     * @return array
     */
    public function index(CategoryRequest $request)
    {
        $data = CategoryRepository::getList(
            $request->get('per_page',50),
            $request->validated(),
            $request->keyword
        );
        if($data instanceof Collection){
            return $this->success($data);
        }
        return $this->success(new CategoryCollection($data));
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return array
     */
    public function show($id)
    {
        return $this->success(CategoryRepository::find($id));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param categoryRequest $request
     * @return array
     */
    public function store(CategoryRequest $request)
    {
        try{
            return $this->success(CategoryRepository::add($request->validated()));
        } catch (\Exception $e) {
            return $this->error("保存失败！{$e->getMessage ()}");
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param categoryRequest $request
     * @param int $id
     * @return array
     */
    public function update (CategoryRequest $request, $category)
    {
        $data = $request->validated();

        try {
            $res = CategoryRepository::update ($category, $data);
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
            return $this->success(CategoryRepository::delete($idArray));
        } catch (\Exception $e) {
            return $this->error("操作失败！{$e->getMessage ()}");
        }
    }
}
