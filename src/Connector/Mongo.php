<?php


namespace Mews\Connector;

use MongoDB\Collection;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Command;
use MongoDB\Driver\WriteConcern;

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

    public function getCollection(): Collection
    {
        return $this->collection;
    }

    public function query($filter, array $options=[])
    {
        $result = $this->getCollection()->find($filter, $options);
        return $result;
    }

    public function count($filter, array $options=[])
    {
        return $this->getCollection()->count($filter);
    }

    public function execute($params, $options=[])
    {
        $command = new Command($params, $options);
        $this->client->executeCommand($command);
    }

    public function table(string $table)
    {
        $collection = new Collection($this->client, $this->dbName, $table);
        $this->collection = $collection;
        $this->table = $this->dbName . '.' . $table;
        return $collection;
    }


    public function find(array $filter=[], array $fields=[])
    {
        return $this->query($filter, $fields);
    }

    public function update(array $filter, array $data, $options=[])
    {

//        $bulk = new BulkWrite();
//        $bulk->update($filter, $data, $options);
//        return $this->client->executeBulkWrite($this->table, $bulk, $this->writeConcern);
        return $this->getCollection()->updateOne($filter, $data, $options);
    }



    public function insert($data, array $options=[])
    {
        return $this->getCollection()->insertOne($data, $options);
    }

    public function delete($filter, $options=[])
    {
        return $this->getCollection()->deleteOne($filter, $options);
    }


    public function __call($name, $arguments)
    {
        if (is_callable([$this->collection, $name])) {
            return call_user_func_array([$this->collection, $name], $arguments);
        }

        throw new \Exception('Call invalid method: ' + $name . ' in ' . self::class);
    }
}
