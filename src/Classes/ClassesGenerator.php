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
     * @throws Exception
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

                    if (count(glob(database_path($payload['path'].'/*_create_'.strtolower(Str::snake($payload['class'])).'_table.php'))) === 0) {
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
            throw new Exception($e);
        }
    }

    /**
     * @param $class
     * @throws Exception
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
     * @throws Exception
     */
    public function model($class)
    {
        $payload = [
                'path'            => $this->getConfig('directory.model'),
                'class'           => $class,
                'stub'            => 'Model',
                'file_name'       => Str::ucfirst($class).'.php',
                'replace_find'    => ['{{ class }}', '{{ classPlural }}'],
                'replace_replace' => [
                        Str::ucfirst($class), strtolower(Str::snake(Str::plural($class))), strtolower($class)
                ],
        ];
        $this->generate('model', $payload);
    }

    /**
     * @param $class
     * @throws Exception
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
     * @throws Exception
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
     * @throws Exception
     */
    public function migration($class)
    {

        $payload = [
                'path'            => '/migrations',
                'class'           => $class,
                'stub'            => 'Migration',
                'file_name'       => $this->getDatePrefix().'_create_'.strtolower(Str::snake($class)).'_table.php',
                'replace_find'    => ['{{ class }}', '{{ table }}'],
                'replace_replace' => ['Create'.Str::ucfirst($class), strtolower(Str::snake(Str::plural($class)))],
        ];
        $this->generate('migration', $payload);

        return $payload['file_name'];
    }

    /**
     * @param $class
     * @throws FileNotFoundException
     * @throws Exception
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
                        Str::ucfirst($class), strtolower(Str::snake(Str::plural($class))),
                        $this->generateModelFillable($class)
                ],
        ];
        $this->generate('model', $payload, true);
    }

    /**
     * @param $class
     * @throws FileNotFoundException
     * @throws Exception
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
     * @throws FileNotFoundException
     * @throws Exception
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
            return $item != 'id' && $item != 'timestamps';
        })->map(function ($item) {
            return "'$item'";
        })->implode(',');
    }

    /**
     * @param $class
     * @return string
     * @throws FileNotFoundException
     */
    public function generateRequestValue($class)
    {
        try {
            return collect($this->getColumns($class))->filter(function ($item) {
                return $item['type'] != 'id' && $item['type'] != 'timestamps';
            })->map(function ($item, $index) {

                $value = [];
                if ( ! $item['nullable']) {
                    $value[] = 'required';
                }
                switch ($item['type']) {
                    case 'string':
                        if (isset($item['max']) && $item['max'] > 0) {
                            $value[] = 'max:'.$item['max'];
                        }
                        break;
                    case 'integer':
                    case 'smallInteger':
                    case 'tinyInteger':
                    case 'bigInteger':
                    case 'mediumInteger':
                    case 'unsignedInteger':
                    case 'unsignedSmallInteger':
                    case 'unsignedTinyInteger':
                    case 'unsignedBigInteger':
                    case 'unsignedMediumInteger':
                    case 'unsignedDecimal':
                    case 'unsignedDouble':
                    case 'unsignedFloat':
                    case 'foreignId':
                        $value[] = 'numeric';
                        if (isset($item['unsigned']) && $item['unsigned']) {
                            $value[] = 'min:0';
                        }
                        break;
                    case 'boolean':
                        $value[] = 'boolean';
                        break;
                    case 'enum':
                        $options = implode(',', $item['options']);
                        $value[] = 'in:'.$options;
                        break;
                    default:
                        return "'$index'=>'required'";
                }

                $value = collect($value)->implode('|');

                return "'$index'=>'$value'";
            })->implode(',');
        } catch (FileNotFoundException $e) {
            throw new FileNotFoundException($e);
        }
    }

    /**
     * @param $class
     * @return string
     * @throws FileNotFoundException
     */
    public function generateResourceValue($class)
    {
        try {
            $value = [];
            collect($this->getColumns($class))->map(function ($item, $index) use (&$value) {
                if ($index == 'timestamps') {
                    $value[] = "'created_at'=>\$this->created_at";
                    $value[] = "'created_by'=>\$this->created_by";
                    $value[] = "'updated_at'=>\$this->updated_at";
                    $value[] = "'updated_by'=>\$this->updated_by";
                } else {
                    $value[] = "'$index'=>\$this->$index";
                }
            });
        } catch (FileNotFoundException $e) {
            throw new FileNotFoundException($e);
        }

        $value = collect($value)->implode(',');

        return $value;
    }

    /**
     * @param  string  $key
     * @return mixed
     * @throws Exception
     */
    public function getConfig($key = '')
    {
        try {
            $published = config_path('scrud.php');
            $default   = realpath(__DIR__.'/../../config/config.php');
            $include   = ($this->file->exists($published)) ? $published : $default;
            $config    = include($include);

            if ($key != null && is_array($config)) {
                return Arr::get($config, $key);
            }
        } catch (Exception $e) {
            throw new Exception($e);
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
        $file = glob(database_path('/migrations/*_create_'.strtolower(Str::snake($class)).'_table.php'));
        if (count($file) < 1) {
            throw new FileNotFoundException('Migration file not found');
        }

        $list = preg_grep('/table->(.*?);/', explode("\n", $this->file->get($file[0])));

        $columns = collect($list)->mapWithKeys(function ($item) {
            return $this->getBluePrint(trim($item));
        })->all();

        return $columns;
    }

    public function getColumnType($item)
    {
        $before = strtok(trim($item), '(');
        $after  = substr(strstr($before, '>'), 1);

        return $after;
    }

    public function getColumnName($item)
    {
        $before = substr(strstr($item, "('"), 1);
        $after  = strtok(trim($before), "'");

        return $after;
    }

    public function getColumnDefaultValue($item)
    {
        if ( ! strpos($item, 'default(')) {
            return false;
        }
        $before = substr(strstr($item, "default('"), 9);
        $after  = strtok(trim($before), "'");

        return $after;
    }

    public function getColumnClosureValue($item, $closure)
    {
        if ( ! strpos($item, "$closure(")) {
            return false;
        }
        $before = substr(strstr($item, "$closure('"), 9);
        $after  = strtok(trim($before), "'");

        return $after;
    }

    public function getColumnStringLength($item)
    {
        $before = substr(strstr($item, "',"), 2);
        $after  = strtok(trim($before), ")");

        if ((int) $after < 1) {
            return false;
        }

        return (int) $after;
    }

    public function getBluePrint($item)
    {
        $type     = $this->getColumnType($item);
        $name     = $this->getColumnName($item);
        $nullable = strpos($item, 'nullable()');

        $column = [
                'type'     => $type,
                'nullable' => ($nullable > 0) ? true : false
        ];

        switch ($type) {
            case 'id':
            case 'uuid':
            case 'integerIncrements':
            case 'smallIncrements':
            case 'tinyIncrements':
            case 'bigIncrements':
            case 'mediumIncrements':
            case 'increments':
                $name = $type;
                break;
            case 'timestamps':
                $name = $type;
                break;
            case 'enum':
                preg_match('/\[(.*?)]/', $item, $matched);
                $enum              = array_map('trim', explode(',', $matched[1]));
                $column['options'] = str_replace("'", '', $enum);
                break;
            case 'string':
                $column['max'] = $this->getColumnStringLength($item);
                break;
            case 'integer':
            case 'smallInteger':
            case 'tinyInteger':
            case 'bigInteger':
            case 'mediumInteger':
                $column['unsigned'] = ! strpos($item, 'unsigned()');
                break;
            case 'unsignedInteger':
            case 'unsignedSmallInteger':
            case 'unsignedTinyInteger':
            case 'unsignedBigInteger':
            case 'unsignedMediumInteger':
            case 'unsignedDecimal':
            case 'unsignedDouble':
            case 'unsignedFloat':
            case 'foreignId':
                $column['unsigned'] = true;
                break;
            default:

        }

        return [$name => $column];
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

    /**
     * @param $class
     * @return array
     * @throws Exception
     */
    public function checkFilesExisting($class)
    {
        $class = Str::ucfirst($class);

        try {
            $toCheck = [
                    'controller'     => '/Http/Controllers/API/'.$class.'Controller.php',
                    'model'          => $this->getConfig('directory.model').'/'.$class.'.php',
                    'create request' => '/Http/Requests/'.$class.'/Create'.$class.'Request.php',
                    'update request' => '/Http/Requests/'.$class.'/Create'.$class.'Request.php',
                    'resource'       => '/Http/Resources/'.$class.'Resource.php',
            ];
        } catch (Exception $e) {
            throw new Exception($e);
        }

        $existed = collect($toCheck)->filter(function ($item) {
            if ($this->file->exists(app_path($item))) {
                return true;
            }

            return false;
        })->keys()->all();

        $last  = array_slice($existed, -1);
        $first = join(', ', array_slice($existed, 0, -1));
        $both  = array_filter(array_merge(array($first), $last), 'strlen');

        return join(' and ', $both);

    }

}