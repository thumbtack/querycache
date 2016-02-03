<?php

use QueryCache\Query;

/**
 * Class QueryGuts
 *
 * This class should not be used it exists to expose the guts of the Query
 * class for testing
 */

class QueryGuts extends Query {
    public function get_read() {
        return $this->get_read_db();
    }

    public function get_write() {
        return $this->get_write_db();
    }

    public function cache() {
        return $this->get_cache();
    }

    public function sort_by_length($ary) {
        return $this->length_sort($ary);
    }

    public function order_by($ary, $order_by) {
        return $this->format_results(null, $order_by, $ary);
    }

    public function to_map($map_format, $rows) {
        return $this->format_results($map_format, null, $rows);
    }

    public function validate($sql, $params_map) {
        return $this->validate_query($sql, $params_map);
    }

    public function flatten_params($tokens, $params_map) {
        return $this->flatten_params_map($tokens, $params_map);
    }

    public function to_sql($params_map, $sql) {
        return $this->parameterize_sql($params_map, $sql);
    }

    public function cache_key_from_array($key_format, $row) {
        return $this->cache_key_from_row($key_format, $row);
    }

    public function cache_keys_from_params($key_format, $params_map) {
        return $this->cache_key_params_map($key_format, $params_map);
    }

    public function cached_result_set($key_format, array &$params_map) {
        return $this->read_cached_result_set($key_format, $params_map);
    }

    public function cached_rows($key_format, array &$params_map) {
        return $this->read_cached_rows($key_format, $params_map);
    }

    public function write_results($key_format, $results, $ttl) {
        return $this->write_cached_result_set($key_format, $results, $ttl);
    }

    public function write_rows($key_format, array $rows, $ttl) {
        return $this->write_cached_rows($key_format, $rows, $ttl);
    }
}
