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
                $len = count($config);
                $index = mt_rand(0, $len - 1);
                $connection = $config[$index];
                $dsn = $this->dsn($connection);
                if (!empty($this->links[$dsn])) {
                    $this->linkw = $this->links[$dsn]['linkw'];
                    $this->linkr = $this->links[$dsn]['linkr'];
                    continue;
                }

                $link[$dsn] = new \PDO(
                    $dsn,
                    $connection['username'],
                    $connection['password'],
                    $connection['options']
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
        $dsn = 'mysql:';
        if (is_array($config)) {

        }
        if (isset($config) && $config['socket']) {
            $dsn .= 'unix_socket=' . $config['socket'];
        } else {
            $dsn .= 'host=' . $config['host'] . ';port=' . $config['port'];
        }
        $dsn .= ';dbname=' . $config['db'];

        return $dsn;
    }

    public function query($sql)
    {
        return $this->linkr->query($sql);
    }

    public function execute($sql)
    {
        return $this->linkr->query($sql);
    }
}

$config = [
    'host' => '10.0.6.49',
    'port' => 3306,
    'username' => 'impress',
    'password' => 'yupoo123',
    'database' => 'impress',
];
//$db = new DB($config);
//$db->query('select * from users');
var_dump($_ENV);

