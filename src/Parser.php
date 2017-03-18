<?php

namespace Mews;

class Parser
{

    public static $logical = [
        '$and' => ' AND ',
        '$or' => ' OR ',
        '$not' => ' NOT ',
//        '$xor',
    ];

    private static $operator = [
        '$eq' => '=',
        '$neq' => '!=',
        '$gt' => '>',
        '$lt' => '<',
        '$gte' => '>=',
        '$lte' => '<=',
        '$like' => 'LIKE',
        '$isNull' => 'IS NULL',
        '$isNotNull' => 'IS NOT NULL',
        '$in' => 'IN(%s)',
    ];

    public $tree = [];

    public $sql = '';

    public $values = [];


    public function __construct()
    {
        $this->sql = '';
    }

    /*
     * 递归入栈生成节点树
     * @param array $entities
     *
     * */
    public function generateNode($entities, $child = false)
    {
        foreach ($entities as $key => $value) {
            $node = [];
            $value = !is_array($value) ? ['$eq' => $value] : $value;
            if (!in_array($key, array_keys(self::$logical))) {
                $operator = array_keys($value);
                $operators = array_keys(self::$operator);
                $intersect = array_intersect($operator, $operators);
                if (count($intersect)) {
                    $node['type'] = 'field';
                    $node['name'] = $key;
                    $node['value'] = $value;
                    $length = count($this->tree);
                    $length = $length ? $length - 1 : 0;
                    $prev = isset($this->tree[$length]) ? $this->tree[$length] : [];
                    if (isset($prev['type']) && $prev['type'] === 'field') {
                        $this->tree[] = $this->getDefaultNode($child);
                    }
                    $this->tree[] = $node;
                } else if ($this->isIndexArray($value)) {
                    foreach ($value as $item) {
                        $this->generateNode($item, true);
                    }
                }
            } else {
                $node['type'] = 'operator';
                $node['name'] = self::$logical[$key];
                $node['value'] = $child ? 0 : 1;
                $this->tree[] = $node;
                $this->generateNode($value, true);
            }
        }
    }

    public function getDefaultNode($child)
    {
        return [
            'type' => 'operator',
            'name' => 'AND',
            'value' => $child ? 0 : 1,
        ];
    }


    public function isIndexArray($node)
    {
        if (!is_array($node)) return false;
        $keys = array_keys($node);
        return is_numeric($keys[0]);
    }

    public function build($entities)
    {
        $this->generateNode($entities);
        foreach ($this->tree as $key => $node) {
            if ($node['type'] === 'field') {
//                if(!$this->sql) {
//                    $this->sql .= ltrim($this->parseFieldNode($node), 'AND');
//                } else {
//                    $this->sql .=  $this->parseFieldNode($node);
//                }
                $this->sql .=  $this->parseFieldNode($node);

            } else {
                if ($node['value'] === 1) { // last child
                    $this->sql .= ')';
                }
                $this->sql .= $this->parseLogicalNode($node);
            }
        }

        $this->sql = 'WHERE(' . $this->sql . ')';

        $ret = [$this->sql, $this->values];
        $this->sql = '';
        $this->values = [];
        $this->tree = [];

        return $ret;
    }

    private function parseFieldNode($node)
    {
        $string = '';
        $filed = '`' . $node['name'] . '`';
        $connector = 'AND';
        foreach ($node['value'] as $operator => $value) {
            $temp = [$filed];
            $temp[] = self::$operator[$operator];
            $temp[] = '?';
            $temp[] = $connector;
            $string .= implode('', $temp);
            $this->values[] = $value;
        }

        return rtrim($string, $connector);
    }

    private function parseLogicalNode($node)
    {
        $string = '';
        $string .= $node['name'];
        if ($node['value'] === 1) {
            $string .= '(';
        }
        return $string;
    }

}

