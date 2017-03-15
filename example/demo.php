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
    'database' => 'crab',
];
//$db = new DB();
//$db->add($config);
$model = new Model();
$model->table('user');
$condition = [
    'id' => [ '$gt' => 1],
];
$model->init($config);
$result = $model->find($condition);
var_dump($result);
$user = $model->findById(11);
var_dump($user);


