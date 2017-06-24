<?php

namespace Mews;

use PHPUnit\Framework\Exception;

class Parser
{

    public static $logical = [
        '$and' => ' AND ',
        '$or' => ' OR ',
        '$xor' => ' XOR ',
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
        '$in' => 'function',
        '$inc' => true,
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
            echo $key . PHP_EOL;
            if (!isset(self::$logical[$key])) {
                $operator = array_keys($value);
                $operators = array_keys(self::$operator);
                $intersect = array_intersect($operator, $operators);
                if (count($intersect)) {
                    $node['type'] = 'field';
                    $node['name'] = $key;
                    $node['value'] = $value;
                    $node['child'] = $child;
                    $node['connector'] = ' AND ';
                    $this->tree[] = $node;
                } else if ($this->isIndexArray($value)) {
                    foreach ($value as $item) {
                        $this->generateNode($item, true);
                    }
                }
            } else {
                $node['type'] = 'logical';
                $node['name'] = self::$logical[$key];
                $node['child'] = $child;
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
        $sql = '';
        $prev = [];
        $inChildren = 0;
        foreach ($this->tree as $key => $node) {
            if ($node['type'] === 'field') {
                if (isset($prev['type']) && $prev['type'] !== 'field' && !$node['child']) {
                    $sql .= ')';
                }
                $sql .= $this->parseFieldNode($node);
            } else {
                $sql = preg_replace('#(and|or|xor)$#i', '', rtrim($sql));
                $sql .= $this->parseLogicalNode($node);
                if (!$node['child']) {
                    $sql .= '(';
                    $inChildren++;
                } else {
                    if ($inChildren) {
                        $sql .= ')';
                        $inChildren--;
                    }
                }
            }

            $prev = $node;
        }

        $sql = rtrim($sql);
        $sql = preg_replace('#(AND|OR|XOR)$#i', '', rtrim($sql));
        if ($inChildren) {
            $sql .= ')';
        }
        $sql = 'WHERE(' . $sql . ')';
        $ret = [$sql, $this->values];
        $this->values = [];
        $this->tree = [];

        return $ret;
    }

    private
    function parseFieldNode($node)
    {
        $string = '';
        $filed = '`' . $node['name'] . '`';
        $connector = strtoupper(substr($node['connector'], 1));
        foreach ($node['value'] as $operator => $value) {
            $temp = [$filed];
            $temp[] = ' ';
            if ($operator === '$inc') {
                $temp = $this->increment($node['name'], $value);
            } else if (self::$operator[$operator] === 'function') {
                $func = substr($operator, 1);
                $temp[] = $this->sqlFunction($func);
                $value = is_array($value) ? implode(',', $value) : $value;
            } else {
                $temp[] = self::$operator[$operator];
                $temp[] = ' ? ';

            }
            $temp[] = $connector;
            $string .= implode('', $temp);
            $this->values[] = $value;
        }

        return $string;
//        return rtrim($string, ' ' . $node['connector']);
    }

    private
    function parseLogicalNode($node)
    {
        $string = ' ' . $node['name'] . ' ';
        return $string;
    }

    protected
    function sqlFunction($name)
    {
        return strtoupper($name) . ' (?) ';
    }

    protected
    function increment($field, $value)
    {
        if (!is_numeric($value)) {
            throw new Exception('mews increment value must be number');
        }
        return $field .= '=' . $field . ' + ' . $value;
    }

}

