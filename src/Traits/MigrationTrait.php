<?php

namespace Nirunfa\FlowProcessParser\Traits;

trait MigrationTrait
{
    protected $dbPrefix ;

    protected function initConfig()
    {
        $this->dbPrefix = config('process_parser.db.prefix');
    }
}