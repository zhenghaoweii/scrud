<?php


namespace limitless\scrud\Classes;

use Exception;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class ClassesGenerator
{
    /**
     * The filesystem instance.
     *
     * @var Filesystem
     */
    protected $file;

    /**
     * ClassesGenerator constructor.
     */
    public function __construct()
    {
        $this->file = (new Filesystem);
    }

    /**
     * @param  string  $type
     * @param  array  $payload
     * @param  bool  $sync
     */
    public function generate($type, array $payload, $sync = false)
    {
        try {
            $modelTemplate = str_replace(
                    $payload['replace_find'],
                    $payload['replace_replace'],
                    $this->getStub($payload['stub'])
            );

            switch ($type) {
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

                    if ( ! $this->file->exists($path) || $sync) {
                        $this->file->put($path, $modelTemplate);
                    }
            }
        } catch (Exception $e) {
            dd($e);
        }
    }

    /**
     * @param $class
     */
    public function controller($class)
    {
        $payload = [
                'path'            => '/Http/Controllers/API',
                'class'           => $class,
                'stub'            => 'Controller',
                'file_name'       => Str::ucfirst($class).'Controller.php',
                'replace_find'    => ['{{ class }}', '{{ classPlural }}'],
                'replace_replace' => [Str::ucfirst($class), strtolower(Str::plural($class)), strtolower($class)],
        ];
        $this->generate('controller', $payload);
    }

    /**
     * @param $class
     */
    public function model($class)
    {
        $payload = [
                'path'            => $this->getConfig('directory.model'),
                'class'           => $class,
                'stub'            => 'Model',
                'file_name'       => Str::ucfirst($class).'.php',
                'replace_find'    => ['{{ class }}', '{{ classPlural }}'],
                'replace_replace' => [Str::ucfirst($class), strtolower(Str::plural($class)), strtolower($class)],
        ];
        $this->generate('model', $payload);
    }

    /**
     * @param $class
     */
    public function request($class)
    {
        //Create Request
        $payload = [
                'path'            => '/Http/Requests/'.Str::ucfirst($class),
                'class'           => $class,
                'stub'            => 'Requests/Create',
                'file_name'       => 'Create'.Str::ucfirst($class).'Request.php',
                'replace_find'    => ['{{ class }}', '{{ classPlural }}'],
                'replace_replace' => [Str::ucfirst($class), strtolower(Str::plural($class)), strtolower($class)],
        ];
        $this->generate('requests', $payload);

        //Update Request
        $payload = [
                'path'            => '/Http/Requests/'.Str::ucfirst($class),
                'class'           => $class,
                'stub'            => 'Requests/Update',
                'file_name'       => 'Update'.Str::ucfirst($class).'Request.php',
                'replace_find'    => ['{{ class }}', '{{ classPlural }}'],
                'replace_replace' => [Str::ucfirst($class), strtolower(Str::plural($class)), strtolower($class)],
        ];
        $this->generate('requests', $payload);

    }

    /**
     * @param $class
     */
    public function resource($class)
    {
        $payload = [
                'path'            => '/Http/Resources',
                'class'           => $class,
                'stub'            => 'Resource',
                'file_name'       => Str::ucfirst($class).'Resource.php',
                'replace_find'    => ['{{ class }}', '{{ classPlural }}'],
                'replace_replace' => [Str::ucfirst($class), strtolower(Str::plural($class)), strtolower($class)],
        ];
        $this->generate('resource', $payload);
    }

    /**
     * @param $class
     */
    public function migration($class)
    {

        $payload = [
                'path'            => '/migrations',
                'class'           => $class,
                'stub'            => 'Migration',
                'file_name'       => $this->getDatePrefix().'_create_'.strtolower($class).'_table.php',
                'replace_find'    => ['{{ class }}', '{{ table }}'],
                'replace_replace' => ['Create'.Str::ucfirst($class), strtolower(Str::plural($class))],
        ];
        $this->generate('migration', $payload);
    }

    /**
     * @param $class
     * @throws FileNotFoundException
     */
    public function syncModel($class)
    {
        $payload = [
                'path'            => $this->getConfig('directory.model'),
                'class'           => $class,
                'stub'            => 'Sync/Model',
                'file_name'       => Str::ucfirst($class).'.php',
                'replace_find'    => ['{{ class }}', '{{ classPlural }}', '{{ fillable }}'],
                'replace_replace' => [
                        Str::ucfirst($class), strtolower(Str::plural($class)), $this->generateModelFillable($class)
                ],
        ];
        $this->generate('model', $payload, true);
    }

    /**
     * @param $class
     */
    public function syncResource($class)
    {
        $payload = [
                'path'            => '/Http/Resources',
                'class'           => $class,
                'stub'            => 'Sync/Resource',
                'file_name'       => Str::ucfirst($class).'Resource.php',
                'replace_find'    => ['{{ class }}', '{{ classPlural }}', '{{ value }}'],
                'replace_replace' => [
                        Str::ucfirst($class), strtolower(Str::plural($class)), $this->generateResourceValue($class)
                ],
        ];
        $this->generate('resource', $payload, true);
    }

    /**
     * @param $class
     */
    public function syncRequest($class)
    {
        $payload = [
                'path'            => '/Http/Requests/'.Str::ucfirst($class),
                'class'           => $class,
                'stub'            => 'Sync/Requests/Create',
                'file_name'       => 'Create'.Str::ucfirst($class).'Request.php',
                'replace_find'    => ['{{ class }}', '{{ classPlural }}', '{{ value }}'],
                'replace_replace' => [
                        Str::ucfirst($class), strtolower(Str::plural($class)),
                        $this->generateRequestValue($class)
                ],
        ];

        $this->generate('model', $payload, true);

        $payload = [
                'path'            => '/Http/Requests/'.Str::ucfirst($class),
                'class'           => $class,
                'stub'            => 'Sync/Requests/Update',
                'file_name'       => 'Update'.Str::ucfirst($class).'Request.php',
                'replace_find'    => ['{{ class }}', '{{ classPlural }}', '{{ value }}'],
                'replace_replace' => [
                        Str::ucfirst($class), strtolower(Str::plural($class)),
                        $this->generateRequestValue($class)
                ],
        ];

        $this->generate('model', $payload, true);
    }

    /**
     * @param $class
     * @return string
     * @throws FileNotFoundException
     */
    public function generateModelFillable($class)
    {
        return collect($this->getColumns($class))->keys()->filter(function ($item) {
            return $item != 'id';
        })->map(function ($item) {
            return "'$item'";
        })->implode(',');
    }

    public function generateRequestValue($class)
    {
        $value = '';
        try {
            $value = collect($this->getColumns($class))->filter(function ($index) {
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
        } catch (FileNotFoundException $e) {
            dd($e);
        }

        return $value;
    }

    public function generateResourceValue($class)
    {
        $value = '';
        try {
            $value = collect($this->getColumns($class))->keys()->map(function ($item) {
                return "'$item'=>\$this->$item";
            })->implode(',');
        } catch (FileNotFoundException $e) {
            dd($e);
        }

        return $value;
    }

    /**
     * @param  string  $key
     * @return mixed
     */
    public function getConfig($key = '')
    {
        $include = ($this->file->exists(config_path('scrud.php'))) ? config_path('scrud.php') : realpath(__DIR__.'/../../config/config.php');
        $config  = fopen($include,"r");

        if ($key != null) {
            return Arr::get($config, $key);
        }

        return $config;

    }

    /**
     * @param $type
     * @return string
     * @throws FileNotFoundException
     */
    public function getStub($type)
    {
        return $this->file->get(dirname(dirname(__FILE__))."/Stubs/$type.stub");
    }

    /**
     * @param $class
     * @return array
     * @throws FileNotFoundException
     */
    public function getColumns($class)
    {

        $file = glob(database_path('/migrations/*_create_'.strtolower($class).'_table.php'));
        if (count($file) < 1) {
            dd('no migration file');
        }

        preg_match_all('/table->(.*?)\(\'(.*?)\'\)/', $this->file->get($file[0]), $matched);

        $merged  = collect($matched[2])->combine($matched[1])->toArray();
        $columns = [];
        collect($merged)->each(function ($item, $index) use (&$columns) {
            if ($item == 'enum') {
                preg_match('/\[(.*?)]/', $index, $matched);
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