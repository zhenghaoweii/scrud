<?php

namespace limitless\scrud\Commands;

use Illuminate\Console\Command;
use limitless\scrud\Classes\ClassesGenerator;

class SyncTableColumns extends Command
{
    protected $generator;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrud:columns {class}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync table columns';

    /**
     * Create a new command instance.
     *
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
        $class = $this->argument('class');

        $this->generator->syncModel($class);
        $this->generator->syncResource($class);
        $this->generator->syncRequest($class);
    }
}
