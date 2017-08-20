<?php

/**
 * @license   https://github.com/Init/licese.md
 * @copyright Copyright (c) 2017
 * @author    : bugbear
 * @date      : 2017/05/30
 * @time      : 上午10:54
 */
namespace Mews;

use InvalidArgumentException;

class Model implements \ArrayAccess
{
    protected $pool;

    protected $cache;

    protected $debug = true;

    public $pk = [];

    private $config = [];

    private $transactionId = '';

    private $attr = [];

    private $result = [];

    protected $table = '';

    protected $enableCache = false;


    /**
     * table schema
     *
     * @var array
     */
    protected $fields = [
        'id' => ['column' => 'id', 'type' => 'int', 'pk' => true],
        'username' => ['column' => 'username', 'type' => 'string'],
    ];

    /**
     * last execute sql
     * 
     * @var string
     */
    protected $lastSql = '';

    /**
     * Model constructor.
     *
     * @param array $config
     * @param array $cache
     */
    public function __construct(array $config, $cache = [])
    {
        $this->config = $config;
        $this->pool = Pool::singleton($config);
        if (!empty($cache)) {
            $client = new Cache($cache);
            $this->setCache($client);
        }

    }

    /**
     * set table
     *
     * @param string $table
     */
    public function setTable($table)
    {
        $this->table = $table;
    }

    /**
     * get table
     *
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @param $cache
     */
    public function setCache($cache)
    {
        $this->cache = $cache;
        $this->enableCache = true;
    }

    /**
     * get cache key
     *
     * @param string $key
     * @return string
     */
    public function getKey($key)
    {
        $key = md5($this->table . ':' . $key);
        if ($this->hashTag) {
            $key = '{' . $key . '}';
        }
        return $this->prefix . $key;
    }

    /**
     * get builder
     *
     * @return Builder
     */
    public function builder()
    {
        $connection = $this->getConnection();
        $release = function () use ($connection) {
          $this->pool->releaseConnection($connection->identify);
        };
        $release->bindTo($this);
        $builder = new Builder($connection, $release); // 如果
        $builder->table($this->table);

        return $builder;
    }

    private function getConnection()
    {
        return $this->pool->getConnection($this->transactionId);
    }


    /**
     * @return string
     */
    public function cacheKey($pk)
    {
        $str = json_encode($this->config) . strtolower($this->sql) . $this->flag;
        return md5($str);
    }

    /**
     * get count by condition
     *
     * @param array $where
     * @return int
     */
    public function count(array $where = [])
    {
        $where = $this->revertFields($where);
        $builder = $this->builder()
            ->field(['count(*) as count']);
        if ($where) {
            $builder = $builder->where($where);
        }
        $result = $builder->select();

        return $result[0]['count'] ?? 0;
    }


    /**
     * update model
     *
     * @param array $where
     * @param array $update
     * @return mixed
     */
    public function update($where = [], $update = [])
    {
        if (!empty($this->pk)) {
            $where = array_merge($this->pk, $where);
        }
        $changed = $this->getChange();
        $changed = array_merge($changed, $update);
        if (empty($changed)) {
            return $this;
        }
        $this->before();
        $mapping = $this->convert($changed);
        $this->builder()->where($where)->update($mapping);
        $this->result = array_merge($this->result, $changed);
        $this->after();
        $this->free;

        return $this->getModel($this->result);
    }

    /**
     * Insert new record
     *
     * @param array $data
     * @return static
     */
    public function insert(array $data)
    {
        $data = $this->revertFields($data);
        $id =  $this->builder()->insert($data);
        $this->increment($id);

        return $this->getModel($data);
    }

    /**
     * set primary key
     * 
     * @return void
     */
    public function setPrimaryKey() {
        foreach($this->fields as $field => $entity) {
            if (isset($entity['pk'])) {
                $this->pk[$field] = $entity['value'];
            }
        }
    }

    /**
     * Delete by where
     *
     * @param string $where
     */
    public function delete($where = '')
    {
        if (!empty($this->pk)) {
            $where = $this->pk;
        }
        $this->before();
        $where = $this->revertFields($where);
        $this->builder()
            ->where($where)
            ->delete();
        return $this->after();
    }

    /**
     * Select single record
     *
     * @param $where
     * @return Model|null
     */
    public function findOne(array $where)
    {
        $where = $this->revertFields($where);
        $builder = $this->builder();
        if ($this->enableCache) {
            $builder->field('id');
        }
        $result = $builder->where($where)
            ->limit(1)
            ->select();

        if (empty($result)) {
            return null;
        }
        $result = array_pop($result);
        if ($this->enableCache) {
            return $this->findById($result['id']);
        }

        return $this->getModel($result);
    }

