<?php

namespace Nirunfa\FlowProcessParser\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Nirunfa\FlowProcessParser\Jobs\JsonNodeParserJob;
use Nirunfa\FlowProcessParser\Models\NProcessDefinition;
use Nirunfa\FlowProcessParser\Models\NProcessDefinitionVersion;
use Nirunfa\FlowProcessParser\Repositories\ProcessDefinitionRepository;
use Nirunfa\FlowProcessParser\Requests\ProcessDefinitionRequest;
use Nirunfa\FlowProcessParser\Resources\ProcessDefinitionCollection;
use Nirunfa\FlowProcessParser\Resources\ProcessDefinitionResource;

class ProcessDefinitionController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @param  ProcessDefinitionRequest  $request
     * @param  array  $conditions
     * @return \Illuminate\Http\Response
     */
    public function index(ProcessDefinitionRequest $request, array $conditions = [])
    {
        $conditions = array_merge($request->validated(), $conditions);
        $data = ProcessDefinitionRepository::getList(
            $request->has('per_page') ? $request->per_page : 30,
            $conditions,
            $request->keyword
        );
        if($data instanceof Collection){
            return $this->success($data);
        }
        return $this->success(new ProcessDefinitionCollection($data));
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return $this->success(new ProcessDefinitionResource(ProcessDefinitionRepository::findWithRelations($id)));
    }

    /**
     * Store a newly created resource in storage.
     * 发布勾选
     *
     * @param ProcessDefinitionRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(ProcessDefinitionRequest $request)
    {
        try{
            return $this->success(ProcessDefinitionRepository::add($request->validated()));
        } catch (\Exception $e) {
            return $this->error("保存失败！{$e->getMessage ()}");
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param ProcessDefinitionRequest $request
     * @param int $id
     * @return array
     */
    public function update (ProcessDefinitionRequest $request, $ProcessDefinition)
    {
        $data = $request->validated();

        try {
            $res = ProcessDefinitionRepository::update ($ProcessDefinition, $data);
            return $this->success($res);
        } catch (\Exception $e) {
            return $this->error("保存失败！{$e->getMessage ()}");
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
        $idArray = array_filter(explode(',',$id),function($item){
            return is_numeric($item);
        });

        try{
            return $this->success(ProcessDefinitionRepository::delete($idArray));
        } catch (\Exception $e) {
            return $this->error("操作失败！{$e->getMessage ()}");
        }
    }

    /**
     * 保存流程设计
     * @param Request $request
     * @param NProcessDefinition $id
     * @return array
     */
    public function saveDesign(int $id): array
    {
        $jsonContent = request()->get('json_content');
        $isNew = request()->get('is_new',false);

        try{
            $definition = ProcessDefinitionRepository::find($id); //重新赋值
            if(empty($definition)){
                return $this->error('流程定义不存在');
            }
            //所有的版本
            $versions = $definition->loadMissing('versions')->versions->sortBy('ver',SORT_DESC);
            $curVerRecord = $versions->first();

            $defaultVer = config('process_parser.start_ver',0)+1;

            $newVer = $defaultVer;
            if(is_null($curVerRecord)){
                $res = $definition->versions()->save(new NProcessDefinitionVersion([
                    'ver' => $defaultVer,
                    'from_ver' => $defaultVer,
                    'json_content'=>$jsonContent,
                ]));
            }else{
                if($isNew){
                    $newVer = $curVerRecord->addVersion();
                    $res =  $definition->versions()->save(new NProcessDefinitionVersion([
                        'ver' => $newVer,
                        'from_ver' => $curVerRecord->ver,
                        'json_content'=>$jsonContent,
                    ]));
                }else{
                    $newVer = $curVerRecord->ver;
                    $res = $curVerRecord->save([
                        'json_content'=>$jsonContent,
                    ]);
                }
            }

            if($res !== false){
                $useQueue = config('process_parser.json_parser.use_queue',false);
                if($useQueue){
                    $queueName = config('process_parser.json_parser.queue_name');
                    if(empty($queueName)){
                        $queueName = 'process_parser';
                    }
                    $this->dispatch(new JsonNodeParserJob($definition->id,$newVer))->onQueue($queueName);
                }else{
                    $this->dispatchSync(new JsonNodeParserJob($definition->id,$newVer));
                }
            }

            return $this->success($res);
        } catch (\Exception $e) {
            throw $e;
            return $this->error("操作失败！{$e->getMessage ()}");
        }
    }
}