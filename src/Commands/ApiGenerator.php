<?php

namespace limitless\scrud\Commands;

use Illuminate\Console\Command;
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
     * @param  string  $customStubPath
     * @return void
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
     */
    public function handle()
    {
        $class         = $this->argument('class');
        $haveMigration = $this->option('m');

        $this->generator->controller($class);
        $this->generator->model($class);
        $this->generator->request($class);
        $this->generator->resource($class);

        if ($haveMigration == 'm') {
            $this->generator->migration($class);
        }
    }
}
