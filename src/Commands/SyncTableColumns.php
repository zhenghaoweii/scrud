<?php

namespace limitless\scrud\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Str;
use Arr;

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
        $this->syncModel($class);
        $this->syncResource($class);
        $this->syncRequest($class);
    }

    protected function getStub($type)
    {
        return file_get_contents(dirname(dirname(__FILE__))."/Stubs/$type.stub");
    }

    protected function getColumns($class)
    {
        $file = glob(database_path('/migrations/*_create_'.strtolower($class).'_table.php'))[0];

        preg_match_all('/table->(.*?)\(\'(.*?)\'\)/', $this->file->get($file), $matched);

        $merged  = collect($matched[2])->combine($matched[1])->toArray();
        $columns = [];
        collect($merged)->each(function ($item, $index) use (&$columns) {
            if ($item == 'enum') {
                preg_match('/\[(.*?)\]/', $index, $matched);
                $enum               = array_map('trim', explode(',', $matched[1]));
                $index              = explode("',", $index);
                $columns[$index[0]] = [
                        'value'   => $item,
                        'options' => str_replace("'", '', $enum),
                ];
            } else {
                $columns[$index] = $item;
            }
        })->all();

        return $columns;
    }

    protected function syncModel($class)
    {
        $resources = str_replace(
                ['{{ class }}', '{{ classPlural }}', '{{ fillable }}'],
                [
                        Str::ucfirst($class),
                        strtolower(Str::plural($class)),
                        collect($this->getColumns($class))->keys()->filter(function ($item) {
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
        $value = collect($this->getColumns($class))->keys()->map(function ($item) {
            return "'$item'=>\$this->$item";
        })->implode(',');

        $resources = str_replace(
                ['{{ class }}', '{{ classPlural }}', '{{ value }}'],
                [
                        Str::ucfirst($class),
                        strtolower(Str::plural($class)),
                        $value
                ],
                $this->getStub('Sync/Resource')
        );

        $path = app_path('/Http/Resources/'.Str::ucfirst($class).'Resource.php');
        $this->file->put($path, $resources);
    }

    protected function syncRequest($class)
    {
        $value = collect($this->getColumns($class))->filter(function ($item, $index) {
            return $index != 'id';
        })->map(function ($item, $index) {
            if (isset($item['value']) && $item['value'] == 'enum') {
                $options = implode(',', $item['options']);

                return "'$index'=>'required|in:$options'";
            }
            switch ($item) {
                case 'bigInteger':
                    return "'$index'=>'required|numeric'";
                    break;
                case 'boolean':
                    return "'$index'=>'required|boolean'";
                    break;
                default:
                    return "'$index'=>'required'";
            }
        })->implode(',');

        $modelTemplate = str_replace(
                ['{{ class }}', '{{ classPlural }}', '{{ value }}'],
                [
                        Str::ucfirst($class),
                        strtolower(Str::plural($class)),
                        $value
                ],
                $this->getStub('Sync/Requests/Create')
        );
        $path          = app_path('/Http/Requests/'.Str::ucfirst($class).'/Create'.Str::ucfirst($class).'Request.php');
        $this->file->put($path, $modelTemplate);



        $modelTemplate = str_replace(
                ['{{ class }}', '{{ classPlural }}', '{{ value }}'],
                [
                        Str::ucfirst($class),
                        strtolower(Str::plural($class)),
                        $value
                ],
                $this->getStub('Sync/Requests/Update')
        );

        $path = app_path('/Http/Requests/'.Str::ucfirst($class).'/Update'.Str::ucfirst($class).'Request.php');
        $this->file->put($path, $modelTemplate);
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
