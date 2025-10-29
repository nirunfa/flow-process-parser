<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFppProcessTasksTable extends \Illuminate\Database\Migrations\Migration
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
        Schema::create($this->dbPrefix.'process_tasks', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('instance_id')->comment('流程实例 id ');
            $table->string('name')->comment('流程模型节点名称 ');
            $table->bigInteger('node_id')->comment('流程模型节点id ');
            $table->tinyInteger('status')->default(0)->comment('流程状态0待分配、1待审批、2已完成 ');

            $table->timestamps();
        });

        Schema::create($this->dbPrefix.'process_task_assignees', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('task_id')->comment('流程实例任务id ');
            $table->string('node_approver_id')->nullable()->comment('节点设计审批uuid ');
            $table->bigInteger('assignee_id')->nullable()->comment('任务分配人 id ');
            $table->string('assignee')->nullable()->comment('任务分配人名称 ');
            $table->longText('form_data')->nullable()->comment('节点表单对应的json数据 ');
            $table->bigInteger('form_data_id')->nullable()->comment('节点表单对应的表单数据id，自定义表单该项通常为 0 或空 ');

            $table->unique(['task_id', 'assignee_id']);
        });

    }

    public function down(){
        $this->initConfig();
        Schema::dropIfExists($this->dbPrefix.'process_tasks');
        Schema::dropIfExists($this->dbPrefix.'process_task_assignees');
    }
}
