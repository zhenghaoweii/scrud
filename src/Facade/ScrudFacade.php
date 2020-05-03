<?php


namespace limitless\scrud\Facade;


use Illuminate\Support\Facades\Facade;

class ScrudFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'scrud';
    }
}