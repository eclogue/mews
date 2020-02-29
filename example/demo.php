<?php
/**
 * @license   MIT
 * @copyright Copyright (c) 2017
 * @author    : bugbear
 * @date      : 2017/3/13
 * @time      : 上午11:16
 */

define('ROOT', dirname(dirname(__FILE__)));
require ROOT . '/vendor/autoload.php';

use Mews\Model;
use Mews\Pool;
use Mews\Cache;

class User extends Model
{
    protected $table = 'users';

    protected $fields = [
        'id' => ['column' => '_id', 'pk' => true, 'type' => 'int', 'auto' => true],
        'username' => ['column' => 'username', 'type' => 'varchar'],
        'nickname' => ['column' => 'nickname', 'type' => 'varchar'],
        'password' => ['column' => 'password', 'type' => 'varchar'],
        'status' => ['column' => 'status', 'type' => 'int'],
        'email' => ['column' => 'email', 'type' => 'varchar', 'default' => ''],
        'created' => ['column' => 'created', 'type' => 'int'],
        'updated' => ['column' => 'updated', 'type' => 'timestamp', 'default' => 'current'],
    ];

    protected $indexes = [
        'username' => ['type' => 'unique', 'column' => ['username']],
        'email' => ['type' => 'key', 'column' => ['email']],

    ];

}

$config = [
    'type' => 'mysql',
    'servers' => [
        'host' => '127.0.0.1',
        'user' => 'root',
        'password' => '123123',
        'dbname' => 'knight',
        'options' => '',
        'pool' => true, // false
    ],
    'debug' => true,
];
$mongo = [
    'uri' => 'mongodb://127.0.0.1:27017/knight',
    'db' => 'knight',
    'options' => [],
    'type' => 'mongodb'
];

$redisConfig = [
    'servers' => [
        'host' => '127.0.0.1',
        'port' => '6379',
    ],
    'prefix' => 'demo',
    'ttl' => 60 * 10,
    'enable' => true,
];

$condition = [
    'id' => ['$in' => [1, 2]],
    'username' => ['$eq' => 'mulberry'],
];


$start = microtime(true);
$sm = memory_get_usage();
// $cache = new Cache($redisConfig);
$model = new User($mongo);
//$schema = $model->getSchema();
//$schema->build();
//echo $schema->tableInfo();
//exit();
// $model->setCache($cache);
// $transaction = $model->startTransaction();
try {
    $user = $model->findById(new \MongoDB\BSON\ObjectId('5e56692e282ede2efd3c08a2'));
    $data = $user->toArray();
    unset($data['id']);
//    $data = [
//        'nickname' => 'damn it',
//        'status' => ['$inc' => -1]
//    ];
    $where = [
        'status' => [
            '$gt' => 0
        ]
    ];
//    $inserted = $user->insert($data);
    $user['username'] .= '+++++++++++++';
    var_dump('~~~~~~~~~~~', $user->toArray());
    $user->save();
    $user->delete(['username' => $user['username']]);

} catch (Exception $e) {
    var_dump($e->getMessage());
//    $model->rollback();
}
$end = microtime(true);
$em = memory_get_usage();
echo $start . '====' . $end;

echo "em: $em -- sm: $sm \n";
echo $end - $start;

echo " \n";
echo $em - $sm;


// $pool = new Pool($config['servers']);
//$i = 0;
//while ($i < 10) {
//    $pool->getConnection();
//    $i++;
//}
//$i = 0;
//while($i < 20) {
//    $sql = 'SELECT * FROM users WHERE id >?';
//    $result = $pool->query($sql, 1);
//    $i++;
//}

