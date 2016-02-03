<?php

use QueryCache\ApcuCache;

class ApcuCacheTest extends CacheInterfaceTest {
    private $cache;

    protected function setUp() {
        $this->cache = new ApcuCache(true, ['ttl' => 1]);
        $this->cache->clear();
    }

    protected function get_cache() {
        return $this->cache;
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

        $cache->clear();
        $res = $cache->get_multi(array_keys($map));
        foreach ($res as $item) {
            $this->assertTrue($item->miss());
        }
    }

    
}
