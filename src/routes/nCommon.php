<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

//路由相关配置
$routeConfig = getParserConfig("process_parser.route");

Route::group(
    [
        "as" => "processParser::",
    ],
    function () {

        Route::group(
            [
                "prefix" => "n_process",
            ],
            function () {
                //data数据
                Route::get("design_data", [
                    \Nirunfa\FlowProcessParser\Controllers\DataController::class,
                    "design",
                ])->name("design.data");
                Route::get("cate_data", [
                    \Nirunfa\FlowProcessParser\Controllers\DataController::class,
                    "category",
                ])->name("category.data");
                Route::get("group_data", [
                    \Nirunfa\FlowProcessParser\Controllers\DataController::class,
                    "group",
                ])->name("group.data");
            },
        );
    },
);
