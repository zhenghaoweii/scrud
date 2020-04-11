<?php

namespace limitless\scrud\Providers;

use Illuminate\Support\ServiceProvider;
use limitless\scrud\Commands\ApiGenerator;
use limitless\scrud\Commands\SyncTableColumns;

class ScrudServiceProvider extends AbstractServiceProvider
{
    public function boot(){

        $path = realpath(__DIR__.'/../../config/config.php');

        $this->publishes([$path => config_path('scrud.php')], 'config');
        $this->mergeConfigFrom($path, 'scrud');

        if ($this->app->runningInConsole()) {
            $this->commands([
                    ApiGenerator::class,
                    SyncTableColumns::class,
            ]);
        }
    }

    public function register()
    {
        parent::register();
    }
}