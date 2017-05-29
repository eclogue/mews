<?php
/**
 * @license   https://github.com/Init/licese.md
 * @copyright Copyright (c) 2017
 * @author    : bugbear
 * @date      : 2017/3/12
 * @time      : 下午2:40
 */

namespace Mews;


class Builder
{
    public $sql = [];

    public $fields = [];

    public $table = '';

    public $values = [];

    public $parser;

    private $connection;

    public function __construct()
    {
        $this->parser = new Parser();
    }

    public function connect($source)
    {
        $this->connection = $source;
    }

    public function table($table)
    {
        $this->table = $table;
        return $this;
    }

    public function field($field)
    {
        $this->fields = $field;
        return $this;
    }

    public function where($condition)
    {
        list($sql, $values) = $this->parser->build($condition);
        $this->sql['where'] = $sql;
        var_dump('~~~~~~~~~~~~', $values);
        foreach ($values as $key => $value) {
            $this->values['where'][] = $value;
        }
        return $this;
    }

    public function order($orderBy)
    {
        foreach ($orderBy as $field => $sort) {
            $this->sql['order'] = 'ORDER BY `' . $field . '` ' . $sort;
        }
        return $this;
    }

    public function offset($offset)
    {
        $this->sql['offset'] = 'OFFSET ' . abs(intval($offset));
        return $this;

    }

    public function limit($limit)
    {
        $this->sql['limit'] = 'LIMIT ' . abs(intval($limit));
        return $this;
    }


    public function group($field)
    {
        $this->sql['group'] = 'GROUP BY `' . $field . '`';
        return $this;
    }

    public function select()
    {
        list($sql, $values) = $this->buildSelect();
        $res = $this->connection->query($sql, $values);
        $this->sql = [];
        $this->values = [];
        $this->fields = [];
        return $res;
    }

    protected function buildSelect()
    {
        $select = 'SELECT %s FROM `%s` %s';
        if (empty($this->fields)) {
            $fields = '*';
        } else if (is_array($this->fields)) {
            $fields = implode(',', $this->fields);
        } else {
            $fields = $this->fields;
        }
        $values = [];
        $sql = [];
        if (isset($this->sql['where'])) {
            $sql[] = $this->sql['where'];
            if (isset($this->values['where'])) {
                foreach ($this->values['where'] as $value) {
                    $values[] = $value;
                }
            }
        }
        if (isset($this->sql['group'])) {
            $sql[] = $this->sql['group'];
        }
        if (isset($this->sql['order'])) {
            $sql[] = $this->sql['order'];
        }
        if (isset($this->sql['limit'])) {
            $sql[] = $this->sql['limit'];
        }
        if (isset($this->sql['offset'])) {
            $sql[] = $this->sql['offset'];
        }
        $this->sql = implode(' ', $this->sql);
        $sql = sprintf($select, $fields, $this->table, $this->sql);
        return [$sql, $values];
    }

    public function delete()
    {
        $sql = 'DELETE FROM `%s` %s';
        $sql = sprintf($sql, $this->table, $this->sql);
        return $this->sql = $sql;
    }

    public function update($data)
    {
        $set = '';
        foreach ($data as $field => $value) {
            if (is_string($value)) {
                $set .= '`' . $field . '`=?';
                $this->values[] = $value;
                continue;
            }
            if (is_array($value)) {
                if (isset($value['$increment'])) {
                    $set .= '`' . $field . '`=' . $field . '+' . $value;
                } else {
                    $set .= '`' . $field . '`=?' . json_encode($value);
                    $this->values[] = $value;
                }
            }
        }
        $sql = 'UPDATE `%s` SET %s %s';
        $sql = sprintf($sql, $this->fields, $this->table, $this->sql);
        return $this->sql = $sql;
    }

    public function insert($data)
    {
        $fields = [];
        $values = [];
        $placeholder = [];
        foreach ($data as $field => $value) {
            $fields[] = '`' . $field . '`';
            $values[] = $value;
            $placeholder[] = '?';
        }
        $fields = implode(',', $fields);
        $placeholder = implode(',', $placeholder);
        $sql = 'INSERT INTO `%s`(%s)VALUE(%s)';
        $sql = sprintf($sql, $this->table, $fields, $placeholder);

        return [$sql, $values];
    }

    public function wrapField($fields)
    {
        $handled = [];
        foreach ($fields as $field) {
            if (!$field !== '*') {
                $handled[] = '`' . $field . '`';
            } else {
                $handled[] = $field;
            }
        }
        return trim(implode(',', $handled), ',');
    }
}