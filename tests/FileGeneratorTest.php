<?php

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

use \Illuminate\Filesystem\Filesystem;
use \limitless\scrud\Classes\ClassesGenerator;

class FileGeneratorTest extends TestCase
{
    protected $file;
    protected $class;
    protected $generator;
    protected $log;

    protected static function boot()
    {
        parent::boot();

    }


    protected function setUp()
    {
        parent::setUp();
        $this->file      = (new Filesystem);
        $this->generator = (new ClassesGenerator);
        $this->class     = 'haowei';

        system("rm -rf ".escapeshellarg(__DIR__.'/../application'));
        $this->file->makeDirectory('application', 0777);

        new Illuminate\Foundation\Application(realpath(__DIR__.'/../application'));
    }

    protected function tearDown()
    {
        parent::tearDown();
        system("rm -rf ".escapeshellarg(__DIR__.'/../application'));
        new Illuminate\Foundation\Application(null);
    }


    /** @test */
    public function default_generator()
    {
        $payload = [
                'path'            => '/Http/Controllers/API/',
                'class'           => $this->class,
                'stub'            => 'Controller',
                'file_name'       => Str::ucfirst($this->class).'Controller.php',
                'replace_find'    => ['{{ class }}', '{{ classPlural }}'],
                'replace_replace' => [
                        Str::ucfirst($this->class), strtolower(Str::plural($this->class)), strtolower($this->class)
                ],
        ];

        (new ClassesGenerator)->generate('controller', $payload);

        $this->checkFileExist($payload);
    }

    /** @test */
    public function migration_generator()
    {
        $payload = [
                'path'            => '/migrations',
                'class'           => $this->class,
                'stub'            => 'Migration',
                'file_name'       => date('Y_m_d_His').'_create_'.strtolower($this->class).'_table.php',
                'replace_find'    => ['{{ class }}', '{{ table }}'],
                'replace_replace' => ['Create'.Str::ucfirst($this->class), strtolower(Str::plural($this->class))],
        ];

        (new ClassesGenerator)->generate('migration', $payload);

        $files = glob(database_path('/migrations/*_create_'.strtolower($this->class).'_table.php'));
        $found = (count($files) > 0) ? true : false;

        $this->assertSame($found, true);
    }

    /**
     * @test
     * @throws FileNotFoundException
     */
    public function sync_column_model()
    {
        $class = 'sync_model';
        $this->init_migration_test_file($class);
        $payload    = $this->init_model_for_sync_columns_test($class);
        $getColumns = (new ClassesGenerator)->generateModelFillable($class);

        (new ClassesGenerator)->syncModel($class);

        $file = $this->file->get(app_path($payload['path'].'/'.$payload['file_name']));

        $found = (strpos($file, $getColumns) !== false) ? true : false;

        $this->assertSame($found, true);
    }

    /**
     * @test
     * @throws FileNotFoundException
     */
    public function sync_column_resource()
    {
        $class = 'sync_resource';
        $this->init_migration_test_file($class);
        $payload = $this->init_resource_for_sync_columns_test($class);
        $value   = (new ClassesGenerator)->generateResourceValue($class);

        (new ClassesGenerator)->syncResource($class);

        $file = $this->file->get(app_path($payload['path'].'/'.$payload['file_name']));

        $found = (strpos($file, $value) !== false) ? true : false;

        $this->assertSame($found, true);
    }

    /**
     * @test
     */
    public function sync_column_request()
    {
        $class = 'sync_request';
        $this->init_migration_test_file($class);
        $this->process_column_request($class, 'Create');
        $this->process_column_request($class, 'Update');
    }

    protected function process_column_request($class, $type)
    {
        $payload = $this->init_request_sync_columns_test($class, $type);
        $value   = (new ClassesGenerator)->generateRequestValue($class);
        (new ClassesGenerator)->syncRequest($class);
        $request_file = [];
        try {
            $request_file = $this->file->get(app_path($payload['path'].'/'.$payload['file_name']));
        } catch (FileNotFoundException $e) {
            throw new FileNotFoundException($e);
        }

        $found = (strpos($request_file, $value) !== false) ? true : false;
        $this->assertSame($found, true);
    }

    protected function init_migration_test_file($class)
    {
        $payload = [
                'path'            => '/migrations',
                'class'           => $class,
                'stub'            => 'ExampleTest/Migration',
                'file_name'       => date('Y_m_d_His').'_create_'.strtolower($class).'_table.php',
                'replace_find'    => ['{{ class }}', '{{ table }}'],
                'replace_replace' => ['Create'.Str::ucfirst($class), strtolower(Str::plural($class))],
        ];

        (new ClassesGenerator)->generate('migration', $payload);

        $found = false;
        if (count(glob(database_path('/migrations/*_create_'.strtolower($class).'_table.php'))) > 0) {
            $found = true;
        }

        $this->assertSame($found, true);
    }

    protected function init_model_for_sync_columns_test($class)
    {
        $payload = [];
        try {
            $payload = [
                    'path'            => (new ClassesGenerator)->getConfig('directory.model'),
                    'class'           => $class,
                    'stub'            => 'Model',
                    'file_name'       => Str::ucfirst($class).'.php',
                    'replace_find'    => ['{{ class }}', '{{ classPlural }}'],
                    'replace_replace' => [Str::ucfirst($class), strtolower(Str::plural($class)), strtolower($class)],
            ];
        } catch (Exception $e) {
            throw new Exception($e);
        }

        (new ClassesGenerator)->generate('model', $payload);

        $this->checkFileExist($payload);

        return $payload;
    }

    protected function init_resource_for_sync_columns_test($class)
    {
        $payload = [
                'path'            => '/Http/Resources',
                'class'           => $class,
                'stub'            => 'Resource',
                'file_name'       => Str::ucfirst($class).'Resource.php',
                'replace_find'    => ['{{ class }}', '{{ classPlural }}'],
                'replace_replace' => [Str::ucfirst($class), strtolower(Str::plural($class)), strtolower($class)],
        ];

        (new ClassesGenerator)->generate('resource', $payload);

        $this->checkFileExist($payload);

        return $payload;
    }

    protected function init_request_sync_columns_test($class, $type)
    {

        $payload = [
                'path'            => '/Http/Requests/'.Str::ucfirst($class),
                'class'           => $class,
                'stub'            => 'Requests/'.$type,
                'file_name'       => $type.Str::ucfirst($class).'Request.php',
                'replace_find'    => ['{{ class }}', '{{ classPlural }}'],
                'replace_replace' => [Str::ucfirst($class), strtolower(Str::plural($class)), strtolower($class)],
        ];

        (new ClassesGenerator)->generate('request', $payload);

        $this->checkFileExist($payload);

        return $payload;
    }

    protected function checkFileExist($payload)
    {
        $path = app_path($payload['path'].'/'.$payload['file_name']);
        $this->assertFileExists($path);
    }


}