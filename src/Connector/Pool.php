<?php
/**
 * @license MIT
 * @copyright Copyright (c) 2017
 * @author: bugbear
 * @date: 2017/7/17
 * @time: 下午1:10
 */

namespace Mews\Connector;

use RuntimeException;
use InvalidArgumentException;
use SplQueue;

class Pool implements ConnectorInterface
{
    /**
     * free connections
     *
     * @var array
     */
    private $freeConnections = [];

    /**
     * active connections 
     * @var array
     */
    private $activeConnections = [];

    /**
     * transcation manager
     * 
     * @var array
     */
    private $transactionManager = [];

    /**
     * is connection pool close
     *
     * @var boolean
     */
    private $closed = true;

    /**
     * connection config
     *
     * @var array
     */
    private $config = [];

    /**
     * singleton instance
     *
     * @var static
     */
    private static $instance;
    
    private $poolSize = -1;

    private $minxPoolSize = 10;
    /**
     * store all connections
     *
     * @var array
     */
    private $allConnections = [];


    public function __construct($config)
    {
        $this->config = $config;
        $this->freeConnections = new SplQueue();
        $this->activeConnections = [];
        if (isset($config['poolSize'])) {
            $this->poolSize = $config['poolSize'];
        }
        $this->flag = uniqid();
        $this->uuid = uniqid();
    }

    /**
     * get pool sigleton
     *
     * @param array $config
     * @return static
     */
    public static function singleton(array $config)
    {
        if (!self::$instance) {
            self::$instance = new self($config);
        }

        return self::$instance;
    }
    /**
     * get connection from connection pool
     * 
     * @param mixed $identify
     * @return ConnectorInterface
     */
    public function connect($identify = null)
    {
        if (!$this->closed) {
            throw new RuntimeException('Connection pool is closed');
        }

        $connection = null;
        if ($identify) {
            if (in_array($identify, $this->activeConnections)) {
                return $this->allConnections[$identify];
            }
        }
        $active = count($this->allConnections);
        if ($active < $this->minxPoolSize) {
            return $this->acquireConnection();
        }
        while (!empty($this->freeConnections)) {
            $index = $this->freeConnections->dequeue();
            $connection = $this->allConnections[$index];
            if (!$connection) {
                unset($this->allConnections[$index]);
                continue;
            }
            if ($connection->isClose()) {
                $this->reconnect($connection);
                continue;
            }
            $this->activeConnections[] = $connection->identify;
            break;
        }
        if (!$connection) {
            if ($this->poolSize !== -1 && $active >= $this->poolSize) {
                throw new RuntimeException('Connection pool ...'); // @fixme
            }
            $connection = $this->acquireConnection();
        }

        if ($identify === true) {
            $this->addTransaction($connection->identify);
        }

        return $connection;
    }

    /**
     * apply new connection
     *
     * @return Mysql
     */
    private function acquireConnection()
    {
        $connection = new Mysql($this->config);
        $this->activeConnections[] = $connection->identify;
        $this->allConnections[$connection->identify] = $connection;

        return $connection;
    }

    /**
     * remove connection from pool
     * @param object $connection Connection instance
     * @return void
     */
    public function removeConnection($connection)
    {
        $identify = $connection->identify;
        if (isset($this->allConnections[$identify])) {
            unset($this->allConnections[$identify]);
        }
    }

    /**
     * relase connection and recycle
     * @param string $identify
     * 
     * @return boolean
     */
    public function release($identify)
    {
        $index = array_search($identify, $this->activeConnections);
        if ($index && !in_array($identify, $this->transactionManager)) {
             unset($this->activeConnections[$index]);
            $this->freeConnections->enqueue($identify);
        }

        return true;
    }

    /**
     * execute sql
     *
     * @param string $sql
     * @param array $value
     * @return mixed
     * @throws RuntimeException
     */
    public function query($sql, $value)
    {
        $connection = null;
        $connection = $this->connect();
        if ($connection->isClose()) {
            $this->reconnect($connection);
        }
        $result = $connection->query($sql, $value);
        $errorCode = $connection->getErrorCode();
        if ($errorCode) {
            throw new RuntimeException('Connection error(%d):%s', $errorCode, $connection->getError());
        }
        $this->release($connection->identify);

        return $result;
    }

    public function execute($params, $options)
    {
        $this->query($params, $options);
    }

    /**
     * reconnect
     *
     * @param Connection $connection
     * @return void
     */
    private function reconnect(Connection $connection)
    {
        return $connection->connect($this->config);
    }

    /**
     * add a transaction
     *
     * @param string $identify
     * @return void
     */
    public function addTransaction($identify)
    {
        $this->transactionManager[] = $identify;
    }

    /**
     * get connection and start a transaction
     *
     * @param string $identify
     * @return string
     */
    public function touchConnection($identify)
    {
        $connection = $this->connect($identify);
        $connection->beginTransaction();
        return $connection;
    }
    
    public function remove($arr, $index)
    {
        $keys = array_keys($arr);
        $offset = array_search($index, $arr);
        array_splice($arr, $offset, 1);
        return $arr;
    }
}

