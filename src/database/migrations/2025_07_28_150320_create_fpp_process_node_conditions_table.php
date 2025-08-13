<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFppProcessNodeConditionsTable extends Migration
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
        Schema::create($this->dbPrefix.'process_node_conditions', function (Blueprint $table) {
            $table->string('id')->comment('条件uuid ');
            $table->string('group_id')->comment('条件组uuid ');
            $table->bigInteger('node_id')->comment('流程节点 process_nodes表 id ');
            $table->integer('column_id')->default(0)->comment('条件字段 id  ');
            $table->string('column_name')->comment('条件字段名称 ');
            $table->string('column_type')->comment('条件字段类型 ');
            $table->string('column_value',160)->comment('条件字段编码名称 ');
            $table->string('opt_type',50)->comment('条件运算符编码名称 ');
            $table->string('opt_type_name',50)->comment('条件运算符名称 ');
            $table->string('value_type',20)->comment('条件比较值类型 ');
            $table->text('condition_value')->comment('条件比较值 ');
            $table->text('condition_value_name')->comment('条件比较值名称 ');

            $table->timestamps();

            $table->primary(['id', 'node_id','group_id']);
        });
    }

    public function down(){
        $this->initConfig();
        Schema::dropIfExists($this->dbPrefix.'process_node_conditions');
    }
}