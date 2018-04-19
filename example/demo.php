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
        'id' => ['column' => 'id', 'pk' => true, 'type' => 'int', 'auto' => true],
        'username' => ['column' => 'username', 'type' => 'string'],
        'nickname' => ['column' => 'nickname', 'type' => 'string'],
        'password' => ['column' => 'password', 'type' => 'string'],
        'status' => ['column' => 'status', 'type' => 'int'],
        'email' => ['column' => 'email', 'type' => 'string', 'default' => ''],
        'created' => ['column' => 'created', 'type' => 'int'],
        'updated' => ['column' => 'updated', 'type' => 'timestamp'],
    ];

}

$config = [
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
    '$or' => [
        'id' => ['$gt' => 5]
    ],
];


$start =  microtime(true);
$sm = memory_get_usage();
// $cache = new Cache($redisConfig);
$model = new User($config);
// $model->setCache($cache);
// $transaction = $model->startTransaction();
try {
    $user = $model->findById('1');
    $data = [
        'nickname' => 'damn it',
        'status' => [ '$inc' => -1]
    ];
    $where = [
        'status' => [
            '$gt' => 0
        ]
    ];
    $user->update($where, $data);
    // sql: UPDATE `users` SET `nickname`=?,`status`=`status` + -1 WHERE (`id` = ? AND `status` > ? ) #args: ["damn it",1,0]

    $result = $model->builder()->where($condition)->select();
    $user = $model->find(['id' => ['$in' => [ 9]]]);
    var_dump($user);

    $user->status = 2;
    $updated = $user->update();
    var_dump($user->toArray());
    $model->username = 'test' . rand(1, 1000);
    $model->password = '123123';
    $model->nickname = 'waterfly';
    $model->status = 0;
    $model->email = rand(1, 1000) . 'email@email.com';
    $model->created = time();
    $newInstance = $model->save();
    var_dump($newInstance->pk);
    throw new Exception('test');
    $newInstance->delete();
    $model->commit();
} catch (Exception $e) {
    var_dump($e->getMessage());
    $model->rollback();
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

