<?php

/**
 * @license   https://github.com/Init/licese.md
 * @copyright Copyright (c) 2017
 * @author    : bugbear
 * @date      : 2017/05/30
 * @time      : 上午10:54
 */
namespace Mews;


class Model implements \ArrayAccess
{
    protected $db;

    protected $cache = null;

    protected $table = '';

    protected $flag = '';

    public $result = [];

    protected $debug = true;

    protected $attr = [];

    protected $pk;


    protected $fields = [
        'id' => ['column' => 'id', 'type' => 'int', 'pk' => true],
        'username' => ['column' => 'username', 'type' => 'string'],
    ];

    protected $builder;

    protected $lastSql = '';


    public function __construct($cache = null)
    {
        $this->builder = new Builder();
        $this->builder->table($this->table);
    }


    public function table($table)
    {
        $this->table = $table;
        $this->builder->table($this->table);
    }

    public function setCache($cache)
    {
        $this->cache = $cache;
    }

    public function init(array $db)
    {
        $this->db = DB::create($db);
        $this->builder->connect($this->db);
    }

    public function builder()
    {
        return $this->builder;
    }

//
//    public function cache()
//    {
//        $this->cache->register($this->table, $this->flag);
//        $key = $this->cacheKey();
//        $this->cache->set($key, $this->result);
//        return $this;
//    }

    public function cacheKey()
    {
        $str = json_encode($this->config) . strtolower($this->sql) . $this->flag;
        return md5($str);
    }

    public function count($where)
    {
        $where = $this->revertFields($where);
        $result = $this->builder
            ->field(['count(*) as count'])
            ->where($where)
            ->select();
        return $result[0]['count'] ?? 0;
    }


    public function register($value)
    {
        $key = $this->table . '#' . $this->flag;
        $this->cache->set($key, $value);
    }

    public function pure() {

    }

    public function update($data, $where)
    {
        $data = $this->revertFields($data);
        if ($this->debug) {
            $this->db->debug();
        }
        $this->before();
    }

    public function insert($data)
    {
        $data = $this->revertFields($data);
        list($this->lastSql, $value) = $this->builder->insert($data);
        $this->result = $this->db->execute($this->lastSql, $value);
        return $this->result;
    }

    public function delete($where)
    {
        $where = $this->revertFields($where);
        list($this->lastSql, $value) = $this->builder
            ->where($where)
            ->delete();
        $this->result = $this->db->execute($this->lastSql, $value);
        $this->after();

        return $this->result;
    }

    public function findOne($where)
    {
        $where = $this->revertFields($where);
        $result = $this->builder
            ->where($where)
            ->limit(1)
            ->select();
        if (empty($result)) {
            return null;
        }
        $result = array_pop($result);

        return $this->getModel($result);
    }

    public function findByIndex($index, $value)
    {
        return $this->findOne([$index => $value]);
    }

    public function findById($id)
    {
        return $this->findOne(['id' => $id]);
    }

    public function find($where, $options = [])
    {
        $where = $this->revertFields($where);
        $builder = $this->builder->where($where);
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

    public function findByIds($ids)
    {
        if (!is_array($ids)) {
            throw new \Exception('FindIds param ids must be array');
        }
        $this->find(['id' => ['$in' => $ids]]);
    }

    public function save()
    {
        if (empty($this->attr)) {
            return null;
        }
        $data = [];
        if ($this->pk) {
            $pkName = '';
            foreach ($this->fields as $field => $entity) {
                if (isset($this->attr[$field]) && $entity['value'] !== $this->attr[$field]) {
                    $data[$field] = $this->attr[$field];
                    $this->fields[$field]['value'] = $this->attr[$field];
                }
                if (isset($entity['pk'])) {
                    $pkName = $entity['column'];
                }
            }
            $condition = [
                $pkName => $this->pk,
            ];
            $this->update($data, $condition);
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
            $this->pk = $this->insert($data);
            $this->attr['id'] = $this->pk;
            return $this->pk;
        }

        return null;
    }

    public function increment($field, $value) {
        if ($this->fields[$field]['type'] !== 'int') {
            throw new \Exception('increment column must be integer');
        }
        $this->attr[$field] = $this->attr[$field] . ' + ' . $value;
    }

    public function remove()
    {
        if (!$this->pk) return false;
        return $this->delete(['id' => $this->pk]);
    }

    public function getModel($data)
    {
        $class = get_class($this);
        $model = new $class();
        $model->table = $this->table;
        $model->result = $this->convert($data);
        foreach ($this->fields as $field => $entity) {
            if (!isset($data[$entity['column']])) continue;
            $model->attr[$field] = $data[$entity['column']];
            $model->fields[$field]['value'] = $data[$entity['column']];
            if (isset($entity['pk'])) {
                $model->pk = $data[$entity['column']];
            }
        }

        return $model;
    }

    public function convert($data)
    {
        $result = [];
        foreach ($this->fields as $field => $entity) {
            $column = $entity['column'];
            if (!isset($data[$column])) continue;
            $result[$field] = $data[$column];
        }
        return $result;
    }


    public function before()
    {

    }

    public function after()
    {

    }


    public function __call($func, $args)
    {
        call_user_func_array([$this->builder, $func], $args);
    }

    public function __get($key)
    {
        if (isset($this->attr[$key])) {
            return $this->attr[$key];
        }
        return null;
    }

    public function __set($key, $value)
    {
        if (isset($this->fields[$key]))
            $this->attr[$key] = $value;
    }

    protected function revertFields($fields)
    {
        $res = [];
        foreach ($fields as $field => $value) {
            if (!isset($this->fields[$field])) continue;
            $column = $this->fields[$field]['column'];
            $res[$column] = $value;
        }

        return $res;
    }

    public function toArray($object)
    {
        $result = [];
        if (is_array($object)) {
            foreach ($object as $model) {
                $result[] = $model->result;
            }
        } else {
            $result = $object->result;
        }

        return $result;
    }

    public function offsetSet($offset, $value)
    {
        if (isset($this->fields[$offset]))
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

