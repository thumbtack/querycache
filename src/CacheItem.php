<?php

namespace QueryCache;

class CacheItem {

    /** @var  mixed $data */
    private $data;

    /** @var  bool $cache_key */
    private $from_cache;

    /**
     * CacheItem constructor.
     * @param mixed $data
     * @param bool $from_cache
     */
    public function __construct($data=false, $from_cache=false) {
        $this->set_data($data);
        $this->set_from_cache($from_cache);
    }

    /**
     * @return mixed
     */
    public function get_data() {
        return $this->data;
    }

    /**
     * @param mixed $data
     * @return $this
     */
    public function set_data($data) {
        $this->data = $data;
        return $this;
    }

    /**
     * @return bool
     */
    public function hit() {
        return $this->from_cache;
    }

    /**
     * @return bool
     */
    public function miss() {
        return !$this->hit();
    }

    /**
     * @param bool $from_cache
     * @return $this
     */
    public function set_from_cache($from_cache) {
        $this->from_cache = !!$from_cache;
        return $this;
    }
}





