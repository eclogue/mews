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

//class User extends Model
//{
//    public $table = 'users';
//
//    public $fields = [
//        'id' => ['column' => 'id', 'pk' => true, 'type' => 'int'],
//        'username' => ['column' => 'username', 'type' => 'string'],
//        'nickname' => ['column' => 'nickname', 'type' => 'string'],
//        'password' => ['column' => 'password', 'type' => 'string'],
//        'status' => ['column' => 'status', 'type' => 'int'],
//        'email' => ['column' => 'email', 'type' => 'string', 'default' => ''],
//        'created' => ['column' => 'created', 'type' => 'int'],
//        'updated' => ['column' => 'updated', 'type' => 'timestamp'],
//    ];
//
//}
//
//$config = [
//    'host' => '127.0.0.1',
//    'user' => 'root',
//    'password' => '123123',
//    'database' => 'knight',
//    'options' => '',
//];
//
//$condition = [
//    'id' => ['$in' => [1,2]],
//    'username' => ['$eq' => 'mulberry'],
//    '$or' => [
//        'id' => ['$gt' => 5]
//    ],
//];
//$model = new User();
//$model->init($config);
////$result = $model->builder()->where($condition)->select();
//$md = $model->findOne(['id' => 9]);
//$md['status'] = 2;
////var_dump($md->fields);
////var_dump($md->attr);
//$md->update();
//var_dump($md->toArray());
////var_dump($update);


$config = [
    'host' => 'localhost',
    'user' => 'root',
    'password' => '123123',
    'dbname' => 'knight',
];

$pool = new Pool($config);
$i = 0;
while ($i < 50) {
    $sql = 'SELECT * FROM users WHERE id =?';
    $result = $pool->query($sql, 9);
    $i++;
}
