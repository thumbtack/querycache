<?php

namespace QueryCache;

class LocalCache extends BaseCache {
    private static $local = [];

    /**
     * @inheritdoc
     */
    public function get($key) {
        if (!$this->is_enabled()) {
            return $this->result(false);
        }

        $found = array_key_exists($key, self::$local);
        $data = $found ? self::$local[$key] : false;
        return $this->result($found, $data);
    }

    /**
     * @inheritdoc
     */
    public function get_multi($keys) {
        $res = [];
        foreach ($keys as $key) {
            $res[$key] = $this->get($key);
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
            self::$local[$key_or_map] = $val;
        } else {
            self::$local = array_replace(self::$local, $key_or_map);
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    public function delete($key_or_keys) {
        if (!$this->is_enabled()) {
            return false;
        }

        $keys = is_string($key_or_keys) ? [$key_or_keys] : $key_or_keys;
        foreach ($keys as $key) {
            unset(self::$local[$key]);
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    public function clear() {
        if ($this->is_enabled()) {
            self::$local = [];
        }
    }
}
