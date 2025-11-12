<?php

namespace Nirunfa\FlowProcessParser\Traits;

trait MigrationTrait
{
    protected $dbPrefix ;

    protected function initConfig()
    {
        $this->dbPrefix = getParserConfig('process_parser.db.prefix');
    }
}