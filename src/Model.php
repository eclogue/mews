<?php

/**
 * @license   https://github.com/Init/licese.md
 * @copyright Copyright (c) 2017
 * @author    : bugbear
 * @date      : 2017/05/30
 * @time      : 上午10:54
 */

namespace Mews;

use ArrayAccess;
use JsonSerializable;
use InvalidArgumentException;
use Mews\Connector\ConnectorInterface;
use Mews\Builder\BuilderInterface;

class Model implements ArrayAccess, JsonSerializable
{
    protected $db;

    protected $cache;

    protected $debug = true;

    protected $prefix = '';

    public $pk = [];

    private $config = [];

    private $transactionId = '';

    public $attr = [];

    protected $table = '';

    protected $enableCache = false;

    protected $pool = false;

    protected $connection;

    protected $indexes = [
        'id' => ['type' => 'primary', 'column' => ['id']],
    ];


    /**
     * table schema
     *
     * @var array
     */
    protected $fields = [
        'id' => ['column' => 'id', 'type' => 'int', 'pk' => true],
    ];

    /**
     * Model constructor.
     *
     * @param array $config
     * @param array $cache
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        if (isset($config['debug'])) {
            $this->debug = $config['debug'];
        }

        if (isset($config['prefix'])) {
            $this->prefix = $config['prefix'];
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
     * get Schema
     *
     * @return Schema
     */
    public function getSchema(): Schema
    {
        return new Schema($this->table, $this->fields, $this->indexes);
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
     * get cache key @fixme
     *
     * @param string $key
     * @return string
     */
    public function getKey($key)
    {
        $key = md5($this->table . ':' . $key);

        return $this->prefix . $key;
    }

    /**
     * get builder
     *
     * @return Builder
     */
    public function builder(): BuilderInterface
    {
        $connection = $this->getConnection();
        $release = null;
//        if ($this->pool) {
//            $release = function () use ($connection) {
//                $this->db->releaseConnection($connection->identify);
//            };
//            $release->bindTo($this);
//        }

        $builder = new Builder($connection, $this->transactionId);
        $builder = $builder->getBuilder($this->config['type']);
        $builder->debug($this->debug);
        $builder->table($this->table);

        return $builder;
    }

    protected function getConnection()
    {
        $connection = new Connector($this->config);

        return $connection->getConnection($this->transactionId);
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
     * @param array $update
     * @param array $where
     * @return mixed
     */
    public function update($where, $update = [])
    {
        $changed = $this->getChange();
        $changed = array_merge($changed, $update);
        if (empty($changed)) {
            return $this;
        }

        $this->before();
        $mapping = $this->revertFields($changed);
        $this->builder()->where($where)->update($mapping);
        $this->attr = array_merge($this->attr, $changed);
        $this->after();
        $this->free();

        return $this->newModel($this->attr);
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
        $id = $this->builder()->insert($data);
        $this->increment($id);
        $data['id'] = $id;

        return $this->newModel($data);
    }

    /**
     * Delete by where
     *
     * @param string $where
     */
    public function delete($where)
    {
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
            $builder->field($this->pk);
        }

        $result = $builder->where($where)
            ->limit(1)
            ->select();

        if (empty($result)) {
            return null;
        }

        $result = $result[0];
        if ($this->enableCache) {
            return $this->findById($result['id']);
        }

        return $this->newModel((array) $result);
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
                    $value = $this->newModel($value);
                }
            }

            return $value;
        }

        $filter = $this->getPKFilter($id);
        if (empty($filter)) {
            return null;
        }

        return $this->findOne($filter);
    }

    /**
     * Select records
     *
     * @param array $where
     * @param array $options
     * @return array
     * @throws \Exception
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
            $res[] = $this->newModel($data);
        }

        return $res;
    }

    /**
     * Select all with condition
     *
     * @param array $options
     * @return array
     * @throws \Exception
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
            $res[] = $this->newModel($data);
        }

        return $res;
    }

    /**
     * Wrap findById by ids
     * @param $ids
     * @return array
     * @throws \Exception
     */
    public function findByIds($ids)
    {
        if (!is_array($ids)) {
            throw new InvalidArgumentException('FindIds param ids must be array');
        }

        if ($this->enableCache) {
            $values = $this->loadFromCache($ids);
        } else {
            $values = $this->loadFromDB($ids);
        }

        if (!empty($values)) {
            $values = $this->newModel($values);
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
        if (!empty($this->pk)) {
            $result = $this->update();
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

    public function getKeys($ids)
    {
        $keys = [];
        foreach ($ids as $id) {
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
            foreach ($missValues as $key => $value) {
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
    public function query($sql, array $value = [])
    {
        return $this->db->query($sql, $value);
    }

    /**
     * make model execute in a tansaction
     *
     * @param string $transactionId
     * @return self
     */
    public function withTransaction($transactionId)
    {
        $this->transactionId = $transactionId;

        return $this;
    }

    /**
     * start a transction
     *
     * @return string
     */
    public function startTransaction()
    {
        $connection = $this->getConnection();
        if (is_callable([$connection, 'startTransaction'])) {
            $connection->startTransaction();

            return $this->transactionId = $connection->identify;
        }
    }

    /**
     * commit current transction
     *
     * @return boolean
     */
    public function commit()
    {
        $connection = $this->getConnection();
        if (is_callable([$connection, 'startTransaction'])) {
            $connection->commit($this->transactionId);

            return true;
        }

        return false;
    }

    /**
     * rollback current transaction
     *
     * @return void
     */
    public function rollback()
    {
        $connection = $this->getConnection();
        if (is_callable([$connection, 'rollback'])) {
            $connection->rollback();
        }
    }


    /**
     * delete record
     *
     * @return mixed
     */
    public function remove()
    {
        if (empty($this->pk)) {
            return false;
        }

        return $this->delete($this->getPKFilter($this->pk));
    }

    public function getPKFilter($pk)
    {
        foreach ($this->fields as $key => $field) {
            if (isset($field['pk']) && $field['pk']) {
                return [$key => $pk];
            }
        }
    }

    /**
     * Get Model instance
     *
     * @param $data
     * @return Model
     */
    public function newModel($data)
    {
        var_dump('new model', $data);
        if (empty($data)) {
            return null;
        }

        $model = clone $this;
        $model->attr = $model->convert($data);
        foreach ($this->fields as $field => $entity) {
            if (!isset($data[$entity['column']])) {
                continue;
            }

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
    public function convert(array $data): array
    {
        $result = [];
        foreach ($this->fields as $field => $entity) {
            if (isset($data[$field])) {
                $result[$field] = $data[$field];
                continue;
            }

            $column = $entity['column'];
            if (!isset($data[$column])) {
                continue;
            }

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
            if (isset($entity['pk']) && $entity['pk']) {
                continue;
            }

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
            if (!isset($this->fields[$field])) {
                continue;
            }

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
                if ($model instanceof Model) {
                    $result[] = $model->attr;
                } else {
                    $result[] = (array)$model;
                }
            }
        } else {
            $result = array_merge($this->attr, $this->getChange());
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

    public function jsonSerialize()
    {
        return $this->toArray();
    }
}

