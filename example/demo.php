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
        'pool' => false,
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
    'debug' => true,
];

$condition = [
    'id' => ['$in' => [1, 2]],
    'username' => ['$eq' => 'mulberry'],
    '$or' => [
        'id' => ['$gt' => 5]
    ],
];
$cache = new Cache($redisConfig);
$model = new User($config);
$model->setCache($cache);
$transaction = $model->startTransaction();
var_dump($transaction);
try {
$result = $model->builder()->where($condition)->select();
//    $user = $model->find(['id' => ['$in' => [ 9]]]);
    exit;
    var_dump($user);
    return;
    $user['status'] = 2;
    $updated = $user->withTransaction($transaction)->update();
//var_dump($updated);
//var_dump($user->toArray());
    $model->username = 'test' . rand(1, 1000);
    $model->password = '123123';
    $model->nickname = 'waterfly';
    $model->status = 0;
    $model->email = rand(1, 1000) . 'email@email.com';
    $model->created = time();
//    var_dump($model);
    $newInstance = $model->save();
    var_dump($newInstance->pk);
    throw new Exception('test');
    $newInstance->delete();
    $model->commit();
} catch (Exception $e) {
    var_dump($e->getMessage());
    $model->rollback();
}
$config = [
    'host' => 'localhost',
    'user' => 'root',
    'password' => '123123',
    'dbname' => 'knight',
];

//$pool = new Pool($config);
//$i = 0;
//while ($i < 10) {
//    $pool->getConnection();
//    $i++;
//}
//$i = 0;
//while($i < 20) {
//    $sql = 'SELECT * FROM users WHERE id =?';
//    $result = $pool->query($sql, [9]);
//    $i++;
//}

