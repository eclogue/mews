<?php
/**
 * @license   https://github.com/Init/licese.md
 * @copyright Copyright (c) 2017
 * @author    : bugbear
 * @date      : 2017/3/12
 * @time      : 下午3:15
 */

namespace Mews;


class Table
{
    private $table = '';

    private $builder;

    public $db;
    public function __construct(DB $db)
    {
        $this->builder = new Builder();
        $this->db = $db;
    }


    public static function table($db)
    {
        $instance = new static($db);
        $instance->builder->table($instance->table);
    }

}