    /**
     * Get record by index
     *
     * @param string $index
     * @param string $value
     * @return Model|null
     */
    public function findByUnique($index, $value)
    {
        return $this->findOne([$index => $value]);
    }

    /**
     * Select by id
     *
     * @param integer $id
     * @return Model|null
     */
    public function findById($id)
    {
        if ($this->enableCache) {
            $key = $this->getKey($id);
            $value = $this->cache->get($key);
            if (!$value) {
                $value = $this->loadFromDB($id);
                if ($value) {
                    $this->cache->set($key, $value);
                    $value = $this->getModel($value);
                }
            }

            return $value;
        }
        return $this->findOne(['id' => $id]);
    }

    /**
     * Select records
     *
     * @param array $where
     * @param array $options
     * @return array
     */
    public function find(array $where, $options = [])
    {
        if (!empty($where)) {
            $where = $this->revertFields($where);
        }
        $condition = $this->getChange();
        $where = array_merge($condition, $where);
        $builder = $this->builder();
        if ($this->enableCache) {
            $builder->field(['id']);
        }
        $builder->where($where);
        if (!empty($options)) {
            foreach ($options as $method => $option) {
                $builder = $builder->$method($option);
            }
        }

        $result = $builder->select();
        if (empty($result)) {
            return [];
        }
        if ($this->enableCache) {
            $ids = [];
            foreach ($result as $value) {
                $ids[] = $value['id'];
            }
            return $this->findByIds($ids);
        }
        $res = [];
        foreach ($result as $data) {
            $res[] = $this->getModel($data);
        }

        return $res;
    }

    /**
     * Select all with condition
     *
     * @param array $options
     * @return array
     */
    public function findAll($options = [])
    {
        $builder = $this->builder();
        if ($this->enableCache) {
            $builder->field(['id']);
        }
        if (!empty($options)) {
            $options = $this->revertFields($options);
            foreach ($options as $method => $option) {
                $builder = $builder->$method($option);
            }
        }
        $result = $builder->select();
        if (empty($result)) {
            return [];
        }
        if ($this->enableCache) {
            $ids = [];
            foreach ($result as $value) {
                $ids[] = $value['id'];
            }
            return $this->findByIds($ids);
        }
        $res = [];
        foreach ($result as $data) {
            $res[] = $this->getModel($data);
        }

        return $res;
    }

    /**
     * Wrap findById by ids
     *
     * @param $ids
     * @throws \Exception
     */
    public function findByIds($ids)
    {
        if (!is_array($ids)) {
            throw new \Exception('FindIds param ids must be array');
        }
        if ($this->enableCache) {
            $values = $this->loadFromCache($ids);
        } else {
            $values = $this->loadFromDB($ids);
        }

        if (!empty($values)) {
            $values = $this->getModel($values);
        }

        return $values;
    }

    /**
     * Store value
     *
     * @return static|null
     */
    public function save()
    {
        if (empty($this->attr)) {
            return null;
        }
        $this->before();
        $data = [];
        $result = null;
        if (!empty($this->pk)) {
            $data = $this->getChange();
            $result =  $this->update($data);
        } else {
            foreach ($this->fields as $field => $entity) {
                if (isset($this->attr[$field])) {
                    $data[$field] = $this->attr[$field];
                    $this->fields[$field]['value'] = $this->attr[$field];
                } else if (isset($entity['default'])) {
                    $data[$field] = $entity['default'];
                    $this->attr[$field] = $entity['default'];
                }
            }
            $this->free();
            $result = $this->insert($data);
        }

        $this->after();

        return $result;
    }
    /**
     *
     * @param [type] $id
     * @return void
     */
    public function increment($id)
    {
        foreach ($this->fields as $key => $entity) {
            if (isset($entity['auto']) && isset($entity['pk'])) {
                $this->pk[$key] = $id;
                break;
            }
        }
    }

    public function getKeys($ids) {
        $keys = [];
        foreach($ids as $id) {
            $keys[] = $this->getKey($id);
        }

        return $keys;
    }

    private function loadFromCache($ids)
    {
        $keys = $this->getKeys($ids);
        $values = $this->cache->getMultiple($keys);
        $len = count($ids);
        $valueLen = count($values);
        if ($len !== $valueLen) {
            $tmp = [];
            $ret = [];
            foreach ($values as $key => $value) {
                $tmp[$value['id']] = $value;
            }
            $missKeys = array_diff($ids, array_keys($tmp));
            $missValues = $this->loadFromDB($missKeys);
            $changed = $len - count($missValues) - $valueLen;
            if ($changed) {
                $missValues = array_pad($missValues, $len, null);
            }
            foreach($missValues as $key => $value) {
                if (isset($tmp[$value['id']])) {
                    continue;
                }
                $tmp[$value['id']] = $value;
            }
            $caches = [];
            foreach ($ids as $key => $id) {
                $ret[] = $tmp[$id];
                $key = $this->getKey($id);
                $caches[$key] = $tmp[$id];
            }
            $this->cache->setMultiple($caches);

            return $ret;
        }

        return $values;
    }

