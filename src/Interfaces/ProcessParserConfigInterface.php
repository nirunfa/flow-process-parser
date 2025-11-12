<?php

namespace Nirunfa\FlowProcessParser\Interfaces;

interface ProcessParserConfigInterface
{
    public function getConfig($key,$default = null);
    public function setConfig($key,$value);
}