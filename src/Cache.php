<?php
/**
 * @license   https://github.com/Init/licese.md
 * @copyright Copyright (c) 2016
 * @author    : bugbear
 * @date      : 2016/12/2
 * @time      : 下午3:49
 */

namespace Mews;


class Cache
{

    private $cache = null;

    private $config = null;

    public $prefix = 'mews_';

    public $enable = false;

    public $expire = 600;

    public function __construct($config)
    {
        $this->config = $config;
    }


    public static function getCache($config)
    {
        $config = new static($config);
        return $config;
    }

    public function get($key, $immediate = true)
    {
        if (!$this->enable) return null;
        $key = $this->getKey($key);
        $data = $this->cache->hGetAll($key);
        if (!$data || !isset($data['value']) || !isset($data['change'])) return null;
        if ($immediate && $data['changed']) return null;
        return $data['value'];
    }

    public function set($key, $value, $expire = 0)
    {
        if (!$this->enable) return null;
        $expire = $expire ?: $this->expire;
        $data = ['value' => $value, 'changed' => 0];
        $this->cache->hMSet($key, $data);
        $this->cache->expire($key, $expire);
        return true;
    }


    public function flag($table, $unique)
    {
        $this->flag = $table . $unique;
        return $this;
    }

    public function register($string, $table)
    {
        $string = $this->sort($string);
        $setKey = $this->prefix . $table;
        $list = $this->cache->lRange($setKey, 0, -1);

        if (!$list || in_array($string, $list)) return true;
        $length = count($list);
        if ($length >= $this->length) {
            $this->cache->rPop();
        }
        $this->cache->lPush($setKey, $string);
        if (!$length) {
            $this->cache->expire($setKey, $this->registryExpire);
        }

        return true;
    }

    public function update($force = true)
    {
        $data = $this->cache->lRange($this->flag);
        if (!$data) return false;
        foreach ($data as $key) {
            if ($force) {
                $this->cache->del($key);
            } else {
                $this->cache->hSetNx($key, 'changed', 1);
            }
        }

        return true;
    }

    public function enable($enable)
    {
        $this->enable = boolval($enable);
    }

    public function sort($param)
    {
        if (is_array($param)) $param = implode(sort($param));
        $string = strtolower($param);

        return $string;
    }

    public function getKey($key)
    {
        return md5($this->prefix . $key . $this->flag);
    }

    public function flush()
    {
        $list = $this->cache->lRange($this->flag, 0, -1);
        if(!count($list)) return true;
        foreach ($list as $key) {
            $this->cache->del($key);
        }

        return true;
    }

    public function cleanRegistry()
    {

    }

}