<?php

declare(strict_types=1);

namespace Inpsyde\Fasti\Tests;

/**
 * phpcs:disable
 */
class DummyWpdb extends \wpdb
{
    public $nextResultRows = [];
    public $nextGetVarResult = '';
    public $nextQueryResult = null;
    public $nextNumQueryResult = [null, null];

    public function __construct()
    {
        $this->base_prefix = 'wp_';
        $this->prefix = 'wp_1_';
        $this->queries = [];

        foreach ($this->tables as $table) {
            $this->{$table} = $this->prefix . $table;
        }

        foreach ($this->global_tables as $table) {
            $this->{$table} = $this->base_prefix . $table;
        }

        foreach ($this->ms_global_tables as $table) {
            $this->{$table} = $this->base_prefix . $table;
        }
    }

    public function get_charset_collate()
    {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';
    }

    public function query($query)
    {
        $this->queries[] = [$query, 0, '', time(), []];
        $this->last_query = $query;

        if ($this->nextQueryResult !== null) {
            return $this->nextQueryResult;
        }

        [$num, $result] = $this->nextNumQueryResult;
        if (is_int($num) && count($this->queries) === $num) {
            return $result;
        }

        return 1;
    }

    public function get_results($query = null, $output = OBJECT)
    {
        parent::get_results($query, $output);
        $rows = $this->nextResultRows;
        $this->nextResultRows = [];

        return $rows;
    }

    public function get_var($query = null, $x = 0, $y = 0)
    {
        parent::get_var($query, $x, $y);
        $result = $this->nextGetVarResult;
        $this->nextGetVarResult = '';

        return $result;
    }

    public function _real_escape($string)
    {
        return $this->add_placeholder_escape(addslashes((string)$string));
    }
}

