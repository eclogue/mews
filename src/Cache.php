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
use Predis\Client;

class Cache implements CacheInterface
{

    private $client;

    private $config = [];

    public $prefix = '';

    private $isEnable = true;

    private $ttl = 600;

    protected $hashTag = false;

    protected $table = '';

    public function __construct($config)
    {
        $this->config = $config;
        $this->init($config);
    }

    public function init($config)
    {
        if (isset($config['prefix'])) {
            $this->prefix = $config['prefix'];
        }
        if (isset($config['ttl'])) {
            $this->ttl = $config['ttl'];
        }
        $connect = $config['servers'];
        $options = $config['options'] ?? [];
        $this->client = new Client($connect, $options);
    }

    /**
     * @param array $config
     *
     * @return null|Client
     */
    public function getClient($config)
    {

        return $this->client;
    }

    /**
     * enable cache
     *
     * @param bool $enable
     */
    public function enable($enable)
    {
        $this->isEnable = boolval($enable);
    }

    /**
     * set cache key to hash tag mode @todo
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
     * @param string $key
     * @param number $ttl
     * @return bool
     */
    public function expire($key, $ttl)
    {
        if (is_array($key)) {
            $pipe = $this->client->pipeline();
            foreach ($key as $item) {
                $pipe->expire($item, $ttl);
            }
            $pipe->execute();
        } else {
            $this->client->expire($key, $ttl);
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

        $value = $this->client->get($key);
        $value = $value ? unserialize($value) : $default;

        return $value;
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
        $value = serialize($value);
        $ttl = $ttl ?? $this->ttl;
        $pipe = $this->client->pipeline();
        $pipe->set($key, $value)
            ->expire($key, $ttl)
            ->execute();

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
        return $this->client->del($key);
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     *
     * @return bool
     */
    public function clear()
    {
        return $this->client->flushAll();
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
        $data = $this->client->mget($keys);
        if (!$data) {
            return $default;
        }
        $result = [];
        foreach ($data as $value) {
            if ($value) {
                $result[] = unserialize($value);
            }
        }

        return $result;
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
        $ttl = $ttl ?? $this->ttl;
        $pipe = $this->client->pipeline();
        foreach($values as $key => $value) {
            $value = serialize($value);
            $pipe->set($key, $value);
            $pipe->expire($key, $ttl);
        }
        $pipe->execute();

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

        $this->client->del($keys);

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

        return $this->client->exists($key);
    }

}
