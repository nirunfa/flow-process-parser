<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFppProcessFormsTable extends Migration
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
        Schema::create($this->dbPrefix.'process_forms', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('表单显示名称 ');
            $table->integer('ver')->default('0')->comment('版本号 ');
            $table->longText('fields')->comment('字段 json ');
            $table->longText('json_content')->comment('表单设计的 json ');
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
        Schema::dropIfExists($this->dbPrefix.'process_forms');
    }
}
