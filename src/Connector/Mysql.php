<?php
/**
 * @license MIT
 * @copyright Copyright (c) 2017
 * @author: bugbear
 * @date: 2017/7/19
 * @time: 下午12:51
 */

namespace Mews\Connector;

use mysqli;
use RuntimeException;

class Mysql implements ConnectorInterface
{
    /**
     * connection config
     *
     * @var array
     */
    private $config = [];

    /**
     * mysqli source
     *
     * @var [type]
     */
    private $link;

    /**
     * connection identify
     *
     * @var string
     */
    public $identify = '';

    /**
     * xa transaction
     *
     * @var boolean
     */
    public $xa = false;

    /**
     * query affect
     *
     * @var integer
     */
    public $affectedRows = 0;

    /**
     * connection instance
     *
     * @var null
     */
    private static $instance = null;


    /**
     * Connection constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->identify = uniqid();
        $this->connect();
    }

    public static function singleton($config)
    {
        if (isset($config['pool']) && $config['pool']) {
            return Pool::singleton($config);
        }

        if (!static::$instance) {
            static::$instance = new static($config);
        }

        return static::$instance;
    }

    /**
     * @param array $config
     * @return mysqli
     */
    public function connect()
    {
        $config = $this->config;
        $user = $config['user'] ?? 'root';
        $password = $config['password'] ?? '';
        $host = $config['host'] ?? 'localhost';
        if (isset($config['pool'])) {
            $host = 'p:' . $host;
        }

        $port = $config['port'] ?? '3306';
        $dbname = $config['dbname'];
        $charset = $config['charset'] ?? 'utf8';
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

    /**
     * execute sql
     *
     * @param string $sql
     * @param array $values
     * @return mixed
     * @throws RuntimeException
     */
    public function execute($sql, $values=[])
    {
        if (!empty($values)) {
            $stmt = $this->link->prepare($sql);
            if (!$stmt) {
                return false;
            }
            if (count($values)) {
                $types = str_repeat('s', count($values));
                $stmt->bind_param($types, ...$values);
            }

            $stmt->execute();
            if ($stmt->errno) {
                throw new RuntimeException(sprintf('Stmt error(%d):%s', $stmt->errno, $stmt->error));
            }

            $this->affectedRows = $stmt->affected_rows;

            return $stmt;
        } else {
            $ret = $this->link->query($sql);
            $this->affectedRows = $this->link->affected_rows;

            return $ret;
        }
    }

    /**
     * execute query sql
     *
     * @param string $sql
     * @param array $values
     * @return mixed
     */
    public function query($sql, $values)
    {
        $stmt = $this->execute($sql, $values);
        $code = $this->getErrorCode();
        if ($code) {
            $msg = 'Query Error' . $this->getError() . ' #code ' . $this->getErrorCode();
            if (intval($code) === 2006) {
                $this->connect($this->config);
                return $this->query($sql, $values);
            } else {
                throw new RuntimeException($msg);
            }
        }

        $sql = ltrim($sql);
        $pattern = '#^(insert|update|replace|select|delete)#i';
        preg_match($pattern, $sql, $match);
        if (!count($match)) {
            return $stmt;
        }

        $sqlType = strtoupper($match[0]);
        if ($sqlType === 'INSERT') {
            if ($stmt instanceof \mysqli_stmt) {
                $insertId = $stmt->insert_id;
                $stmt->close();
                return $insertId;
            }

            return $this->link->insert_id;
        } else if ($sqlType === 'SELECT') {
            $result = $stmt;
            $ret = [];
            if ($stmt instanceof \mysqli_stmt) {
                $result = $stmt->get_result();
            }

            if ($result instanceof \mysqli_result) {
                while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
                    $ret[] = $row;
                }
                $stmt->free_result();

                return $ret;
            }

            return $this->affectedRows;
        } else {
            $this->affectedRows;
        }
    }

    /**
     * @todo
     */
    private function release()
    {
    }

    /**
     * close mysql link
     */
    public function close()
    {
        $this->link->close();
    }

    /**
     * check mysql link is closed
     *
     * @return bool
     */
    public function isClose()
    {
        return !$this->link->thread_id && !$this->link->host_info;
    }

    /**
     * @todo
     */
    private function getType()
    {

    }

    /**
     * start transaction
     *
     * @return void
     */
    public function startTransaction()
    {
        $this->link->autocommit(false);
    }

    /**
     * commit transaction
     *
     * @return void
     */
    public function commit()
    {
        $this->link->autocommit(true);
    }

    /**
     * rollback transaction
     *
     * @return void
     */
    public function rollback()
    {
        $this->link->rollback();
    }
}