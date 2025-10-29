<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFppProcessDesignsTable extends Migration
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
        Schema::create($this->dbPrefix.'process_designs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('定义名称 ');
            $table->integer('group_id')->default('0')->comment('分组id ');
            $table->integer('category_id')->default('0')->comment('分类id ');
            $table->string('description')->nullable()->comment('定义描述、说明 ');
            $table->string('define_key')->nullable()->comment('定义key 唯一 ');
            $table->integer('order_sort')->default(0)->comment('排序 ');
            $table->tinyInteger('status')->default(1)->comment('流程状态 0禁用 1启用 ');
            $table->timestamps();
        });

        Schema::create($this->dbPrefix.'process_design_versions', function (Blueprint $table) {
            $table->id();
            $table->integer('design_id')->comment($this->dbPrefix.'process_designs主键 id ');
            $table->integer('ver')->default(0)->comment('版本号 ');
            $table->integer('from_ver')->default(0)->comment('来源版本号（可能是继承，可能是后续历史列表里选择） ');
            $table->tinyInteger('status')->default(0)->comment('版本状态 0禁用 1启用 ,一个流程模型只允许启用一个版本，其他版本自动禁用 ');
            $table->longText('json_content')->nullable()->comment('对应版本的json数据字符串 ');

            $table->unique(['design_id', 'ver']);
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
        Schema::dropIfExists($this->dbPrefix.'process_designs');
        Schema::dropIfExists($this->dbPrefix.'process_design_versions');
    }
}
