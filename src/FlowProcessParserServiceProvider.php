<?php

namespace Nirunfa\FlowProcessParser;

use Illuminate\Support\ServiceProvider;
use Nirunfa\FlowProcessParser\Models\NProcessDesign;
use Nirunfa\FlowProcessParser\Observers\NProcessDesignObserver;
use Nirunfa\FlowProcessParser\Providers\RouteServiceProvider;

class FlowProcessParserServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // 发布配置文件
        $this->publishes([
            __DIR__.'/config/process_parser.php' => config_path('process_parser.php')
        ],'config');

        //            __DIR__.'/database/migrations/' => database_path('/migrations/process_parser')

        $this->mergeConfigFrom(__DIR__.'/config/process_parser.php','process_parser');

        //数据表迁移文件
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        $this->observe();
    }
    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(RouteServiceProvider::class);

        $this->app->singleton('process_parser', function ($app) {
            return new ProcessParser($app['config']);
        });

    }

    protected function observe () {
        NProcessDesign::observe(NProcessDesignObserver::class);
    }
}
