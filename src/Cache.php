<?php
/**
 * @license   https://github.com/Init/licese.md
 * @copyright Copyright (c) 2016
 * @author    : bugbear
 * @date      : 2016/12/2
 * @time      : 下午3:49
 */

namespace Mews;

use Psr\SimpleCache\CacheInterface;
use Redis;

class Cache implements CacheInterface
{

    private $cache;

    private $config = [];

    public $prefix = '';

    private $isEnable = false;

    private $ttl = 600;

    protected $hashTag = false;

    protected $table = '';

    public function __construct($config, $prefix = 'mews', $ttl = 600)
    {
        $this->config = $config;
        $this->prefix = $prefix;
        $this->ttl = $ttl;
        $this->cache = $this->getCache($config);
    }

    /**
     * @param array $config
     *
     * @return null|Redis
     */
    public function getCache($config)
    {
        if (!class_exists(Redis::class)) {
            $this->enable(false);
            return null;
        }
        $config = new Redis($config);

        return $config;
    }

    /**
     * enable cache
     *
     * @param boolean $enable
     */
    public function enable($enable)
    {
        $this->isEnable = boolval($enable);
    }

    /**
     * set cache key to hash tag mode
     *
     * @param bool $hash
     * @return $this
     */
    public function hash($hash = true)
    {
        $this->hashTag = $hash;

        return $this;
    }

    public function setTable($tableName)
    {
        $this->table = $tableName;
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

    public function expire($key, $ttl)
    {
        if (is_array($key)) {
            $transaction = $this->cache->multi();
            foreach ($key as $item) {
                $transaction->expire($item, $ttl);
            }
            $transaction->exec();
        } else {
            $this->cache->expire($key, $ttl);
        }

        return true;
    }

    /**
     * Fetches a value from the cache.
     *
     * @param string $key     The unique key of this item in the cache.
     * @param mixed  $default Default value to return if the key does not exist.
     * @return mixed The value of the item from the cache, or $default in case of cache miss.
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function get($key, $default = null)
    {
        if (!$this->isEnable) {
            return $default;
        }

        return $this->cache->get($key);
    }

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param string                $key   The key of the item to store.
     * @param mixed                 $value The value of the item to store, must be serializable.
     * @param null|int|DateInterval $ttl   Optional. The TTL value of this item. If no value is sent and
     *                                     the driver supports TTL then the library may set a default value
     *
     * @return boolean
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function set($key, $value, $ttl = null)
    {
        if (!$this->isEnable) {
            return false;
        }
        $this->cache->set($key, $value);
        $this->expire($key, $ttl);

        return true;
    }

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The unique cache key of the item to delete.
     *
     * @return boolean
     */
    public function delete($key)
    {
        return $this->cache->del($key);
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     *
     * @return bool
     */
    public function clear()
    {
        return $this->cache->flushAll();
    }

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable $keys    A list of keys that can obtained in a single operation.
     * @param mixed    $default Default value to return for keys that do not exist.
     *
     * @return mixed
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getMultiple($keys, $default = null)
    {
        if (!$this->isEnable) {
            return $default;
        }
        $data = $this->cache->mget($keys);
        if ($data) {
            return $default;
        }

        return $data;
    }

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable              $values A list of key => value pairs for a multiple-set operation.
     * @param null|int|DateInterval $ttl    Optional. The TTL value of this item. If no value is sent and
     *                                      the driver supports TTL then the library may set a default value
     *                                      for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function setMultiple($values, $ttl = null)
    {
        if (!$this->isEnable) {
            return false;
        }
        $this->cache->mset($values);
        $keys = array_keys($values);
        $this->expire($keys, $ttl);

        return true;
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable $keys A list of string-based keys to be deleted.
     * @return bool True if the items were successfully removed. False if there was an error.
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function deleteMultiple($keys)
    {
        if (!$this->isEnable) {
            return false;
        }

        $this->cache->delete($keys);

        return true;
    }

    /**
     * Determines whether an item is present in the cache.
     *
     * NOTE: It is recommended that has() is only to be used for cache warming type purposes
     * and not to be used within your live applications operations for get/set, as this method
     * is subject to a race condition where your has() will return true and immediately after,
     * another script can remove it making the state of your app out of date.
     *
     * @param string $key The cache item key.
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function has($key)
    {
        if (!$this->isEnable) {
            return false;
        }

        return $this->cache->exists($key);
    }

}
