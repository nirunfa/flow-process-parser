<?php

namespace Nirunfa\FlowProcessParser\Controllers;

use Illuminate\Support\Collection;
use Nirunfa\FlowProcessParser\Repositories\CategoryRepository;
use Nirunfa\FlowProcessParser\Repositories\GroupRepository;
use Nirunfa\FlowProcessParser\Repositories\ProcessDefinitionRepository;
use Nirunfa\FlowProcessParser\Requests\CategoryRequest;
use Nirunfa\FlowProcessParser\Requests\GroupRequest;
use Nirunfa\FlowProcessParser\Requests\ProcessDefinitionRequest;
use Nirunfa\FlowProcessParser\Resources\CategoryCollection;
use Nirunfa\FlowProcessParser\Resources\GroupCollection;
use Nirunfa\FlowProcessParser\Resources\ProcessDefinitionCollection;

class DataController extends BaseController
{
    public function category(CategoryRequest $request, array $conditions = []){
        $data_type = $request->get ('data_type');

        if ($data_type === 'select') {
            $data = CategoryRepository::select ($conditions, $request->keyword);
        } else if ($data_type === 'cascader') {
            $data = CategoryRepository::selectTree ($request->pid ?? 0);
        } else {
            $data = CategoryRepository::categoryTree($conditions);
        }

        if($data instanceof Collection){
            return $this->success($data);
        }
        return $this->success(new CategoryCollection($data), );
    }


    public function group(GroupRequest $request, array $conditions = []){
        $data_type = $request->get ('data_type');

        if ($data_type === 'select') {
            $data = GroupRepository::select ($request->keyword);
        } else if ($data_type === 'cascader') {
            $data = GroupRepository::selectTree ();
        }
        if($data instanceof Collection){
            return $this->success($data);
        }
        return $this->success(new GroupCollection($data));
    }

    public function definition(ProcessDefinitionRequest $request, array $conditions = []){
        $data_type = $request->get ('data_type');

        if ($data_type === 'select') {
            $data = ProcessDefinitionRepository::select ($conditions);
        } else if ($data_type === 'cascader') {
            $data = ProcessDefinitionRepository::selectTree ();
        }
        if($data instanceof Collection){
            return $this->success($data);
        }
        return $this->success(new ProcessDefinitionCollection($data));
    }
}