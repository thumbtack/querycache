<?php

namespace QueryCache;

class ApcuCache extends BaseCache {
    public function __construct($enabled=true, $options=[]) {
        parent::__construct($enabled && function_exists('apcu_add'), $options);
    }

    /**
     * @inheritdoc
     */
    public function get($key) {
        if (!$this->is_enabled()) {
            return $this->result(false);
        }

        $found = false;
        // http://php.net/manual/en/function.apcu-fetch.php
        $data = apcu_fetch($key, $found);
        return $this->result($found, $data);
    }

    /**
     * @inheritdoc
     */
    public function get_multi($keys) {
        $res = [];
        $cache_keys = [];
        if ($this->is_enabled()) {
            $cache_keys = $keys;
        }

        // http://php.net/manual/en/function.apcu-fetch.php
        $data = count($cache_keys) > 0 ? apcu_fetch($cache_keys) : [];
        // This should only be hit if APCu suddenly disappears:
        // @codeCoverageIgnoreStart
        if (!is_array($data)) {
            $data = [];
        }
        // @codeCoverageIgnoreEnd

        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $res[$key] = $this->result(true, $data[$key]);
            } else {
                $res[$key] = $this->result(false);
            }
        }
        return $res;
    }

    /**
     * @inheritdoc
     */
    public function set($key_or_map, $val=null, $ttl=null) {
        if (!$this->is_enabled()) {
            return false;
        }

        if (is_string($key_or_map)) {
            $key_or_map = [ $key_or_map => $val ];
        }
        $ttl = $this->get_ttl($ttl);

        // http://php.net/manual/en/function.apcu-store.php
        $res = apcu_store($key_or_map, null, $ttl);
        return count($res) <= 0;
    }

    /**
     * @inheritdoc
     */
    public function delete($key_or_keys) {
        if (!$this->is_enabled()) {
            return false;
        }

        $cache_keys = is_string($key_or_keys) ? [$key_or_keys] : $key_or_keys;
        foreach ($cache_keys as $key) {
            // http://php.net/manual/en/function.apcu-delete.php
            apcu_delete($key);
            // have to assume success because there doesn't seem to be a way to
            // determine if a delete failed or if the key was just missing
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function clear() {
        if ($this->is_enabled()) {
            // http://php.net/manual/en/function.apcu-clear-cache.php
            apcu_clear_cache();
        }
    }
}
