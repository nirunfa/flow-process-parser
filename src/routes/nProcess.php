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

Route::group(
    [
        'as' => 'processParser::',
        'prefix' => 'n_process',
    ],
    function () {
        Route::put('/process_definitions/{id}/design',[\Nirunfa\FlowProcessParser\Controllers\ProcessDefinitionController::class, 'saveDesign'])
            ->name('process_definitions.store.design');
        Route::apiResources(['process_definitions' => \Nirunfa\FlowProcessParser\Controllers\ProcessDefinitionController::class]);
        Route::apiResources(['groups' => \Nirunfa\FlowProcessParser\Controllers\GroupController::class]);
        Route::apiResources(['categories' => \Nirunfa\FlowProcessParser\Controllers\CategoryController::class]);

        //data数据
        Route::get('definition_data' , [\Nirunfa\FlowProcessParser\Controllers\DataController::class,'definition'])->name('definition.data');
        Route::get('cate_data' , [\Nirunfa\FlowProcessParser\Controllers\DataController::class,'category'])->name('category.data');
        Route::get('group_data' , [\Nirunfa\FlowProcessParser\Controllers\DataController::class,'group'])->name('group.data');

    });



