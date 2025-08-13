<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFppProcessConfigsTable extends Migration
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
        Schema::create($this->dbPrefix.'process_configs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('node_id')->comment('流程节点 id ');
            $table->string('name')->comment('配置名称 ');
            $table->text('value')->comment('配置值 ');
            $table->string('group')->comment('配置分组 ');
            $table->timestamps();
        });
    }

    public function down(){
        $this->initConfig();
        Schema::dropIfExists($this->dbPrefix.'process_configs');
    }
}