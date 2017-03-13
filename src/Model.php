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

    public $fields = [];

    protected $builder;

    public $lastSql = '';

    private $_preSet = [];

    public $id;


    public function __construct($cache = null)
    {
//        $this->cache = Cache::getCache($cache);
        $this->builder = new Builder();
        $this->builder->table('user');
    }

    public function setCache($cache)
    {
        $this->cache = $cache;
    }

    public function init(array $db)
    {
        $this->db = new DB();
        $this->db->add($db);
        $this->db->connect();
    }

    public function builder()
    {
        return $this->builder();
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
        $this->result = $this->db->query($this->lastSql, $value);
        if ($this->result) $this->result = array_pop($this->result);

        return $this->result;
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
        $this->result = $this->db->query($this->lastSql, $value);

        return $this->result;
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
        if (isset($this->attr[$key]) && isset($this->result[$key])) {
            return $this->result[$key];
        }
        return null;
    }

    public function __set($key, $value)
    {
        $this->_preSet[$key] = $value;
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

