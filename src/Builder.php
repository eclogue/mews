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

    public function __construct()
    {
        $this->parser = new Parser();
    }

    public function table($table)
    {
        $this->table = $table;
        return $this;
    }

    public function field($field)
    {
        $this->fields = $this->wrapField($field);
        return $this;
    }

    public function where($condition)
    {
        list($sql, $values) = $this->parser->build($condition);
        $this->sql = $sql;
        foreach ($values as $key => $value) {
            $this->values[] = $value;
        }
        return $this;
    }

    public function order($orderBy)
    {
        foreach ($orderBy as $field => $sort) {
            $this->sql[] = 'ORDER BY `' . $field . '` ' . $sort;
        }
        return $this;
    }

    public function skip($offset)
    {
        $this->sql[] = 'OFFSET ' . $offset;
        return $this;

    }

    public function limit($limit)
    {
        $this->sql[] = 'LIMIT ' . $limit;
        return $this;
    }


    public function group($field)
    {
        $this->sql[] = 'GROUP BY `' . $field . '`';
        return $this;
    }

    public function select()
    {
        $sql = 'SELECT %s FROM `%s` %s';
        if(empty($this->fields)) {
            $fields = '*';
        } else {
            $fields = implode(',', $this->fields);
        }
        $sql = sprintf($sql, $fields, $this->table, $this->sql);
        $this->sql = $sql;
        return [$sql, $this->values];
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
        foreach ($data as $field => $value) {
            $field[] = '`' . $field . '`';
            $values[] = $value;
        }
        $fields = implode(',', $fields);
        $values = implode(',', $values);
        $sql = 'INSERT INTO `%s`(%s)VALUE(%s)';
        return $this->sql = sprintf($sql, $this->table, $fields, $values);
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
        return rtrim(implode(',', $handled), ',');
    }
}