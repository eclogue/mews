#Mews
 
 used like mongo shell
 
### install
 `composer require racecourse/mews`
### get start
```php
<?php
$user = new Model();
$user->init([
  'host' => '127.0.0.1',
  'user' => 'root',
  'password' => '123123',
  'database' => 'test',
]);

$condition = [
    'id' => ['$gt' => 1, '$lt' => 100, '$neq' => 23],
    '$or' => [
        'email' => 'aaxx@scac.com',
        'status' => '1',
    ],
    'age' => ['$lt' => 70]
];
$user->findById(1);
$user->findOne($condition);
$user->findByIds([1, 2, 3]);
class User extends \Mews\Model {
    public $table = 'users';
    
    public $fields = [
        'id' => ['pk' => true, 'auto' => true, 'type' => 'int', 'column' => 'id' ],
        'username' => ['type' => 'string', 'column' => 'username']
        // ...
    ];
    
    public function __construct()
    {
        $config = [
            'host' => '127.0.0.1',
            'user' => 'root',
            'password' => '123123',
            'database' => 'test',
        ];
        $this->init($config);
    }
}

$userInfo = new User()->findById(1);
var_dump($userInfo);
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

###
get query sql:
`var_dump($user->lastSql);`
