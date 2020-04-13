<?php

namespace limitless\scrud\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use limitless\scrud\Classes\ClassesGenerator;

class ApiGenerator extends Command
{
    protected $generator;
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
     */
    public function __construct()
    {
        parent::__construct();

        $this->generator = (new ClassesGenerator);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws Exception
     */
    public function handle()
    {
        $class         = $this->argument('class');
        $haveMigration = $this->option('m');

        $existed = $this->generator->checkFilesExisting($class);

        $continue = false;
        if($existed != ''){
            if ($this->confirm('There\'s existing files with same class name in '.$existed.'. Do you wish to continue? (it will overwrite the existing files)')) {
                $continue = true;
            }
        }

        if($continue || $existed == ''){
            $this->generator->controller($class);
            $this->info('Generated controller : '.Str::ucfirst($class).'Controller.php');
            $this->generator->model($class);
            $this->info('Generated model : '.Str::ucfirst($class).'.php');
            $this->generator->request($class);
            $this->info('Generated create request : Create'.Str::ucfirst($class).'Request.php');
            $this->info('Generated update request : Update'.Str::ucfirst($class).'Request.php');
            $this->generator->resource($class);
            $this->info('Generated resource : Update'.Str::ucfirst($class).'Resource.php');

            if ($haveMigration == 'm') {
                $migration = $this->generator->migration($class);
                $this->info('Generated migration : '.$migration);
            }
        }
    }
}
