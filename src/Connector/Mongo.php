<?php


namespace Mews\Connector;

use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;
use MongoDB\Driver\Command;
use MongoDB\Driver\WriteConcern;
use MongoDB\Driver\BulkWrite;

class Mongo implements ConnectorInterface
{
    static $instance = null;

    private $client = null;

    private $dbName = '';

    protected $table = '';

    protected $collection;

    private $config = [];

    protected $writeConcern;

    public function __construct(array $config)
    {
        $this->config = $config;
        $timeout = $config['timeout'] ?? 100;
        $this->writeConcern = new WriteConcern(WriteConcern::MAJORITY, $timeout);
        $this->client = $this->connect();
        $this->dbName = $config['db'];
    }

    public static function singleton(array $config)
    {
        if (!static::$instance) {
            static::$instance = new static($config);
        }

        return static::$instance;
    }


    public function connect(): Manager
    {
        return new Manager($this->config['uri'], $this->config['options']);
    }

    public function query($filter, array $options=[])
    {
        $query = new Query($filter, $options);
        $result = $this->client->executeQuery($this->table, $query);
        return $result;
    }

    public function execute($params, $options=[])
    {
        $command = new Command($params, $options);
        $this->client->executeCommand($command);
    }

    public function table(string $table)
    {
        $this->table = $this->dbName . '.' . $table;
    }


    public function find(array $filter=[], array $fields=[])
    {
        return $this->query($filter, $fields);
    }

    public function update(array $filter, array $data, $options=[])
    {

        $bulk = new BulkWrite();
        $bulk->update($filter, $data, $options);
        return $this->client->executeBulkWrite($this->table, $bulk, $this->writeConcern);
    }

    public function insert($data, $options=[])
    {
        $bulk = new BulkWrite();
        $bulk->insert($data, $options);
        return $this->client->executeBulkWrite($this->table, $bulk, $this->writeConcern);
    }

    public function delete($filter, $options=[])
    {
        $bulk = new BulkWrite();
        $bulk->delete($filter, $options);
        return $this->client->executeBulkWrite($this->table, $bulk, $this->writeConcern);
    }

//
//    public function __call($name, $arguments)
//    {
//        if (is_callable([$this->collection, $name])) {
//            return $this->collection->$name($arguments);
//        }
//
//        throw new \Exception('Call invalid method: ' + $name . ' in ' . self::class);
//    }
}