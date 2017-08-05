<?php
/**
 * @license MIT
 * @copyright Copyright (c) 2017
 * @author: bugbear
 * @date: 2017/7/17
 * @time: 下午1:10
 */

namespace Mews;

use RuntimeException;
use InvalidArgumentException;
use SplQueue;

class Pool
{


    private $freeConnections = [];

    private $activeConnections = [];

    private $transactionManner = [];

    private $closed = true;

    private $config = [];

    private static $instance;

    private $poolSize = -1;

    private $minxPoolSize = 10;

    private $uuid  = '';

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

    public static function singleton($config)
    {
        if (!self::$instance) {
            self::$instance = new self($config);
        }
        echo "@@@uuid" . self::$instance->uuid . PHP_EOL;
        return self::$instance;
    }

    public function getConnection($identify = null)
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


    private function acquireConnection()
    {
        $connection = new Connection($this->config);
        $this->activeConnections[] = $connection->identify;
        $this->allConnections[$connection->identify] = $connection;

        return $connection;
    }

    public function removeConnection($connection)
    {
        $identify = $connection->identify;
        if (isset($this->allConnections[$identify])) {
            unset($this->allConnections[$identify]);
        }
    }


    public function releaseConnection($identify)
    {
        echo "++++++++$identify:" . $this->freeConnections->count() . ">>>>>" . count($this->activeConnections) . "*********\n";
        $index = array_search($identify, $this->activeConnections);
        if ($index && !in_array($identify, $this->transactionManner)) {
             unset($this->activeConnections[$index]);
            $this->freeConnections->enqueue($identify);
        }

        return true;
    }

    public function query($sql, $value)
    {
        $connection = null;
        $connection = $this->getConnection();
        if ($connection->isClose()) {
            $this->reconnect($connection);
        }
        $result = $connection->query($sql, $value);
        $errorCode = $connection->getErrorCode();
        if ($errorCode) {
            throw new RuntimeException('Connection error(%d):%s', $errorCode, $connection->getError());
        }
        $this->releaseConnection($connection->identify);

        return $result;
    }

    private function reconnect(Connection $connection)
    {
        return $connection->connect($this->config);
    }

    public function addTransaction($identify)
    {
        $this->transactionManner[] = $identify;
    }


    public function touchConnection($identify)
    {
        $connection = $this->getConnection();
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

