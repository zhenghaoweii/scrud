<?php

namespace limitless\scrud\Providers;

use Illuminate\Support\ServiceProvider;
use limitless\scrud\Commands\ApiGenerator;
use limitless\scrud\Commands\SyncTableColumns;

class ScrudServiceProvider extends ServiceProvider
{
    public function boot(){
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