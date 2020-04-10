<?php

namespace limitless\scrud\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Str;

class SyncTableColumns extends Command
{
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
        $this->file = new Filesystem;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $class = $this->argument('class');
        $this->syncResource($class);
        $this->syncRequest($class);
        $this->syncModel($class);
    }

    protected function getStub($type)
    {
        return file_get_contents("../Stubs/$type.stub"));
    }

    protected function getColumns($class)
    {
        $file = glob(database_path('/migrations/*_create_'.strtolower($class).'_table.php'))[0];

        $explode = explode('$table->', $this->file->get($file));

        unset($explode[0]);
        $result = collect($explode)->map(function ($item) {
            preg_match("/(.*?)('(.*?)')/", $item, $matched);

            if (isset($matched[3])) {
                return $matched[3];
            }
        })->filter(function ($item) {
            if ($item != null
                    && $item != 'created_by'
                    && $item != 'updated_by'
                    && $item != 'deleted_by'
            ) {
                return $item;
            }
        })->all();

        return $result;
    }

    protected function syncModel($class)
    {
        $resources = str_replace(
                ['{{ class }}', '{{ classPlural }}', '{{ fillable }}'],
                [
                        Str::ucfirst($class),
                        strtolower(Str::plural($class)),
                        collect($this->getColumns($class))->filter(function ($item){
                            return $item != 'id';
                        })->map(function ($item) {
                            return "'$item'";
                        })->implode(',')
                ],
                $this->getStub('Sync/Model')
        );

        $path = app_path('Models/'.Str::ucfirst($class).'.php');
        $this->file->put($path, $resources);
    }

    protected function syncResource($class)
    {

        $resources = str_replace(
                ['{{ class }}', '{{ classPlural }}', '{{ value }}'],
                [
                        Str::ucfirst($class),
                        strtolower(Str::plural($class)),
                        collect($this->getColumns($class))->map(function ($item) {
                            return "'$item'=>\$this->$item,\n";
                        })->implode('')
                ],
                $this->getStub('Sync/Resource')
        );

        $path = app_path('/Http/Resources/'.Str::ucfirst($class).'Resource.php');
        $this->file->put($path, $resources);
    }

    protected function syncRequest($class)
    {
        $modelTemplate = str_replace(
                ['{{ class }}', '{{ classPlural }}','{{ value }}'],
                [
                        Str::ucfirst($class),
                        strtolower(Str::plural($class)),
                        collect($this->getColumns($class))->filter(function ($item){
                            return $item != 'id';
                        })->map(function ($item) {
                            return "'$item'=>'required',\n";
                        })->implode('')
                ],
                $this->getStub('Sync/Requests/Create')
        );
        $path          = app_path('/Http/Requests/'.Str::ucfirst($class).'/Create'.Str::ucfirst($class).'Request.php');
        $this->file->put($path, $modelTemplate);


        $modelTemplate = str_replace(
                ['{{ class }}', '{{ classPlural }}','{{ value }}'],
                [
                        Str::ucfirst($class),
                        strtolower(Str::plural($class)),
                        collect($this->getColumns($class))->filter(function ($item){
                            return $item != 'id';
                        })->map(function ($item) {
                            return "'$item'=>'required',\n";
                        })->implode('')
                ],
                $this->getStub('Sync/Requests/Update')
        );

        $path = app_path('/Http/Requests/'.Str::ucfirst($class).'/Update'.Str::ucfirst($class).'Request.php');
        $this->file->put($path, $modelTemplate);
    }
}
