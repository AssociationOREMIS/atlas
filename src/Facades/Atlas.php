<?php

namespace Oremis\Atlas\Facades;

use Illuminate\Support\Facades\Facade;

class Atlas extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'atlas';
    }
}
