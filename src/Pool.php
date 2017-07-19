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


    private $_freeConnections = [];

    private $_touchConnections = [];

    private $_closed = true;

    private $_config = [];

    protected $maxConnections = -1;

    private $_lock = [];



    public function __construct($config)
    {
        $this->_config = $config;
        $this->_freeConnections = new SplQueue();
//        $this->_touchConnections = new SplQueue();
    }

    public function getConnection()
    {
        echo '**********' . count(count($this->_touchConnections)) . '&&' . count($this->_freeConnections) . "**************\n";
        if (!$this->_closed) {
            throw new RuntimeException('Connection pool is closed');
        }

        $active = count($this->_touchConnections) + count($this->_freeConnections);
        if ($this->maxConnections !== -1 && $active >= $this->maxConnections) {
            throw new RuntimeException('Connection pool ...');
        }
        if (count($this->_freeConnections)) {
            $connection = $this->_freeConnections->dequeue();
            $this->_touchConnections[$connection->identify] = $connection;
        }

        return $this->acquireConnection();
    }


    private function acquireConnection()
    {
        $connection = new Connection($this->_config);
        $connection->connect();
        $this->_touchConnections[$connection->identify] = $connection;
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
        $connection = $this->getConnection();
        $result = $connection->query($sql, $value);
        $errorCode = $connection->getErrorCode();
        if ($errorCode) {
            throw new RuntimeException('Connection error(%d):%s', $errorCode, $connection->getError());
        }
        $this->_freeConnections->enqueue($connection);
        unset($this->_touchConnections[$connection->identify]);
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

