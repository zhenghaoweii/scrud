<?php

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
        $this->file->makeDirectory('application', 0777, true, true);

        $app = new Illuminate\Foundation\Application(realpath(__DIR__.'/../application'));
    }
    protected function tearDown()
    {
        parent::tearDown();
        system("rm -rf ".escapeshellarg(__DIR__.'/../application'));
        new Illuminate\Foundation\Application(null);
    }

    /** @test */
    public function generate_controller()
    {
        (new ClassesGenerator)->controller($this->class);

        $path = app_path('/Http/Controllers/API/'.Str::ucfirst($this->class).'Controller.php');
        $this->assertFileExists($path);
    }

    /** @test */
    public function generate_model()
    {
        (new ClassesGenerator)->model($this->class);
        $path = app_path($this->generator->getConfig('directory.model').'/'.Str::ucfirst($this->class).'.php');
        $this->assertFileExists($path);

    }

    /** @test */
    public function generate_request()
    {
        (new ClassesGenerator)->request($this->class);
        $path = app_path('/Http/Requests/'.Str::ucfirst($this->class).'/Create'.Str::ucfirst($this->class).'Request.php');
        $this->assertFileExists($path);

        $path = app_path('/Http/Requests/'.Str::ucfirst($this->class).'/Update'.Str::ucfirst($this->class).'Request.php');
        $this->assertFileExists($path);
    }

    /** @test */
    public function generate_resource()
    {
        (new ClassesGenerator)->resource($this->class);
        $path = app_path('/Http/Resources/'.Str::ucfirst($this->class).'Resource.php');
        $this->assertFileExists($path);
    }

    /** @test */
    public function generate_migration()
    {
        (new ClassesGenerator)->migration($this->class);

        $found = false;
        if (count(glob(database_path('/migrations/*_create_'.strtolower($this->class).'_table.php'))) > 0) {
            $found = true;
        }

        $this->assertSame($found, true);
    }

    /** test */
    public function sync_column()
    {
        $class= 'syncMigration';

        $this->init_migration_test_file($class);
    }


    protected function init_migration_test_file($class){
        if ( ! $this->file->isDirectory(database_path('/migrations'))) {
            $this->file->makeDirectory(database_path('/migrations'), 0777, true, true);
        }

        $modelTemplate = str_replace(
                ['{{ class }}', '{{ table }}'],
                ['Create'.Str::ucfirst($class), strtolower(Str::plural($class))],
                (new ClassesGenerator)->getStub('ExampleTest/Migration')
        );

        $path = database_path('/migrations/'.(new ClassesGenerator)->getDatePrefix().'_create_'.strtolower($class).'_table.php');
        if (count(glob(database_path('/migrations/*_create_'.strtolower($class).'_table.php'))) === 0) {
            $this->file->put($path, $modelTemplate);
        }
    }


}