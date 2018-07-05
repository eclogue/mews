<?php
/**
 * @license MIT
 * @copyright Copyright (c) 2018
 * @author: bugbear
 * @date: 2018/7/5
 * @time: 下午2:53
 */

namespace Mews;


class Schema
{
    protected $schema = [];

    protected $indexes = [];

    protected $engine = 'InnoDB';

    protected $charset = 'utf8';

    protected $fields = [];

    protected $table = '';

    public function __construct(string $table, array $fields, array $indexes = [])
    {
        var_dump($table);
        $this->fields = $fields;
        $this->table = $table;
        $this->indexes = $indexes;
    }

    public function build()
    {
        $schema = [];
        foreach ($this->fields as $field => $item) {
            $builder = [];
            $column = $item['column'];
            $type = $item['type'];
            $length = $this->getDefaultLength($type);
            if ($length) {
                $length = '(' . $length . ')';
            }

            $builder[] = '`' . $column .'`';
            $builder[] = $type;
            $builder[] = $length;
            if (!empty($item['unsigned'])) {
                $builder[] = 'unsigned';
            }

            $allowNull = !empty($item['null']);
            $default = '';
            if (!$allowNull) {
                $builder[] = 'NOT NULL';
            } else {
                $default = 'DEFAULT NULL';
            }

            if (isset($item['default'])) {
                if ($type === 'timestamp') {
                    $default = 'DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
                } else {
                    $default = "DEFAULT '{$item['default']}'";

                }
            }

            if ($default) {
                $builder[] = $default;
            }

            if (!empty($item['auto'])) {
                $builder[] = 'AUTO_INCREMENT';
                $this->indexes['id'] = ['type' => 'primary', 'column' => ['id']];
            }
            if (!empty($item['comment'])) {
                $builder[] = "COMMENT '{$item['comment']}'";
            }

            $schema[] = implode(' ', $builder);
        }


        foreach ($this->indexes as $key => $index) {
            $data = [];
            $type = $index['type'];
            $type = strtoupper($type);
            $type = $type === 'KEY' ? $type : $type . ' KEY';
            $column = implode(',', $index['column']);
            $data[] = $type;
            $data[] = $key;
            $data[] = '(' . $column . ')';
            $schema[] = implode(' ', $data);
        }

        $this->schema = $schema;

        return $this->schema;
    }

    public function getDefaultLength(string $type)
    {
        switch ($type) :
            case 'varchar': return 255;
            case 'char': return 255;
            case 'int': return 11;
            case 'tinyint': return 4;
            case 'smallint': return 6;
            default:  return '';
        endswitch;
    }

    public function tableInfo()
    {
        $sql = "CREATE TABLE `{$this->table}` (" . PHP_EOL;
        $schema = implode(',' . PHP_EOL, $this->schema);
        $sql .= $schema . PHP_EOL;
        $sql .= ")ENGINE={$this->engine} DEFAULT CHARSET={$this->charset};";

        return $sql;
    }
}