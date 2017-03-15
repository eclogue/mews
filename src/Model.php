<?php

/**
 * @license   https://github.com/Init/licese.md
 * @copyright Copyright (c) 2016
 * @author    : bugbear
 * @date      : 2016/11/30
 * @time      : 上午10:54
 */
namespace Mews;

class Model
{
    protected $db;

    protected $cache = null;

    public $table = '';

    protected $flag = '';

    protected $result = '';

    protected $debug = false;

    public $attr = [];

    public $fields = [
        'id' => ['column' => 'id', 'type' => 'int', 'pk' => true],
        'username' => ['column' => 'username', 'type' => 'string'],
    ];

    protected $builder;

    public $lastSql = '';

    public $pk;


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

//    public function table($table)
//    {
//        $this->table = $table;
//        return $this;
//    }

    public function update($data, $where)
    {
        if ($this->debug) {
            $this->db->debug();
        }
        $this->before();

    }

    public function insert($data)
    {
        list($this->lastSql, $value) = $this->builder->insert($data);
        $this->result = $this->db->execute($this->lastSql, $value);

        return $this->result;
    }

    public function delete($where)
    {
        list($this->lastSql, $value) = $this->builder
            ->where($where)
            ->delete();
        $this->result = $this->db->execute($this->lastSql, $value);
        $this->after();

        return $this->result;
    }

    public function findOne($where)
    {

        list($this->lastSql, $value) = $this->builder
            ->where($where)
            ->limit(1)
            ->select();
        $result = $this->db->query($this->lastSql, $value);
        if ($result) $result = array_pop($result);
        return $this->map($result);
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

        $builder = $this->builder->where($where);
        if (!empty($options)) {
            foreach ($options as $method => $option) {
                $builder = $builder->$method($option);
            }
        }

        list($this->lastSql, $value) = $builder->select();
        $result = $this->db->query($this->lastSql, $value);
        $res = [];
        foreach($result as $data) {
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
        $data = [];
        foreach ($this->fields as $field => $entity) {
            if ($entity['value'] !== $this->attr[$field]) {
                $data[$entity['column']] = $this->attr[$field];
                $this->fields[$field]['value'] = $this->attr[$field];
            }
        }

        if (!empty($data)) {
            if($this->pk) {
                $condition = [
                    'id' => $this->pk,
                ];
                $this->update($data, $condition);
            } else {
                $this->insert($data);
            }
        }
    }

    public function remove()
    {
        if(!$this->pk)  return false;
        return $this->delete(['id' => $this->pk]);
    }

    public function map($data)
    {
        $model = new self();
        foreach ($this->fields as $field => $entity) {
            $model->attr[$field] = $data[$entity['column']];
            $model->fields[$field]['value'] = $data[$entity['column']];
            if (isset($entity['pk'])) {
                $model->pk = $data[$entity['column']];
            }
        }

        return $model;
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

    protected function checkFields($fields)
    {
        if (is_string($fields)) return isset($this->fields[$fields]);
        foreach ($fields as $field => $value) {
            if (!isset($this->fields[$field])) return false;
        }

        return true;
    }
}

