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

class User extends Model
{
    public $table = 'users';

    public $fields = [
        'id' => ['column' => 'id', 'pk' => true, 'type' => 'int'],
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
    'host' => '127.0.0.1',
    'user' => 'root',
    'password' => '123123',
    'dbname' => 'knight',
    'options' => '',
];

$condition = [
    'id' => ['$in' => [1,2]],
    'username' => ['$eq' => 'mulberry'],
    '$or' => [
        'id' => ['$gt' => 5]
    ],
];
$model = new User($config);
//$result = $model->builder()->where($condition)->select();
$md = $model->findOne(['id' => 9]);
$md['status'] = 2;
$updated = $md->update();
var_dump($updated);
var_dump($md->toArray());
$model->username = 'test' . rand(1, 1000);
$model->password = '123123';
$model->nickname = 'waterfly';
$model->status = 0;
$model->email = rand(1, 1000) . 'email@email.com';
$model->created = time();
$newInstance = $model->save();
var_dump($newInstance);
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

