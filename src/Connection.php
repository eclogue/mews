<?php
/**
 * @license https://github.com/racecourse/courser/license.md
 * @copyright Copyright (c) 2017
 * @author: bugbear
 * @date: 2017/7/19
 * @time: 下午12:51
 */

namespace Mews;

use mysqli;
use RuntimeException;

class Connection
{
    private $config = [];

    private $link;

    public $identify = '';


    /**
     * Connection constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->identify = uniqid();
    }

    /**
     * connect mysql
     *
     * @return mysqli
     */
    public function connect()
    {
        $user = $this->config['user'] ?? 'root';
        $password = $this->config['password'] ?? '';
        $host = $this->config['host'] ?? 'localhost';
        $host = 'p:' . $host;
        $port = $this->config['port'] ?? '3306';
        $dbname = $this->config['dbname'];
        $charset = $this->config['charset'] ?? 'utf8';
        $this->link = new mysqli($host, $user, $password, $dbname, $port);
        if ($this->link->connect_error) {
            throw new RuntimeException('Connect Error (' . $this->link->connect_errno . ')'
                . $this->link->connect_error
            );
        }
        $this->link->set_charset($charset);

        return $this->link;
    }

    /**
     * get error
     *
     * @return mixed
     */
    public function getError()
    {
        return $this->link->error;
    }

    /**
     * get error code
     *
     * @return integer
     */
    public function getErrorCode()
    {
        return $this->link->errno;
    }

    public function execute($sql, $values)
    {
        echo "debug:" . $sql . "values:" . implode(',', $values) . PHP_EOL;
        $state = $this->link->get_connection_stats();
//        $this->close();
        echo "debug: active connections:" . $state['active_persistent_connections'] . PHP_EOL;
        $types = str_repeat('s', count($values));
        $stmt = $this->link->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        if ($stmt->errno) {
            throw new RuntimeException(printf('Stmt error(%d):%s', $stmt->errno, $stmt->error));
        }
        return $stmt;
    }

    public function query($sql, $values)
    {
        $stmt = $this->execute($sql, $values);
        $result = $stmt->get_result();
        $ret = [];
        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $ret[] = $row;
        }

        echo '--------------------->' . PHP_EOL;
        var_dump($ret);
//        $stmt->free_result();
        $stmt->close();

        return $ret;
    }

    public function insert($sql, $values) {
        $stmt = $this->execute($sql, $values);
        $insertId = $stmt->insert_id;
        $stmt->close();
        return $insertId;
    }

    public function close()
    {
        $this->link->close();
    }

    public function isClose()
    {
        return !$this->link->thread_id && !$this->link->host_info;
    }


    private function getType()
    {

    }
}