    private function loadFromDB($ids)
    {
        $builder = $this->builder();
        $operator = is_array($ids) ? '$in' : '$eq';
        $where = ['id' => [$operator => $ids]];
        $result = $builder->where($where)->select();

        return $result ? $result : [];
    }

    /**
     * execute sql
     *
     * @param string $sql
     * @param array $value
     * @return mixed
     */
    public function query($sql,array  $value = [])
    {
        return $this->pool->query($sql, $value);
    }

    /**
     * make model execute in a tansaction
     *
     * @param string $transactionId
     * @return self
     */
    public function withTransaction($transactionId)
    {
        $this->connection = (Pool::singleton($this->config))->getConnection($transactionId);

        return $this;
    }

    /**
     * start a transction
     *
     * @return string
     */
    public function startTransaction()
    {
        $connection = $this->pool->getConnection(true);
        $connection->startTransaction();

        return $this->transactionId = $connection->identify;
    }

    /**
     * commit current transction
     *
     * @return string
     */
    public function commit()
    {
        $connection = $this->getConnection();
        $connection->commit();
        $this->pool->releaseConnection($connection->identify);
    }
    /**
     * roolback current transction
     *
     * @return void
     */
    public function rollback()
    {
        $connection = $this->getConnection();
        $connection->rollback();

        $this->pool->releaseConnection($connection->identify);
    }

    /**
     * delete record
     *
     * @return mixed
     */
    public function remove()
    {
        if (!$this->pk) return false;
        return $this->delete(['id' => $this->pk]);
    }

    /**
     * Get Model instance
     *
     * @param $data
     * @return Model
     */
    public function getModel($data)
    {
        if (empty($data)) {
            return null;
        }
        $model = clone $this;
        $model->result = $model->convert($data);
        foreach ($this->fields as $field => $entity) {
            if (!isset($data[$entity['column']])) continue;
            $model->fields[$field]['value'] = $data[$entity['column']];
            if (isset($entity['pk'])) {
                $model->pk[$field] = $data[$entity['column']];
            }
        }


        return $model;
    }


    /**
     *  convert data
     *
     * @param array $data
     * @return array
     */
    public function convert(array $data)
    {
        $result = [];
        foreach ($this->fields as $field => $entity) {
            if (isset($data[$field])) {
                $result[$field] = $data[$field];
                continue;
            }
            $column = $entity['column'];
            if (!isset($data[$column])) continue;
            $result[$field] = $data[$column];
        }
        return $result;
    }

    /**
     * check change before update
     *
     * @return array
     */
    public function getChange()
    {
        $data = [];
        if (empty($this->attr)) {
            return $data;
        }
        foreach ($this->fields as $field => $entity) {
            if (isset($this->attr[$field]) && $entity['value'] != $this->attr[$field]) {
                $data[$field] = $this->attr[$field];
                $this->fields[$field]['value'] = $this->attr[$field];
            }
        }

        $this->free();

        return $data;
    }

    
    public function free()
    {
        $this->attr = [];
    }

    /**
     * before hook
     */
    protected function before()
    {

    }

    protected function after()
    {

    }


    public function __call($func, $args)
    {
        call_user_func_array([$this->builder(), $func], $args);
    }

    /**
     * Get attribute
     *
     * @param string $key
     * @return mixed|null
     */
    public function __get($key)
    {
        if (isset($this->result[$key])) {
            return $this->result[$key];
        }
        return null;
    }

    /**
     * Set attribute
     *
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value)
    {
        if (isset($this->fields[$key]))
            $this->attr[$key] = $value;
    }

    /**
     * revert fields
     *
     * @param array $fields
     * @return array
     */
    protected function revertFields($fields)
    {
        if (!$fields) return [];
        $res = [];
        foreach ($fields as $field => $value) {
            if (!isset($this->fields[$field])) continue;
            $column = $this->fields[$field]['column'];
            $res[$column] = $value;
        }

        return $res;
    }
    /**
     * transform to array
     *
     * @param array $object
     * @return array
     */
    public function toArray($object = [])
    {
        $result = [];
        if (!empty($object)) {
            foreach ($object as $model) {
                $result[] = $model->result;
            }
        } else {
            $result = $this->result;
        }

        return $result;
    }

    public function offsetSet($offset, $value)
    {
        if (isset($this->attr[$offset]))
            $this->attr[$offset] = $value;
    }


    public function offsetExists($offset)
    {
        return isset($this->attr[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->attr[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->attr[$offset] ?? $this->attr[$offset] ?? null;
    }
}

