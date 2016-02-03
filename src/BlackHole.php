<?php

namespace QueryCache;

class BlackHole extends BaseCache {
    /**
     * @inheritdoc
     */
    public function get($key) {
        return $this->result(false);
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
        return true;
    }

    /**
     * @inheritdoc
     */
    public function delete($key_or_keys) {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function clear() {
    }
}
