<?php

namespace QueryCache;

abstract class BaseCache implements CacheInterface {
    const DEFAULT_TTL = 86400;

    private $enabled;
    private $options;
    private $empty;

    /**
     * BaseCache constructor.
     * @param bool $enabled
     * @param array $options
     */
    public function __construct($enabled=true, $options=[]) {
        $this->set_enable($enabled);
        $this->options = $options;
        $this->empty = new CacheItem();
    }

    /**
     * @param bool $val
     */
    public function set_enable($val=true) {
        $this->enabled = !!$val;
    }

    /**
     * @return bool
     */
    public function is_enabled() {
        return $this->enabled;
    }

    /**
     * @param string $option
     * @param mixed $default
     * @return mixed
     */
    public function get_option($option, $default=null) {
        if (array_key_exists($option, $this->options)) {
            return $this->options[$option];
        }
        return $default;
    }

    /**
     * @param array $options
     */
    public function set_options($options) {
        $this->options = array_replace($this->options, $options);
    }

    /**
     * @param array $options
     */
    public function set_default_options($options) {
        $this->options = array_replace($options, $this->options);
    }

    /**
     * @param string $option
     * @param mixed $value
     */
    public function set_option($option, $value) {
        $this->set_options([$option => $value]);
    }

    protected function get_ttl($ttl) {
        return $ttl === null ? $this->get_option('ttl', self::DEFAULT_TTL) : $ttl;
    }

    public function set_default_ttl($ttl) {
        $this->set_option('ttl', $ttl);
    }

    /**
     * @param mixed $data
     * @param bool $found
     * @return CacheItem
     */
    protected function result($found, $data=false) {
        if ($found === false) {
            return $this->empty;
        }
        return new CacheItem($data, $found);
    }

    /**
     * @inheritdoc
     */
    abstract public function get($key);

    /**
     * @inheritdoc
     */
    abstract public function get_multi($keys);

    /**
     * @inheritdoc
     */
    abstract public function set($key_or_map, $val=null, $ttl=null);

    /**
     * @inheritdoc
     */
    abstract public function delete($key_or_keys);

    /**
     * @inheritdoc
     */
    abstract public function clear();
}
