<?php

namespace Nirunfa\FlowProcessParser\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
// use Nirunfa\FlowProcessParser\Jobs\JsonNodeParserJob; // 使用辅助函数 createJsonNodeParserJob 替代
use Nirunfa\FlowProcessParser\Models\NProcessDesign;
use Nirunfa\FlowProcessParser\Models\NProcessDesignVersion;
use Nirunfa\FlowProcessParser\Repositories\ProcessDesignRepository;
use Nirunfa\FlowProcessParser\Requests\ProcessDesignRequest;
use Nirunfa\FlowProcessParser\Resources\ProcessDesignCollection;
use Nirunfa\FlowProcessParser\Resources\ProcessDesignResource;

class ProcessDesignController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @param  ProcessDesignRequest  $request
     * @param  array  $conditions
     * @return array
     */
    public function index(
        ProcessDesignRequest $request,
        array $conditions = []
    ): array {
        $conditions = array_merge($request->validated(), $conditions);
        $data = ProcessDesignRepository::getList(
            $request->has("per_page") ? $request->per_page : 30,
            $conditions,
            $request->keyword,
        );
        return $this->success(new ProcessDesignCollection($data));
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return array
     */
    public function show($id)
    {
        return $this->success(
            new ProcessDesignResource(
                ProcessDesignRepository::findWithRelations($id),
            ),
        );
    }

    /**
     * Store a newly created resource in storage.
     * 发布勾选
     *
     * @param ProcessDesignRequest $request
     * @return array
     */
    public function store(ProcessDesignRequest $request)
    {
        try {
            return $this->success(
                ProcessDesignRepository::add($request->validated()),
            );
        } catch (\Exception $e) {
            return $this->error("保存失败！{$e->getMessage()}");
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param ProcessDesignRequest $request
     * @param int $id
     * @return array
     */
    public function update(ProcessDesignRequest $request, $id)
    {
        $data = $request->validated();

        try {
            $res = ProcessDesignRepository::update($id, $data);
            return $this->success($res);
        } catch (\Exception $e) {
            return $this->error("保存失败！{$e->getMessage()}");
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param mixed $id
     * @return array
     */
    public function destroy($id)
    {
        $idArray = array_filter(explode(",", $id), function ($item) {
            return is_numeric($item);
        });

        try {
            return $this->success(ProcessDesignRepository::delete($idArray));
        } catch (\Exception $e) {
            return $this->error("操作失败！{$e->getMessage()}");
        }
    }

    /**
     * 保存流程设计
     * @param Request $request
     * @param NProcessDesign $id
     * @return array
     */
    public function saveDesign(int $id): array
    {
        $jsonContent = request()->get("json_content");
        $isNew = intval(request()->get("is_new", 1));
        $ver = request()->get("ver", null);

        DB::beginTransaction();
        try {
            $design = ProcessDesignRepository::find($id); //重新赋值
            if (empty($design)) {
                return $this->error("流程模型不存在");
            }
            //所有的版本
            $versions = $design
                ->loadMissing("versions")
                ->versions->sortByDesc("ver")->values();
            $curVerRecord = $versions->first();
            if(!is_null($ver) && $isNew === 0){
                $curVerRecord = $versions->firstWhere("ver", $ver);
            }

            $defaultVer = config("process_parser.start_ver", 0) + 1;

            $newVer = $defaultVer;
            if (is_null($curVerRecord)) {
                $res = $design->versions()->save(
                    new NProcessDesignVersion([
                        "ver" => $defaultVer,
                        "from_ver" => $defaultVer,
                        "json_content" => $jsonContent,
                        'status'=>$isNew?NProcessDesignVersion::STATUS_ENABLE:NProcessDesignVersion::STATUS_DISABLE
                    ]),
                );
            } else {
                if ($isNew) {
                    $newVer = $curVerRecord->addVersion();
                    //先禁用版本
                    $design->versions()->update(['status'=>NProcessDesignVersion::STATUS_DISABLE]);
                    $res = $design->versions()->save(
                        new NProcessDesignVersion([
                            "ver" => $newVer,
                            "from_ver" => $curVerRecord->ver,
                            "json_content" => $jsonContent,
                            'status'=>NProcessDesignVersion::STATUS_ENABLE
                        ]),
                    );
                } else {
                    $newVer = $curVerRecord->ver;
                    $curVerRecord->json_content = $jsonContent;
                    $res = $curVerRecord->save();
                }
            }

            if ($res !== false) {
                $useQueue = config(
                    "process_parser.json_parser.use_queue",
                    false,
                );
                if ($useQueue) {
                    $queueName = config(
                        "process_parser.json_parser.queue_name",
                    );
                    if (empty($queueName)) {
                        $queueName = "process_parser";
                    }
                    $this->dispatch(
                        createJsonNodeParserJob($design->id, $newVer),
                    )->onQueue($queueName);
                } else {
                    $this->dispatchSync(
                        createJsonNodeParserJob($design->id, $newVer),
                    );
                }

                DB::commit();
                return $this->success($res);
            } else {
                DB::rollBack();
                return $this->error("操作失败！");
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error("操作失败！{$e->getMessage()}");
        }
    }
}
