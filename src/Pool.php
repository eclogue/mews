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

class Pool
{


    private $freeConnections;

    private $activeConnections;

    private $closed = true;

    private $config = [];

    private $lockConnections = [];

    private static $instance = null;

    private $poolSize = -1;


    public function __construct($config)
    {
        $this->config = $config;
        $this->freeConnections = [];
        $this->activeConnections = [];
        if (isset($config['poolSize'])) {
            $this->poolSize = $config['poolSize'];
        }
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

        $connection = null;
        if ($uid) {
            if (isset($this->lockConnections[$uid])) {
                return $this->lockConnections[$uid];
            }
        }
        while (!empty($this->freeConnections)) {
            $conn = array_pop($this->freeConnections);
            if (!$conn) {
                continue;
            }
            if ($conn->isClose()) {
                $this->removeConnection($conn);
                continue;
            }
            $connection = $conn;
            $this->activeConnections[$conn->identify] = $conn;
            break;
        }
        if (!$connection) {
            $active = count($this->activeConnections) + count($this->freeConnections)
                + count($this->lockConnections);
            if ($this->poolSize !== -1 && $active >= $this->poolSize) {
                throw new RuntimeException('Connection pool ...'); // @fixme
            }
            $connection = $this->acquireConnection($uid);
        }
        echo "*********" . count($this->freeConnections) . "===" . count($this->activeConnections) . "*********\n";

        return $connection;
    }


    private function acquireConnection($lock = false)
    {
        $connection = new Connection($this->config);
        if ($lock) {
            $this->lockConnections[$connection->identify] = $connection;
        } else {
            $this->activeConnections[$connection->identify] = $connection;
        }

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


    public function releaseConnection($identify)
    {
        echo '>>>>>>>>>' . $identify . PHP_EOL;
        if (isset($this->lockConnections[$identify])) {
            $connection = $this->lockConnections[$identify];
            unset($this->lockConnections[$identify]);
        } else {
            $connection = $this->activeConnections[$identify];
            unset($this->activeConnections[$identify]);
        }

        $this->freeConnections[$identify] = $connection;

        return true;
    }

    public function query($sql, $value)
    {
        while (empty($this->activeConnections)) {
            $this->getConnection();
        }

        $connection = array_pop($this->activeConnections);
        if ($connection->isClose()) {
            $this->reconnect($connection);
        }
        $result = $connection->query($sql, $value);
        $errorCode = $connection->getErrorCode();
        if ($errorCode) {
            throw new RuntimeException('Connection error(%d):%s', $errorCode, $connection->getError());
        }
        if (!isset($this->freeConnections[$connection->identify])) {
            $this->freeConnections[$connection->identify] = $connection;
        }
        echo "++++++++" . count($this->freeConnections) . ">>>>>" . count($this->activeConnections) . "*********\n";

        return $result;
    }

    private function reconnect(Connection $connection)
    {
        return $connection->connect($this->config);
    }


    public function touchConnection($identify)
    {
        $connection = $this->getConnection();
        $connection->beginTransaction();
        return $connection;
    }
}

