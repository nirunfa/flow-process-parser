<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFppProcessNodesTable extends Migration
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
        Schema::create($this->dbPrefix.'process_nodes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('结点名称 ');
            $table->string('n_uuid')->comment('结点uuid ');
            $table->tinyInteger('type')->default(0)->comment('结点类型 ');
            $table->integer('ver')->default('0')->comment('版本号 ');
            $table->bigInteger('definition_id')->comment($this->dbPrefix.'process_definitions表 id ');
            $table->bigInteger('form_id')->default(0)->comment($this->dbPrefix.'process_forms表 id ');
            $table->bigInteger('prev_node_id')->nullable()->comment('上一个处理结点 id ');
            $table->string('prev_node_uuid')->nullable()->comment('上一个处理结点 uuid ');

            $table->string('description')->nullable()->comment('描述、说明 ');
            $table->tinyInteger('status')->default(1)->comment('状态 0禁用 1启用 ');
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $this->initConfig();
        Schema::dropIfExists($this->dbPrefix.'process_nodes');
    }
}