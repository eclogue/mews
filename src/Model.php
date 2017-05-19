<?php

/**
 * @license   https://github.com/Init/licese.md
 * @copyright Copyright (c) 2016
 * @author    : bugbear
 * @date      : 2016/11/30
 * @time      : 上午10:54
 */
namespace Mews;

class Model implements \ArrayAccess
{
    protected $db;

    protected $cache = null;

    protected $table = '';

    protected $flag = '';

    protected $result = [];

    protected $debug = true;

    protected $attr = [];

    protected $pk;


    protected $fields = [
        'id' => ['column' => 'id', 'type' => 'int', 'pk' => true],
        'username' => ['column' => 'username', 'type' => 'string'],
    ];

    protected $builder;

    public $lastSql = '';


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


    public function register($value)
    {
        $key = $this->table . '#' . $this->flag;
        $this->cache->set($key, $value);
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
        list($this->lastSql, $value) = $this->builder
            ->where($where)
            ->limit(1)
            ->select();
        $result = $this->db->query($this->lastSql, $value);
        if (empty($result)) {
            return null;
        }
        $result = array_pop($result);

        return $this->fetch($result);
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

        list($this->lastSql, $value) = $builder->select();
        $result = $this->db->query($this->lastSql, $value);
        if (!$result) return [];
        $res = [];
        foreach ($result as $data) {
            $res[] = $this->fetch($data);
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
        list($this->lastSql, $value) = $builder->select();
        $result = $this->db->query($this->lastSql, $value);
        if (!$result) return null;
        $res = [];
        foreach ($result as $data) {
            $res[] = $this->map($data);
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
        foreach ($data as $field => $entity) {
            if (!isset($data[$entity['column']])) continue;
            $model->attr[$field] = $data[$entity['column']];
            $model->fields[$field]['value'] = $data[$entity['column']];
            if (isset($entity['pk'])) {
                $model->pk = $data[$entity['column']];
            }
        }

        return $model;
    }

    public function fetch($data)
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

    public function toArray()
    {
        return is_array($this->attr) ? $this->attr : [];
    }

    public function offsetSet($offset, $value)
    {
        if (isset($this->fields[$offset]))
            $this->attr[$offset] = $value;
    }


    public function offsetExists($offset)
    {
        return isset($this->fields[$offset]);
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

