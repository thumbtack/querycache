<?php

namespace QueryCache;

class CacheStack implements CacheInterface {
    /** @var BaseCache[] */
    private $caches = [];
    private $disable = false;
    private $disable_keys = '';
    private $empty;

    /**
     * @param BaseCache[] $caches
     * @throws \Exception
     */
    public function __construct($caches=[]) {
        foreach ($caches as $cache) {
            if (!($cache instanceof BaseCache)) {
                throw new \Exception('CacheStack must reference a BaseCache instance');
            }
        }
        $this->caches = $caches;

        //pre-compute the empty result so we can re-use the same object for misses
        $this->empty = new CacheItem();
    }

    /**
     * @return bool
     */
    public function is_enabled() {
        foreach ($this->caches as $cache) {
            if (!$cache->is_enabled()) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param bool $val
     */
    public function set_enable($val=true) {
        foreach ($this->caches as $cache) {
            $cache->set_enable($val);
        }
    }

    public function disable_cache($val='') {
        if ($val === false) {
            $this->enable_cache();
        } else {
            $this->disable = true;
            $this->disable_keys = $val;
        }
    }

    public function enable_cache() {
        $this->disable = false;
        $this->disable_keys;
    }

    /**
     * Conditionally disable cache reads for any matching keys
     *
     * @param string $key
     * @return bool
     */
    public function is_disabled($key) {
        if (!$this->disable) {
            return false;
        }
        if ($this->disable_keys) {
            return mb_stripos($key, $this->disable_keys) !== false;
        }
        return true;
    }

    /**
     * @param string[] $keys
     * @return string[] - returns array of keys that we can search cache for
     */
    public function active_keys($keys) {
        if ($this->disable && !$this->disable_keys) {
            return [];
        }

        if ($this->disable_keys) {
            $enabled = [];
            foreach ($keys as $key) {
                if (mb_stripos($key, $this->disable_keys) === false) {
                    $enabled[] = $key;
                }
            }
            $keys = $enabled;
        }

        return $keys;
    }

    /**
     * @inheritdoc
     */
    public function get($key) {
        if (!$this->is_disabled($key)) {
            foreach ($this->caches as $class => $cache) {
                $res = $cache->get($key);
                if ($res->hit()) {
                    return $res;
                }
            }
        }

        return $this->empty;
    }

    /**
     * @inheritdoc
     */
    public function get_multi($keys) {
        $res = [];
        foreach ($keys as $key) {
            $res[$key] = $this->empty;
        }
        $active = $this->active_keys($keys);

        foreach ($this->caches as $class => $cache) {
            $missing = [];
            $partial_res = $cache->get_multi($active);
            foreach ($partial_res as $key => $entry) {
                if ($entry->hit()) {
                    $res[$key] = $entry;
                } else {
                    $missing[] = $key;
                }
            }
            $active = $missing;
        }

        return $res;
    }

    /**
     * @inheritdoc
     */
    public function set($key_or_map, $val=null, $ttl=null) {
        $res = true;

        if (is_string($key_or_map)) {
            $key_or_map = [ $key_or_map => $val ];
        }

        /** @var BaseCache[] $reverse */
        $reverse = array_reverse($this->caches);
        foreach ($reverse as $class => $cache) {
            if (!$cache->set($key_or_map, $val, $ttl)) {
                $res = false;
            }
        }
        return $res;
    }

    /**
     * @inheritdoc
     */
    public function delete($key_or_keys) {
        $res = true;

        /** @var BaseCache[] $reverse */
        $reverse = array_reverse($this->caches);
        foreach ($reverse as $class => $cache) {
            if ($cache->delete($key_or_keys) === false) {
                $res = false;
            }
        }
        return $res;
    }

    /**
     * @inheritdoc
     */
    public function clear() {
        /** @var BaseCache[] $reverse */
        $reverse = array_reverse($this->caches);
        foreach ($reverse as $cache) {
            $cache->clear();
        }
    }

}
