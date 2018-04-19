# Mews
 
 PHP ORM，used connection pool, similar to mongodb query shell.
 
### install
 `composer require eclogue/mews`
 
 
 **Notice** beta stage
 
### get start
```php
<?php
use Mews\Model;
$config = [
  'host' => '127.0.0.1',
  'user' => 'root',
  'password' => '123123',
  'dbname' => 'test',
];

class User extends \Mews\Model {
    public $table = 'users';
    
    public $fields = [
        'id' => ['pk' => true, 'auto' => true, 'type' => 'int', 'column' => 'id' ],
        'username' => ['type' => 'string', 'column' => 'username']
        // ...
    ];
}

$user = new Model($config);

$condition = [
    'id' => ['$gt' => 1, '$lt' => 100, '$neq' => 23],
    '$or' => [
        'email' => 'aaxx@scac.com',
        'status' => '1',
    ],
    'age' => ['$lt' => 70]
];
$userInfo = $user->findById(1);
$user->findOne($condition);
$user->findByIds([1, 2, 3]);
$data = [
    'nickname' => 'damn it',
    'status' => [ '$inc' => -1]
];
$where = [
    'status' => [
        '$gt' => 0
    ]
];
$userInfo->update($where, $data);
// sql: UPDATE `users` SET `nickname`=?,`status`=`status` + -1 WHERE (`id` = ? AND `status` > ? ) #args: ["damn it",1,0]
```
-------

### builder
```php
<?php
// builder
$user->builder()
->field('*')
->where($condition)
->order(['id' => 'desc'])
->skip(100)
->limit(10)
->select();
// execute sql: SELECT * FROM `user` WHERE (`id`>'1' and `id`<'100' and `id`!='23') or (`email`='aaxx@scac.com' and `status`='1') and (`age`<'70') ORDER BY `id` DESC limit 10 offset 100;

```


### cache
 mews cache is dependence primary key `id`.If you want to use cache please make sure your table had primary key `id`.

### Feature
 -  connection pool
 -  connection pool transaction
 -  cache
 - ~~cluster connection pool~~

