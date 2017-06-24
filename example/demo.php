<?php
/**
 * @license   https://github.com/Init/licese.md
 * @copyright Copyright (c) 2017
 * @author    : bugbear
 * @date      : 2017/3/13
 * @time      : 上午11:16
 */

define('ROOT', dirname(dirname(__FILE__)));
require ROOT . '/vendor/autoload.php';
use Mews\Model;

$config = [
    'host' => '127.0.0.1',
    'user' => 'root',
    'password' => '123123',
    'database' => 'knight',
];
//$db = new DB();
//$db->add($config);
$model = new Model();
$model->table('users');
$condition = [
    'id' => ['$in' => [1,2]],
    'username' => ['$eq' => 'mulberry'],
    '$or' => [
        'id' => ['$gt' => 5]
    ],
];
$model->init($config);
$result = $model->builder()->where($condition)->select();
//$user = $model->findById(11);
//var_dump($user);


