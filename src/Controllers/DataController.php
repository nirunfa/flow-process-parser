<?php

namespace Nirunfa\FlowProcessParser\Controllers;

use Illuminate\Support\Collection;
use Nirunfa\FlowProcessParser\Repositories\CategoryRepository;
use Nirunfa\FlowProcessParser\Repositories\GroupRepository;
use Nirunfa\FlowProcessParser\Repositories\ProcessDesignRepository;
use Nirunfa\FlowProcessParser\Repositories\ProcessFormRepository;
use Nirunfa\FlowProcessParser\Requests\CategoryRequest;
use Nirunfa\FlowProcessParser\Requests\GroupRequest;
use Nirunfa\FlowProcessParser\Requests\ProcessDesignRequest;
use Nirunfa\FlowProcessParser\Requests\ProcessFormRequest;
use Nirunfa\FlowProcessParser\Resources\CategoryCollection;
use Nirunfa\FlowProcessParser\Resources\GroupCollection;
use Nirunfa\FlowProcessParser\Resources\ProcessDesignCollection;
use Nirunfa\FlowProcessParser\Resources\ProcessFormCollection;

class DataController extends BaseController
{
    public function category(CategoryRequest $request, array $conditions = [])
    {
        $data_type = $request->get('data_type');

        if ($data_type === 'select') {
            $data = CategoryRepository::select($conditions, $request->keyword);
        } else if ($data_type === 'cascader') {
            $data = CategoryRepository::selectTree($request->pid ?? 0);
        } else {
            $data = CategoryRepository::categoryTree($conditions);
        }

        if ($data instanceof Collection) {
            return $this->success($data);
        }
        return $this->success(new CategoryCollection($data), );
    }


    public function group(GroupRequest $request, array $conditions = [])
    {
        $data_type = $request->get('data_type');

        if ($data_type === 'select') {
            $data = GroupRepository::select($request->keyword);
        } else if ($data_type === 'cascader') {
            $data = GroupRepository::selectTree();
        }
        if ($data instanceof Collection) {
            return $this->success($data);
        }
        return $this->success(new GroupCollection($data));
    }

    public function design(ProcessDesignRequest $request, array $conditions = [])
    {
        $data_type = $request->get('data_type');

        if ($data_type === 'select') {
            $data = ProcessDesignRepository::select($conditions);
        } else if ($data_type === 'cascader') {
            $data = ProcessDesignRepository::selectTree();
        }
        if ($data instanceof Collection) {
            return $this->success($data);
        }
        return $this->success(new ProcessDesignCollection($data));
    }

    public function forms(ProcessFormRequest $request, array $conditions = [])
    {
        $data_type = $request->get('data_type');

        if ($data_type === 'select') {
            $data = ProcessFormRepository::select($conditions);
        } else if ($data_type === 'cascader') {
            $data = ProcessFormRepository::selectTree();
        }
        if ($data instanceof Collection) {
            return $this->success($data);
        }
        return $this->success(new ProcessFormCollection($data));
    }
}
