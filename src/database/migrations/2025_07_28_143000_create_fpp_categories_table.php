<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFppCategoriesTable extends Migration
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
        Schema::create($this->dbPrefix.'categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('分类名称 ');
            $table->integer('pid')->default('0')->comment('父id ');
            $table->string('description')->comment('定义描述、说明 ');
            $table->integer('order_sort')->default(0)->comment('排序 ');
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
        Schema::dropIfExists($this->dbPrefix.'categories');
    }
}
