<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFppProcessInstancesTable extends Migration
{
    use \Nirunfa\FlowProcessParser\Traits\MigrationTrait;
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $this->initConfig();
        Schema::create($this->dbPrefix.'process_instances', function (Blueprint $table) {
            $table->id();
            $table->string('title')->comment('流程实例主题 ');
            $table->integer('ver')->comment('当前使用的版本 ');
            $table->string('code')->comment('流程实例单号 ');
            $table->bigInteger('design_id')->comment('流程模型 id ');
            $table->tinyInteger('status')->default(0)->comment('流程状态0未启动、1进行中、2已完成、3撤回、4废弃、5驳回 ');
            $table->boolean('is_archived')->default(false)->comment('是否存档 0未存档、1已存档 ');

            $table->bigInteger('initiator_id')->comment('流程发起人id ');
            $table->string('initiator')->comment('流程发起人名称 ');

            $table->timestamp('start_time')->nullable()->comment('流程实例启动时间 ');
            $table->timestamp('end_time')->nullable()->comment('流程实例结束（完成）时间 ');
            $table->integer('duration')->nullable()->comment('流程实例耗时时长(单位秒) ');

            $table->timestamps();
            $table->unique('code');
        });
    }

    public function down(){
        $this->initConfig();
        Schema::dropIfExists($this->dbPrefix.'process_instances');
    }
}
