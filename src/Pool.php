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

class Pool
{


    private $freeConnections;

    private $enqueueConnections;

    private $closed = true;

    private $config = [];

    protected $maxConnections = -1;

    private $lockConnections = [];

    private static $instance = null;


    public function __construct($config)
    {
        $this->config = $config;
        $this->freeConnections = [];
        $this->enqueueConnections = [];
    }

    public static function singleton($config)
    {
        if (!self::$instance) {
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    public function getConnection($uid = null)
    {
        if (!$this->closed) {
            throw new RuntimeException('Connection pool is closed');
        }

        $active = count($this->enqueueConnections) + count($this->freeConnections)
            + count($this->lockConnections);
        if ($this->maxConnections !== -1 && $active >= $this->maxConnections) {
            throw new RuntimeException('Connection pool ...'); // @fixme
        }
        $connection = null;
        if ($uid) {
            if (!isset($this->lockConnections[$uid])) {
                throw new RuntimeException('Miss connection:' . $uid);
            }
            return $this->lockConnections[$uid];
        }
        if (!empty($this->freeConnections)) {
            foreach ($this->freeConnections as $identify => $connection) {
                if ($connection->isClose()) {
                    continue;
                }
                $this->enqueueConnections[$identify] = $connection;
            }
        }

        if (!$connection) {
            $connection = $this->acquireConnection();
        }

        return $connection;
    }


    private function acquireConnection()
    {
        $connection = new Connection($this->config);
        $this->enqueueConnections[$connection->identify] = $connection;

        return $connection;
    }

    public function removeConnection($connection)
    {
        $identify = $connection->identify;
        if (isset($this->freeConnections[$identify])) {
            unset($this->freeConnections[$identify]);
        }
        if ($this->freeConnections[$identify]) {
            unset($this->freeConnections[$identify]);
        }
    }


    public function releaseConnection($connection)
    {
//        if (!isset($this->lockConnections[$identify])) {
//            return null;
//        }
//
//        $connection = $this->lockConnections[$identify];
//        unset($this->lockConnections[$identify]);
//        $this->freeConnections[$identify] = $connection;
        return true;
    }

    public function query($sql, $value)
    {
        echo "*********" . count($this->freeConnections) . "===" . count($this->enqueueConnections) . "*********\n";
        if (empty($this->enqueueConnections)) {
            $this->getConnection();
        }
        $connection = array_pop($this->enqueueConnections);
        if ($connection->isClose()) {
            $this->reconnect($connection);
        }
        $result = $connection->query($sql, $value);
        $errorCode = $connection->getErrorCode();
        if ($errorCode) {
            throw new RuntimeException('Connection error(%d):%s', $errorCode, $connection->getError());
        }
        $this->freeConnections[$connection->identify] = $connection;

        return $result;
    }

    private function reconnect(Connection $connection)
    {
        return $connection->connect($this->config);
    }


    public function transaction()
    {
        $connection = $this->getConnection();
        $connection->beginTransaction();
        return $connection;
    }

    public function commit()
    {

    }

    public function rollback()
    {

    }

    public function getLockId()
    {

    }


}

