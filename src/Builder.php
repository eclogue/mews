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
        $select = 'SELECT %s FROM `%s` %s';
        if (empty($this->fields)) {
            $fields = '*';
        } else if (is_array($this->fields)) {
            $fields = implode(',', $this->fields);
        } else {
            $fields = $this->fields;
        }
        list($sql, $values) = $this->toSql();
        $sql = sprintf($select, $fields, $this->table, $sql);
        $res = $this->connection->query($sql, $values);
        $this->sql = [];
        $this->values = [];
        $this->fields = [];
        return $res;
    }

    protected function toSql()
    {
        $values = [];
        $sql = [];
        if (isset($this->sql['where'])) {
            $sql[] ='WHERE';
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
        $sql = implode(' ', $sql);
        return [$sql, $values];
    }

    public function delete()
    {
        $delete = 'DELETE FROM `%s` %s';
        list($sql, $values) = $this->toSql();
        $sql = sprintf($delete, $this->table, $sql);
        $res = $this->connection->query($sql, $values);
        $this->sql = [];
        $this->values = [];
        $this->fields = [];
        return $res;
    }

    public function update($data)
    {
        $set = '';
        $setVal = [];
        foreach ($data as $field => $value) {
            if (is_array($value)) {
                if (isset($value['$increment'])) {
                    $set .= '`' . $field . '`=' . $field . '+' . $value;
                } else {
                    $set .= '`' . $field . '`=?' . json_encode($value);
                    $setVal[] = $value;
                }
            } else {
                $set .= '`' . $field . '`=?';
                $setVal[] = $value;
            }
        }
        list($sql, $values) = $this->toSql();
        $values = array_merge($setVal, $values);
        $update = 'UPDATE `%s` SET %s %s';
        $sql = sprintf($update, $this->table, $set, $sql);
        $res = $this->connection->query($sql, $values);
        $this->sql = [];
        $this->values = [];
        $this->fields = [];
        return $res;
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
        $this->connection->query($sql, $values);
        $id = $this->connection->lastInsertId();
        $this->sql = [];
        $this->values = [];
        $this->fields = [];
        return $id;
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