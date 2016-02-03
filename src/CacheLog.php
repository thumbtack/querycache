<?php

namespace QueryCache;

class CacheLog extends BaseCache {
    private static $activity_buffer = [];

    /** @var string  */
    private $logged_class;

    /** @var CacheInterface */
    private $instance;

    /** @var null or psr-3 logger */
    private $logger;

    /**
     * @return array
     */
    public static function GetActivityBuffer() {
        return self::$activity_buffer;
    }

    public static function ResetActivityBuffer() {
        $classes = array_keys(self::$activity_buffer);
        self::$activity_buffer = [];
        foreach ($classes as $class) {
            self::InitMonitors($class);
        }
    }

    /**
     * @param string $class
     */
    protected static function InitMonitors($class) {
        if (!isset(self::$activity_buffer[$class])) {
            self::$activity_buffer[$class]['calls'] = 0;
            self::$activity_buffer[$class]['runtime'] = 0.0;
            self::$activity_buffer[$class]['activity'] = [];
        }
    }

    /**
     * CacheLog constructor.
     * @param CacheInterface $ins
     * @param null $logger
     * @throws \Exception
     */
    public function __construct(CacheInterface $ins, $logger=null) {
        $class = str_replace(__NAMESPACE__ . '\\', '', get_class($ins));
        $this->instance = $ins;
        $this->logged_class = $class;
        self::InitMonitors($class);
        $this->logger = $logger;
    }

    public function set_enable($val=true) {
        parent::set_enable($val);
        $this->instance->set_enable($val);
    }

    /**
     * @param string $method
     * @param float $start -
     * @param string|string[] $key - key or array of keys we interacted with
     * @param int $hit - cache hits for key(s)
     * @param int $miss - cache misses for key(s)
     */
    protected function update_stats($method, $start, $key, $hit=0, $miss=0) {
        $runtime = microtime(true) - $start;
        if (is_string($key)) {
            $keys = [ $key ];
        } else {
            $keys = $key;
        }
        $class = $this->logged_class;
        $entry = [
            'runtime' => $runtime,
            'class' => $class,
            'method' => $method,
            'keys' => $keys,
            'hit' => $hit,
            'miss' => $miss,
        ];
        self::$activity_buffer[$class]['calls']++;
        self::$activity_buffer[$class]['runtime'] += $runtime;
        self::$activity_buffer[$class]['activity'][] = $entry;
        if ($this->logger !== null) {
            $this->logger->debug("$class::$method", $entry);
        }
    }

    /**
     * @inheritdoc
     */
    public function get($key) {
        $start = microtime(true);
        $res = $this->instance->get($key);
        $hit = $res->hit() ? 1 : 0;
        $miss = $hit ? 0 : 1;
        $this->update_stats(__FUNCTION__, $start, $key, $hit, $miss);
        return $res;
    }

    /**
     * @inheritdoc
     */
    public function get_multi($keys) {
        $start = microtime(true);
        $res = $this->instance->get_multi($keys);
        $hit = 0;
        $miss = 0;
        foreach ($res as $row) {
            if ($row->hit()) {
                $hit++;
            } else {
                $miss++;
            }
        }
        $this->update_stats(__FUNCTION__, $start, $keys, $hit, $miss);
        return $res;
    }

    /**
     * @inheritdoc
     */
    public function set($key_or_map, $val=null, $ttl=null) {
        $start = microtime(true);
        $res = $this->instance->set($key_or_map, $val, $ttl);
        $keys = is_string($key_or_map) ? [$key_or_map] : array_keys($key_or_map);
        $this->update_stats(__FUNCTION__, $start, $keys);
        return $res;
    }

    /**
     * @inheritdoc
     */
    public function delete($key_or_keys) {
        $start = microtime(true);
        $res = $this->instance->delete($key_or_keys);
        $this->update_stats(__FUNCTION__, $start, $key_or_keys);
        return $res;
    }

    /**
     * @inheritdoc
     */
    public function clear() {
        $start = microtime(true);
        $this->instance->clear();
        $this->update_stats(__FUNCTION__, $start, '');
    }
}
