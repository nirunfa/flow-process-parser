<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFppProcessNodeApproversTable extends Migration
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
        Schema::create($this->dbPrefix . 'process_node_approvers', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->comment('审批（处理）人uuid ');
            $table->bigInteger('node_id')->comment('流程节点 process_nodes表 id ');
            $table->tinyInteger('level_mode')->nullable()->comment('审批（处理）人层级 ');
            $table->unsignedTinyInteger('loop_count')->default(0)->comment('审批（处理）人层级检索层次,0 不限制，1 表示检索一层,依此类推 ');
            $table->tinyInteger('approver_type')->comment('审批（处理）人类型 ');
            $table->tinyInteger('approve_direct')->comment('审批人检索方向 ');
            $table->string('approver_ids')->comment('审批人标识符号或者表达式等 ');
            $table->string('approver_names')->comment('审批人名称或者简称 ');
            $table->integer('order_sort')->default(0)->comment('审批人检索排序数值 ');
            $table->timestamps();

            $table->unique(['uuid', 'node_id']);
        });
    }

    public function down(){
        $this->initConfig();
        Schema::dropIfExists($this->dbPrefix.'process_node_approvers');
    }
}
