<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFppProcessNodeAttrsTable extends Migration
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
        Schema::create($this->dbPrefix.'process_node_attrs', function (Blueprint $table) {
            $table->bigInteger('node_id')->comment('流程节点 id ');
            $table->string('towards',50)->nullable()->comment('条件节点的指向 ');
            $table->string('condition_type',200)->nullable()->comment('条件节点类型 ');
            $table->tinyInteger('approve_type')->nullable()->comment('审批类型 1 人工审批 2 自动通过 3 自动拒绝');
            $table->tinyInteger('approve_mode')->nullable()->comment('审批方式 ');
            $table->tinyInteger('approver_same_initiator')->nullable()->comment('审批人和发起人相同时的配置 ');
            $table->tinyInteger('approver_same_prev')->nullable()->comment('审批人和上一个节点相同时的配置 ');
            $table->tinyInteger('approver_empty')->nullable()->comment('审批人为空时的配置 ');

            $table->timestamps();

            $table->primary('node_id');
        });
    }

    public function down(){
        $this->initConfig();
        Schema::dropIfExists($this->dbPrefix.'process_node_attrs');
    }
}
