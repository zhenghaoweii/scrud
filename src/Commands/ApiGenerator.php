<?php

namespace limitless\scrud\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Filesystem\Filesystem;
use Str;
use File;
use Arr;

class ApiGenerator extends Command
{
    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $file;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrud:api {class} {--m}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate API SCRUD';

    /**
     * Create a new migration creator instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @param  string  $customStubPath
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->file = new Filesystem;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $class         = $this->argument('class');
        $haveMigration = $this->option('m');
        $this->controller($class);
        $this->model($class);
        $this->request($class);
        $this->resource($class);
        if ($haveMigration == 'm') {
            $this->migration($class);
        }
    }

    protected function getStub($type)
    {
        return file_get_contents(dirname(dirname(__FILE__))."/Stubs/$type.stub");
    }

    /**
     * Get the date prefix for the migration.
     *
     * @return string
     */
    protected function getDatePrefix()
    {
        return date('Y_m_d_His');
    }

    protected function controller($class)
    {
        $controllerTemplate = str_replace(
                ['{{ class }}', '{{ classPlural }}', '{{ classNameSingular }}'],
                [Str::ucfirst($class), strtolower(Str::plural($class)), strtolower($class)],
                $this->getStub('Controller')
        );
        if ( ! $this->file->isDirectory(app_path("/Http/Controllers/API"))) {

            $this->file->makeDirectory(app_path("/Http/Controllers/API"), 0777, true, true);

        }
        $path = app_path('/Http/Controllers/API/'.Str::ucfirst($class).'Controller.php');
        if ( ! $this->file->exists($path)) {
            $this->file->put($path, $controllerTemplate);
        }
    }

    protected function model($class)
    {
        $modelTemplate = str_replace(
                ['{{ class }}', '{{ classPlural }}'],
                [Str::ucfirst($class), strtolower(Str::plural($class))],
                $this->getStub('Model')
        );

        if ( ! $this->file->isDirectory(app_path("/Models"))) {
            $this->file->makeDirectory(app_path("/Models"), 0777, true, true);
        }
        $path = app_path('/Models/'.Str::ucfirst($class).'.php');
        if ( ! $this->file->exists($path)) {
            $this->file->put($path, $modelTemplate);
        }
    }

    protected function request($class)
    {
        if ( ! $this->file->isDirectory(app_path('/Http/Requests/'.Str::ucfirst($class)))) {
            $this->file->makeDirectory(app_path('/Http/Requests/'.Str::ucfirst($class)), 0777, true, true);
        }

        $modelTemplate = str_replace(
                ['{{ class }}', '{{ classPlural }}'],
                [Str::ucfirst($class), strtolower(Str::plural($class))],
                $this->getStub('Requests/Create')
        );
        $path          = app_path('/Http/Requests/'.Str::ucfirst($class).'/Create'.Str::ucfirst($class).'Request.php');
        if ( ! $this->file->exists($path)) {
            $this->file->put($path, $modelTemplate);
        }

        $modelTemplate = str_replace(
                ['{{ class }}', '{{ classPlural }}'],
                [Str::ucfirst($class), strtolower(Str::plural($class))],
                $this->getStub('Requests/Update')
        );
        $path          = app_path('/Http/Requests/'.Str::ucfirst($class).'/Update'.Str::ucfirst($class).'Request.php');
        if ( ! $this->file->exists($path)) {
            $this->file->put($path, $modelTemplate);
        }
    }

    protected function resource($class)
    {
        $modelTemplate = str_replace(
                ['{{ class }}', '{{ classPlural }}'],
                [Str::ucfirst($class), strtolower(Str::plural($class))],
                $this->getStub('Resource')
        );
        $path          = app_path('/Http/Resources/'.Str::ucfirst($class).'Resource.php');
        if ( ! $this->file->exists($path)) {
            $this->file->put($path, $modelTemplate);
        }
    }

    protected function migration($class)
    {
        $modelTemplate = str_replace(
                ['{{ class }}', '{{ table }}'],
                ['Create'.Str::ucfirst($class), strtolower(Str::plural($class))],
                $this->getStub('Migration')
        );

        $path = database_path('/migrations/'.$this->getDatePrefix().'_create_'.strtolower($class).'_table.php');
        if (count(glob(database_path('/migrations/*_create_'.strtolower($class).'_table.php'))) === 0) {
            $this->file->put($path, $modelTemplate);
        }
    }

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
