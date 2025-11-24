<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDescriptionToFppProcessConfigsTable extends Migration
{
   /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('fpp_process_configs', function (Blueprint $table) {
            $table->string('description')->nullable()->comment('配置描述 ');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('fpp_process_configs', function (Blueprint $table) {
            $table->dropColumn('description')->nullable()->comment('配置描述 ');
        });
    }
}