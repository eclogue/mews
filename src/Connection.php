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
    private $_config = [];

    private $_link;

    public $identify = '';


    /**
     * Connection constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->_config = $config;
        $this->identify = uniqid();
    }

    /**
     * connect mysql
     *
     * @return mysqli
     */
    public function connect()
    {
        $user = $this->_config['user'] ?? 'root';
        $password = $this->_config['password'] ?? '';
        $host = $this->_config['host'] ?? 'localhost';
        $port = $this->_config['port'] ?? '3306';
        $dbname = $this->_config['dbname'];
        $charset = $this->_config['charset'] ?? 'utf8';
        $this->_link = new mysqli($host, $user, $password, $dbname, $port);
        if ($this->_link->connect_error) {
            throw new RuntimeException('Connect Error (' . $this->_link->connect_errno . ')'
                . $this->_link->connect_error
            );
        }
        $this->_link->set_charset($charset);

        return $this->_link;
    }

    /**
     * get error
     *
     * @return mixed
     */
    public function getError()
    {
        return $this->_link->error;
    }

    /**
     * get error code
     *
     * @return integer
     */
    public function getErrorCode()
    {
        return $this->_link->errno;
    }

    public function execute($sql, $values)
    {
        $types = str_repeat('s', count($values));
        $stmt = $this->_link->prepare($sql);
        $stmt->bind_param($types, $values);
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


    private function getType()
    {

    }
}