<?php

use QueryCache\Memcache;

class MemcacheTest extends CacheInterfaceTest {
    private $cache;

    protected function setUp() {
        $options = [
            'servers' => [
                [
                    'host' => 'localhost',
                    'port' => 11211,
                    'weight' => 1,
                ]
            ],
            'prefix' => 'tests/',
            'persist' => false,
        ];
        $this->cache = new Memcache(true, $options);
        $this->cache->set_default_ttl(1);
    }

    protected function get_cache() {
        return $this->cache;
    }

    public function test_persist_default() {
        $options = [
            'servers' => [
                [
                    'host' => 'localhost',
                    'port' => 11211,
                    'weight' => 1,
                ]
            ],
            'persist' => true
        ];
        $cache = new Memcache(true, $options);
        $this->assertTrue($cache->get_option('persist'));
        $this->assertTrue($cache->get('missing_key')->miss());
    }

    public function test_clear() {
        $cache = $this->get_cache();
        $map = [
            'example_data' => 1,
            'sample_key' => 'data',
        ];

        $ret = $cache->set($map, null);
        $this->assertTrue($ret);

        $res = $cache->get_multi(array_keys($map));
        foreach ($res as $item) {
            $this->assertTrue($item->hit());
        }

        // clear should be a no-op for memcached
        $cache->clear();
        $res = $cache->get_multi(array_keys($map));
        foreach ($res as $item) {
            $this->assertTrue($item->hit());
        }
    }
}
