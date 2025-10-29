<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFppProcessVariablesTable extends Migration
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
        Schema::create($this->dbPrefix.'process_variables', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('instance_id')->nullable()->comment('流程实例 id ');
            $table->bigInteger('task_id')->nullable()->comment('流程实例任务 id ');
            $table->string('name')->comment('变量名称 ');
            $table->string('value')->nullable()->comment('变量值 ');
            $table->string('type',80)->default('string')->comment('变量类型 ');
            $table->timestamps();
        });
    }

    public function down(){
        $this->initConfig();
        Schema::dropIfExists($this->dbPrefix.'process_variables');
    }
}
