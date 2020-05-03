<?php

namespace limitless\scrud\Providers;

use Illuminate\Support\ServiceProvider;
use limitless\scrud\Classes\ClassesGenerator;
use limitless\scrud\Commands\ApiGenerator;
use limitless\scrud\Commands\SyncTableColumns;

class ScrudServiceProvider extends ServiceProvider
{
    public function boot()
    {

        $path = realpath(__DIR__.'/../../config/config.php');

        //php artisan vendor:publish --tag=scrud
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

        $this->app->bind('scrud',function(){

            return new ClassesGenerator();

        });
    }
}