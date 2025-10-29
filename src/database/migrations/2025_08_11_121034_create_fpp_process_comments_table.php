<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFppProcessCommentsTable extends Migration
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
        Schema::create($this->dbPrefix.'process_comments', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('instance_id')->comment('流程实例id ');
            $table->bigInteger('task_id')->nullable()->comment('流程实例任务id ');
            $table->longText('content')->comment('任务评论内容 ');

            $table->timestamps();
        });
    }

    public function down(){
        $this->initConfig();
        Schema::dropIfExists($this->dbPrefix.'process_comments');
    }
}
