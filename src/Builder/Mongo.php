<?php


namespace Mews\Builder;

use Mews\Connector\ConnectorInterface;
use MongoDB\BSON\ObjectId;
use MongoDB\Collection;

class Mongo implements BuilderInterface {

    public $connector = null;

    public $filter = [];

    public $collectionName = '';

    public $isDebug = false;

    public $cursorStack = [];

    public function __construct(ConnectorInterface $connector)
    {
        $this->connector = $connector;
    }

    public function debug($debug)
    {
        $this->isDebug = $debug;
    }

    public function table(string $table)
    {
        $this->collectionName = $table;
        $this->connector->table($table);
    }

    public function where(array $filter)
    {
        $filter = $this->parseObjectId($filter);
        $this->filter = array_merge($this->filter, $filter);
        return $this;
    }

    public function select(array $options=[])
    {
        $options = array_merge($this->cursorStack, $options);
        $cursor = $this->connector->find($this->filter, $options);

        return $cursor->toArray();
    }

    public function count($options=[])
    {
        $count = $this->connector->count($this->filter);
        return ['count' => $count];
    }

    public function update(array $update, array $options=[])
    {
        return $this->connector->update($this->filter, $update, $options);
    }

    public function delete(array $options=[])
    {
        return $this->connector->delete($this->filter, $options);
    }

    public function insert(array $data, array $options=[])
    {
        return $this->connector->insert($data, $options);
    }

    public function getCollection(): Collection
    {
        return $this->connector->table($this->collectionName);
    }

    public function __call($name, $arguments)
    {
        $collection = $this->getCollection();
        if (is_callable([$collection, $name])) {
            return call_user_func_array([$collection, $name], $arguments);
        }

        $this->cursorStack[$name] = count($arguments) === 1 ? $arguments[0] : $arguments;

        return $this;
    }

    public function parseObjectId(array $filter)
    {
        $result = [];
        foreach ($filter as $key => &$item) {
            if ($key === '_id' && !($item instanceof ObjectId)) {
                $result[$key] = new ObjectId($item);
                continue;
            }
            if (is_array($item)) {
                $result[$key] = $this->parseObjectId($item);
                continue;
            }

            $result[$key] = $item;
        }

        return $result;
    }
}
