<?php

namespace Nirunfa\FlowProcessParser\Models;

class NProcessNodeMap extends PivotBaseModel
{
    protected $guarded  =[];

    /**
        * The name of the foreign key column.
        *
        * @var string
        */
    protected $foreignKey = 'next_node_id';

    /**
        * The name of the "other key" column.
        *
        * @var string
        */
    protected $relatedKey = 'node_id';

    public $timestamps = false;
}
