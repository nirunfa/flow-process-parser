<?php

namespace Nirunfa\FlowProcessParser;

use Illuminate\Support\Facades\Facade;

class ProcessParserFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'processParser';
    }
}