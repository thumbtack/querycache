<?php

namespace QueryCache;

class Memcache extends BaseCache {
    private $servers;
    private $prefix;
    private $memcache;

    public function __construct($enabled, array $options) {
        parent::__construct ($enabled, $options);
        $this->set_default_options(['persist' => true]);
        $this->servers = $this->get_option('servers');
        $this->prefix = $this->get_option('prefix', '');
    }

    /**
     * @return \Memcached
     */
    private function get_memcache() {
        if ($this->memcache === null) {
            $options = $this->get_option('options', []);
            $servers = $this->get_option('servers', []);
            if ($this->get_option('persist', false)) {
                $persistent_id = md5(json_encode(['o' => $options, 's' => $servers]));
                $this->memcache = new \Memcached($persistent_id);
            } else {
                $this->memcache = new \Memcached();
            }

            if (count($this->memcache->getServerList()) < 1) {
                if (count($options) > 0) {
                    $this->memcache->setOptions($options);
                }
                $this->memcache->addServers($servers);
            }
        }
        return $this->memcache;
    }

    protected function build_key($key) {
        return $this->prefix . $key;
    }

    /**
     * @inheritdoc
     */
    public function get($key) {
        if (!$this->is_enabled()) {
            return $this->result(false);
        }

        $data = $this->get_memcache()->get($this->build_key($key));
        $found = $data !== false;

        return $this->result($found, $data);
    }

    /**
     * @inheritdoc
     */
    public function get_multi($keys) {
        $res = [];
        $cache_keys = [];
        foreach ($keys as $key) {
            $cache_keys[$key] = $this->build_key($key);
        }

        $data = [];
        if ($this->is_enabled() && count($cache_keys) > 0) {
            $data = $this->get_memcache()->getMulti($cache_keys);
        }
        // This should only be hit if Memcached suddenly disappears:
        // @codeCoverageIgnoreStart
        if (!is_array($data)) {
            $data = [];
        }
        // @codeCoverageIgnoreEnd

        foreach ($keys as $key) {
            $cache_key = $cache_keys[$key];
            if (array_key_exists($cache_key, $data)) {
                $found = $data[$cache_key] !== false;
                $res[$key] = $this->result($found, $data[$cache_key]);
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
        $cache_key_map = [];
        foreach ($key_or_map as $key => $val) {
            $cache_key_map[$this->build_key($key)] = $val;
        }
        $ttl = $this->get_ttl($ttl);

        return $this->get_memcache()->setMulti($cache_key_map, $ttl);
    }

    /**
     * @inheritdoc
     */
    public function delete($key_or_keys) {
        if (!$this->is_enabled()) {
            return false;
        }

        $keys = is_string($key_or_keys) ? [$key_or_keys] : $key_or_keys;
        $cache_keys = [];
        foreach ($keys as $key) {
            $cache_keys[] = $this->build_key($key);
        }
        return $this->get_memcache()->deleteMulti($cache_keys) !== false;
    }

    /**
     * @inheritdoc
     */
    public function clear() {
        //clearing cache could impact all devs, so it is a no-op
    }
}
