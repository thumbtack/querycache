<?php

use QueryCache\LocalCache;

class LocalCacheTest extends CacheInterfaceTest {
    private $cache;

    protected function setUp() {
        $this->cache = new LocalCache();
        $this->cache->clear();
    }

    protected function tearDown() {
        parent::tearDown ();
        $this->get_cache()->clear();
    }

    protected function get_cache() {
        return $this->cache;
    }

    public function test_shared_locals() {
        $cacheA = $this->get_cache();
        $cacheB = new LocalCache();

        $cacheA->set('shared_test', 'has a value');
        $cacheA->set('shared_test2', 'exists');

        $keys = ['shared_test', 'shared_test2', 'missing'];
        $resA = $cacheA->get_multi($keys);
        $resB = $cacheB->get_multi($keys);

        $this->assertEmpty(array_diff($keys, array_keys($resA)));
        $this->assertEmpty(array_diff($keys, array_keys($resB)));
        foreach ($resA as $key => $itemA) {
            $itemB = $resB[$key];
            $this->assertEquals($itemA->get_data(), $itemB->get_data());
        }

        $cacheB->delete('shared_test');
        $resA = $cacheA->get_multi($keys);
        $resB = $cacheB->get_multi($keys);

        $this->assertEmpty(array_diff($keys, array_keys($resA)));
        $this->assertEmpty(array_diff($keys, array_keys($resB)));
        foreach ($resA as $key => $itemA) {
            $itemB = $resB[$key];
            $this->assertEquals($itemA->get_data(), $itemB->get_data());
        }
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
