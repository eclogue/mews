<?php


namespace Mews;

use Mews\Connector\Mongo;
use Mews\Connector\Mysql;

class Connector
{
    public $config = [];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getConnection($transactionId)
    {
        if ($this->config['type'] === 'mysql') {
            return Mysql::singleton($this->config['servers']);
        } else if($this->config['type'] === 'mongodb') {
            return Mongo::singleton($this->config);
        }
    }
}