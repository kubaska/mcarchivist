<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public function register()
    {

    }

    public function boot()
    {
        $this->app['router']->group([
            'namespace' => 'App\Http\Controllers',
            'prefix' => 'api'
        ], function ($router) {
            require __DIR__.'/../../routes/api.php';
        });
        $this->app['router']->group([
            'namespace' => 'App\Http\Controllers',
        ], function ($router) {
            require __DIR__.'/../../routes/web.php';
        });
    }
}
