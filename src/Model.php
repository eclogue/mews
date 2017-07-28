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

    protected $cache = null;


    protected $flag = '';


    protected $debug = true;


    protected $pk = [];

    private $config = [];

    private $transactionId = '';


    private $attr = [];

    private $result = [];

    protected $table = '';



    protected $fields = [
        'id' => ['column' => 'id', 'type' => 'int', 'pk' => true],
        'username' => ['column' => 'username', 'type' => 'string'],
    ];


    protected $lastSql = '';

    /**
     * Model constructor.
     *
     * @param array $config
     * @param array $cache
     */
    public function __construct(array $config, array $cache = [])
    {
        $this->config = $config;
        $this->pool = Pool::singleton($config);
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
    }

    /**
     * get builder
     *
     * @return Builder
     */
    public function builder()
    {
        $connection = $this->getConnection();
        $builder = new Builder($connection); // 如果
        $builder->table($this->table);

        return $builder;
    }

    private function getConnection()
    {
        if ($this->transactionId) {
            return $this->pool->getConnection($this->transactionId);
        }

        return $this->pool;
    }

//
//    public function cache()
//    {
//        $this->cache->register($this->table, $this->flag);
//        $key = $this->cacheKey();
//        $this->cache->set($key, $this->result);
//        return $this;
//    }

    /**
     * @return string
     */
    public function cacheKey()
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
    public function count(array $where)
    {
        $where = $this->revertFields($where);
        $result = $this->builder()
            ->field(['count(*) as count'])
            ->where($where)
            ->select();
        return $result[0]['count'] ?? 0;
    }

    /**
     * @param $value
     */
    public function register($value)
    {
        $key = $this->table . '#' . $this->flag;
        $this->cache->set($key, $value);
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
        $mapping = $this->convert($changed);
        $this->builder()->where($where)->update($mapping);
        $this->result = array_merge($this->result, $changed);
        $this->before();

        var_dump($this->result);

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
        if (isset($this->fields[$id])) {
            $data['id'] = $id;
        }

        return $this->getModel($data);
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
        $result = $this->builder()
            ->where($where)
            ->limit(1)
            ->select();
        if (empty($result)) {
            return null;
        }
        $result = array_pop($result);

        return $this->getModel($result);
    }

    /**
     * Get record by index
     *
     * @param string $index
     * @param string $value
     * @return Model|null
     */
    public function findByIndex($index, $value)
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
        $where = $this->revertFields($where);
        $builder = $this->builder()->where($where);
        if (!empty($options)) {
            foreach ($options as $method => $option) {
                $builder = $builder->$method($option);
            }
        }

        $result = $builder->select();
        if (!$result) return [];
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
        if (!empty($options)) {
            $options = $this->revertFields($options);
            foreach ($options as $method => $option) {
                $builder = $builder->$method($option);
            }
        }
        $result = $builder->select();
        if (!$result) {
            return [];
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
        $this->find(['id' => ['$in' => $ids]]);
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
        $data = [];
        if (!empty($this->pk)) {
            foreach ($this->fields as $field => $entity) {
                if (isset($this->attr[$field]) && $entity['value'] !== $this->attr[$field]) {
                    $data[$field] = $this->attr[$field];
                    $this->fields[$field]['value'] = $this->attr[$field];
                }
            }
            return $this->update($data, $this->pk);
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
            return $this->insert($data);
        }
    }

    public function increment($field, $value)
    {
        if (!is_numeric($value)) {
            throw new \Exception('increment column must be integer');
        }
        $this->attr[$field] = $this->attr[$field] . ' + ' . $value;
    }

    public function query($sql, $value = [])
    {
        return $this->pool->query($sql, $value);
    }

    public function withTransaction($transactionId)
    {
        $this->connection = (Pool::singleton($this->config))->getConnection($transactionId);
        return $this;
    }

    public function startTransaction()
    {
        $this->connection->transaction();
        return $this->connection->identify;
    }

    public function commit()
    {

    }

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
        $model = clone $this;
        $model->result = $model->convert($data);
        foreach ($this->fields as $field => $entity) {
            if (!isset($data[$entity['column']])) continue;
            $model->attr[$field] = $data[$entity['column']];
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
        foreach ($this->fields as $field => $entity) {
            if (isset($this->attr[$field]) && $entity['value'] != $this->attr[$field]) {
                $data[$field] = $this->attr[$field];
                $this->fields[$field]['value'] = $this->attr[$field];
            }
        }

        return $data;
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
        if (isset($this->attr[$key])) {
            return $this->attr[$key];
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

