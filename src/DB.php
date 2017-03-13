<?php
namespace Mews;

class DB
{

    private $_config = [];

    private $linkr = null;

    private $linkw = null;

    private $links = [];


    public function add($config, $type = 'single')
    {
        $type = strtolower($type);
        if ($type === 'master') {
            $this->_config['master'] = $config;
        } else if ($type === 'slave') {
            $this->_config['slave'] = $config;
        } else {
            $this->_config['single'] = $config;
        }
    }

    public function connect()
    {
        try {
            foreach ($this->_config as $type => $config) {
//                $len = count($config);
//                $index = mt_rand(0, $len - 1);
//                $connection = $config[$index];
                $connection = $config;
                $dsn = $this->dsn($connection);
                if (!empty($this->links[$dsn])) {
                    $this->linkw = $this->links[$dsn]['linkw'];
                    $this->linkr = $this->links[$dsn]['linkr'];
                    continue;
                }

                $link[$dsn] = new \PDO(
                    $dsn,
                    $connection['user'],
                    $connection['password']
//                    $connection['options']
                );
                if ($type === 'master') {
                    $this->linkw = $link[$dsn];
                } else if ($type === 'slave') {
                    $this->linkr = $link[$dsn];
                } else {
                    $this->linkr = $this->linkw = $link[$dsn];
                }
                $this->links[$dsn]['linkw'] = $this->linkw;
                $this->links[$dsn]['linkr'] = $this->linkr;
            }
        } catch (\Exception $err) {
            throw new \Error('DB connect error,' . $err->getMessage());
        }
    }


    private function dsn($config)
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
        $query = $this->linkr->prepare($sql);

        if ($value) {
            $res = $query->execute($value);
        } else {
            $res = $query->execute();
        }
        if (!$res) return null;

        return $this->fetch($query);
    }

    public function execute($sql)
    {
        return $this->linkr->query($sql);
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


