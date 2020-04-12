<?php


namespace limitless\scrud\Classes;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class ClassesGenerator
{
    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $file;

    public function __construct()
    {
        $this->file = (new Filesystem);
    }

    protected function generate($type, array $payload)
    {
        try {
            $modelTemplate = str_replace(
                    $payload['replace_find'],
                    $payload['replace_replace'],
                    $this->getStub($type)
            );

            switch ($type){
                case 'migration':
                    if ( ! $this->file->isDirectory(database_path($payload['path']))) {
                        $this->file->makeDirectory(database_path($payload['path']), 0777, true, true);
                    }
                    $path = database_path($payload['path'].'/'.$payload['file_name']);

                    if (count(glob(database_path($payload['path'].'/*_create_'.strtolower($payload['class']).'_table.php'))) === 0) {
                        $this->file->put($path, $modelTemplate);
                    }

                    break;
                default:
                    if ( ! $this->file->isDirectory(app_path($payload['path']))) {
                        $this->file->makeDirectory(app_path($payload['path']), 0777, true, true);
                    }
                    $path = app_path($payload['path'].'/'.$payload['file_name']);

                    if ( ! $this->file->exists($path)) {
                        $this->file->put($path, $modelTemplate);
                    }
            }
        }catch (\Exception $e){
            Log::error(json_encode($e));
        }
    }

    public function controller($class)
    {
        $payload = [
                'path'            => '/Http/Controllers/API',
                'class'           => $class,
                'file_name'       => Str::ucfirst($class).'Controller.php',
                'replace_find'    => ['{{ class }}', '{{ classPlural }}'],
                'replace_replace' => [Str::ucfirst($class), strtolower(Str::plural($class)), strtolower($class)],
        ];
        $this->generate('controller', $payload);
    }

    public function model($class)
    {
        $payload = [
                'path'            => $this->getConfig('directory.model'),
                'class'           => $class,
                'file_name'       => Str::ucfirst($class).'.php',
                'replace_find'    => ['{{ class }}', '{{ classPlural }}'],
                'replace_replace' => [Str::ucfirst($class), strtolower(Str::plural($class)), strtolower($class)],
        ];
        $this->generate('model', $payload);
    }

    public function request($class)
    {
        //Create Request
        $payload = [
                'path'            => '/Http/Requests/'.Str::ucfirst($class),
                'class'           => $class,
                'file_name'       => 'Create'.Str::ucfirst($class).'Request.php',
                'replace_find'    => ['{{ class }}', '{{ classPlural }}'],
                'replace_replace' => [Str::ucfirst($class), strtolower(Str::plural($class)), strtolower($class)],
        ];
        $this->generate('requests/create', $payload);

        //Update Request
        $payload = [
                'path'            => '/Http/Requests/'.Str::ucfirst($class),
                'class'           => $class,
                'file_name'       => 'Update'.Str::ucfirst($class).'Request.php',
                'replace_find'    => ['{{ class }}', '{{ classPlural }}'],
                'replace_replace' => [Str::ucfirst($class), strtolower(Str::plural($class)), strtolower($class)],
        ];
        $this->generate('requests/update', $payload);

    }

    public function resource($class)
    {
        $payload = [
                'path'            => '/Http/Resources',
                'class'           => $class,
                'file_name'       => Str::ucfirst($class).'Resource.php',
                'replace_find'    => ['{{ class }}', '{{ classPlural }}'],
                'replace_replace' => [Str::ucfirst($class), strtolower(Str::plural($class)), strtolower($class)],
        ];
        $this->generate('resource', $payload);
    }

    public function migration($class)
    {

        $payload = [
                'path'            => '/migrations',
                'class'           => $class,
                'file_name'       => $this->getDatePrefix().'_create_'.strtolower($class).'_table.php',
                'replace_find'    => ['{{ class }}', '{{ table }}'],
                'replace_replace' => ['Create'.Str::ucfirst($class), strtolower(Str::plural($class))],
        ];
        $this->generate('migration', $payload);
    }


    public function syncModel($class)
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

        $path = $this->getConfig('directory.model').'/'.Str::ucfirst($class).'.php';
        $this->file->put($path, $resources);
    }

    public function syncResource($class)
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

    public function syncRequest($class)
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
    public function getConfig($key = null)
    {
        $file = new Filesystem;

        if ($file->exists(config_path('scrud.php'))) {
            $config = include(config_path('scrud.php'));
        } else {
            $config = include(realpath(__DIR__.'/../../config/config.php'));
        }

        if ($key != null) {
            return Arr::get($config, $key);
        }

        return $config;

    }

    public function getStub($type)
    {
        return file_get_contents(dirname(dirname(__FILE__))."/Stubs/$type.stub");
    }

    public function getColumns($class)
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

    /**
     * Get the date prefix for the migration.
     *
     * @return string
     */
    public function getDatePrefix()
    {
        return date('Y_m_d_His');
    }

}