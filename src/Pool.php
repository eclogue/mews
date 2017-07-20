<?php
/**
 * @license https://github.com/racecourse/courser/license.md
 * @copyright Copyright (c) 2017
 * @author: bugbear
 * @date: 2017/7/17
 * @time: 下午1:10
 */

namespace Mews;

use RuntimeException;
use SplQueue;

class Pool
{


    private $freeConnections;

    private $enqueueConnections;

    private $closed = true;

    private $config = [];

    protected $maxConnections = -1;

    private $lock = [];

    private static $instance = null;


    public function __construct($config)
    {
        $this->config = $config;
        $this->freeConnections = new SplQueue();
        $this->enqueueConnections = new SplQueue();
    }

    public static function singleton($config)
    {
        if (!self::$instance) {
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    public function getConnection()
    {
        if (!$this->closed) {
            throw new RuntimeException('Connection pool is closed');
        }

        $active = count($this->enqueueConnections) + count($this->freeConnections);
        if ($this->maxConnections !== -1 && $active >= $this->maxConnections) {
            throw new RuntimeException('Connection pool ...');
        }
        $connection = null;
        if (!$this->freeConnections->isEmpty()) {
            while ($this->freeConnections->isEmpty()) {
                $connection = $this->freeConnections->dequeue();
                if ($connection->isClose()) {
                    continue;
                }
                $this->enqueueConnections->enqueue($connection);
            }

        }

        if (!$connection) {
            $this->acquireConnection();
        }

        return $this;
    }


    private function acquireConnection()
    {
        $connection = new Connection($this->config);
        $connection->connect();
        $this->enqueueConnections->enqueue($connection);

        return $connection;
    }

    public function removeConnection($connection)
    {

    }


    public function releaseConnection()
    {

    }

    public function query($sql, $value)
    {
        echo "*********" . $this->freeConnections->count() . "===" . $this->enqueueConnections->count() . "*********\n";
        if ($this->enqueueConnections->isEmpty()) {
            $this->getConnection();
        }
        $connection = $this->enqueueConnections->dequeue();

        $result = $connection->query($sql, $value);
        $errorCode = $connection->getErrorCode();
        if ($errorCode) {
            throw new RuntimeException('Connection error(%d):%s', $errorCode, $connection->getError());
        }
        $connection->close();
        $this->freeConnections->enqueue($connection);
        return $result;
    }

    private function reconnect($connection)
    {

    }


    public static function transaction()
    {
        // @todo 产生一个 lockId 并返回，其他 model 需根据这个 lockId 设置 connection
    }

    public static function commit()
    {

    }

    public static function rollback()
    {

    }

    public static function getLockId()
    {

    }


}

