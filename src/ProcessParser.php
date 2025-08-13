<?php

namespace Nirunfa\FlowProcessParser;

use Illuminate\Config\Repository;

class ProcessParser
{
    protected $config;
    /**
     * 构造方法
     */
    public function __construct(Repository $config)
    {
        $this->config = $config->get('unlimited');
    }
    public function getUnlimited($data)
    {
        $parent_key=$this->config['parent_key'];
        $child_key=$this->config['child_key'];
        $data = array_column($data, null, 'id');
        $tree = [];
        foreach ($data as $key => $val) {
            if ($val[$parent_key] == 0) {
                $tree[] = &$data[$key];
            } else {
                $data[$val[$parent_key]][$child_key][] = &$data[$key];
            }
        }
        return $tree;
    }
}