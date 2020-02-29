<?php


namespace Mews\Builder;

use Mews\Connector\ConnectorInterface;

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
    }

    public function where(array $filter)
    {
        $this->filter = array_merge($this->filter, $filter);
        return $this;
    }

    public function select(array $options=[])
    {
        $cursor = $this->connector->find($this->filter, $this->cursorStack);

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

    public function getCollection()
    {
        return $this->connector->table($this->collectionName);
    }

    public function __call($name, $arguments)
    {
        $collection = $this->getCollection();
        if (is_callable([$collection, $name])) {
            return $collection->$name($arguments);
        }
        $this->cursorStack[$name] = $arguments;

        return $this;
    }
}
