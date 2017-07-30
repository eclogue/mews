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

    private $maxConnections = -1;

    private $lockConnections = [];

    private static $instance = null;

    private $poolSize = -1;


    public function __construct($config)
    {
        $this->config = $config;
        $this->freeConnections = [];
        $this->enqueueConnections = [];
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
        if (!empty($this->freeConnections)) {
            foreach ($this->freeConnections as $identify => $conn) {
                if (!$conn) {
                    continue;
                }
                if ($conn->isClose()) {
                    $this->removeConnection($conn);
                    continue;
                }
                $connection = $conn;
                break;
            }
        }
        if (!$connection) {
            $active = count($this->enqueueConnections) + count($this->freeConnections)
                + count($this->lockConnections);
            if ($this->poolSize !== -1 && $active >= $this->poolSize) {
                throw new RuntimeException('Connection pool ...'); // @fixme
            }
            $connection = $this->acquireConnection($uid);
        }

        return $connection;
    }


    private function acquireConnection($lock = false)
    {
        $connection = new Connection($this->config);
        if ($lock) {
            $this->lockConnections[$connection->identify] = $connection;
        } else {
            $this->enqueueConnections[$connection->identify] = $connection;
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
        echo $identify . PHP_EOL;
        if (isset($this->lockConnections[$identify])) {
            return true;
        }

        $connection = $this->lockConnections[$identify];
        unset($this->lockConnections[$identify]);
        $this->freeConnections[$identify] = $connection;

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


    public function touchConnection($identify)
    {
        $connection = $this->getConnection();
        $connection->beginTransaction();
        return $connection;
    }

    public function getLockId()
    {

    }


}

