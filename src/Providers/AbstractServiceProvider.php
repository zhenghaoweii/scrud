<?php


namespace limitless\scrud\Providers;


use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Arr;

abstract class AbstractServiceProvider extends ServiceProvider
{

    /**
     * Helper to get the config values.
     *
     * @param  string  $key
     * @param  string  $default
     *
     * @return mixed
     */
    protected function getConfig($key)
    {
        $file = new Filesystem;

        if($file->exists(config_path('scrud.php'))){
            $config = include(config_path('scrud.php'));
        }else{
            $config = include(realpath(__DIR__.'/../../config/config.php'));
        }

        return Arr::get($config, $key);
    }
}