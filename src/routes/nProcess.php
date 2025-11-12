<?php

use Illuminate\Support\Facades\Route;

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
                Route::post("/process_designs/{id}/design", [
                    \Nirunfa\FlowProcessParser\Controllers\ProcessDesignController::class,
                    "saveDesign",
                ])->name("process_designs.store.design");
                Route::apiResources([
                    "process_designs" =>
                        \Nirunfa\FlowProcessParser\Controllers\ProcessDesignController::class,
                ]);
                Route::apiResources([
                    "groups" =>
                        \Nirunfa\FlowProcessParser\Controllers\GroupController::class,
                ]);
                Route::apiResources([
                    "categories" =>
                        \Nirunfa\FlowProcessParser\Controllers\CategoryController::class,
                ]);
            },
        );
    },
);
