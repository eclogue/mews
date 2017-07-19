<?php
namespace Mews;

use mysqli;

class DB
{

    private static $_config = [];

    private $linkr = null;

    private $linkw = null;

    private static $links = [];

    private static $instance = [];

    public $debug = true;

    public function __construct($config)
    {

    }


    public static function add($config, $type = 'single')
    {
        $type = strtolower($type);
        if ($type === 'master') {
            self::$_config['master'][] = $config;
        } else if ($type === 'slave') {
            self::$_config['slave'][] = $config;
        } else {
            self::$_config['single'][] = $config;
        }
    }

    public static function create($config, $type = 'single')
    {
        $self = new static();
        $self->add($config);
        $self->connect($type);
    }


    public function connect($type = 'single')
    {
        try {
            $config = self::$_config[$type];
            $len = count($config);
            $index = mt_rand(0, $len - 1);
            $connection = $config[$index];
            $dsn = self::dsn($connection);

            $link[$dsn] = new mysqli(
                $connection['user'],
                $connection['password']
//                $connection['options']
            );
        } catch (\Exception $err) {
            throw new \Error('DB connect error,' . $err->getMessage());
        }
    }


    private static function dsn($config)
    {
        if (!isset($config['post'])) {
            $config['port'] = 3306;
        }
        $dsn = 'mysql:';
        if (is_array($config)) {

        }
        if (isset($config['socket'])) {
            $dsn .= 'unix_socket=' . $config['socket'];
        } else {
            $dsn .= 'host=' . $config['host'] . ';port=' . $config['port'];
        }
        $dsn .= ';dbname=' . $config['database'];

        return $dsn;
    }

    public function query($sql, $value = null)
    {
        if ($this->debug) {
            echo "debug sql: " . $sql . " #args:" . json_encode($value);
        }
        $query = $this->linkr->prepare($sql);

        if ($value) {
            $res = $query->execute($value);
        } else {
            $res = $query->execute();
        }
        if ($query->errorCode() !== '00000') { // @todo
            var_dump($query->errorInfo(), $query->errorCode());
        }
        if (!$res) return null;

        return $this->fetch($query);
    }

    public function execute($sql, $value = null)
    {
        if ($this->debug) {
            echo "debug sql: " . $sql . " #args:" . json_encode($value);
        }
        $query = $this->linkr->prepare($sql);

        if ($value) {
            $res = $query->execute($value);
        } else {
            $res = $query->execute();
        }
        if ($query->errorCode() !== '00000') {
            throw new \Exception('Execute Sql Exception:' . implode('# ', $query->errorInfo()));
        }
        preg_match('#INSERT INTO#', $sql, $match);
        if ($match) {
            return $this->linkw->lastInsertId();
        }
        return $res;
    }

    public function fetch($query)
    {
        $res = [];
        while ($row = $query->fetch(\PDO::FETCH_ASSOC)) {
            $res[] = $row;
        }

        return $res;
    }

    public function prepare($sql)
    {

    }
}


