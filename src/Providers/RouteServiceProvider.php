<?php

namespace Nirunfa\FlowProcessParser\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{

    /**
     * @var string $moduleName
     */
    protected $moduleName = 'NProcess';

    /**
     * @var string $moduleNameLower
     */
    protected $moduleNameLower = 'n_process';

    protected $middleware = [];

    protected $path;

    /**
     * Create a new service provider instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return void
     */
    public function __construct ($app)
    {
        parent::__construct ($app);

        $this->initRoute ();
    }

    protected function initRoute ()
    {
        $this->path = __DIR__ . '/../routes';

        //路由相关配置
        $routeConfig = config('process_parser.route');
        $this->moduleNameLower = $routeConfig['prefix'] ?? null;
        if(empty($this->moduleNameLower)) {
            $this->moduleNameLower =  '/api';
        }
        $this->middleware = $routeConfig['middleware'] ?? [];
    }

    protected function getPath ($name = null)
    {
        return $this->path . '/' . (isset ($name) ? $name : 'web') . '.php';
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map ()
    {
        $this->mapApiRoutes();
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes ()
    {
        Route::prefix ($this->moduleNameLower)
            ->middleware ($this->middleware)
            ->group($this->getPath ('nProcess'));

            Route::prefix ($this->moduleNameLower)
                ->middleware ([])
                ->group($this->getPath ('nCommon'));
    }
}
