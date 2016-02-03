<?php

use QueryCache\CacheStack;
use QueryCache\LocalCache;
use QueryCache\Memcache;

class RequestMemcacheStackTest extends CacheInterfaceTest {
    private $cache;

    protected function setUp() {
        $local = new LocalCache();
        $local->clear();
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
            'ttl' => 1,
        ];
        $memcache = new Memcache(true, $options);
        $this->cache = new CacheStack([$local, $memcache]);
    }

    protected function get_cache() {
        return $this->cache;
    }

    public function test_default_enabled() {
        $this->assertTrue($this->get_cache()->is_enabled());
    }

    /**
     * @expectedException \Exception
     */
    public function test_invalid_cache_stack() {
        $invalid = new StdClass();
        $cache = new CacheStack([$invalid]);
    }

    public function test_disable_key() {
        $cache = $this->get_cache();

        $map = [
            'disable_key_test' => 'with value',
            'disable_another_key' => [1,2,3],
            'partially_enabled' => true,
        ];
        $cache->set($map, null);

        $res = $cache->get_multi(array_keys($map));
        $this->assertEmpty(array_diff(array_keys($map), array_keys($res)));
        foreach ($res as $key => $item) {
            $this->assertTrue($item->hit());
            $this->assertEquals($map[$key], $item->get_data());
        }

        //partial matches
        $cache->disable_cache('disable_');
        $res = $cache->get_multi(array_keys($map));
        $this->assertEmpty(array_diff(array_keys($map), array_keys($res)));
        foreach ($res as $key => $item) {
            $should_hit = mb_stripos($key, 'disable_') === false;
            $expect = $should_hit ? $map[$key] : false;
            $this->assertEquals(!$should_hit, $cache->is_disabled($key));
            $this->assertEquals($should_hit, $item->hit());
            $this->assertEquals($expect, $item->get_data());
        }

        //no matches
        $cache->disable_cache();
        $res = $cache->get_multi(array_keys($map));
        $this->assertEmpty(array_diff(array_keys($map), array_keys($res)));
        foreach ($res as $key => $item) {
            $this->assertTrue($cache->is_disabled($key));
            $this->assertFalse($item->hit());
            $this->assertFalse($item->get_data());
        }


        $cache->enable_cache();
        $res = $cache->get_multi(array_keys($map));
        $this->assertEmpty(array_diff(array_keys($map), array_keys($res)));
        foreach ($res as $key => $item) {
            $this->assertFalse($cache->is_disabled($key));
            $this->assertTrue($item->hit());
            $this->assertEquals($map[$key], $item->get_data());
        }

        $cache->disable_cache(false); //should be the same as calling enable_cache
        $res = $cache->get_multi(array_keys($map));
        $this->assertEmpty(array_diff(array_keys($map), array_keys($res)));
        foreach ($res as $key => $item) {
            $this->assertFalse($cache->is_disabled($key));
            $this->assertTrue($item->hit());
            $this->assertEquals($map[$key], $item->get_data());
        }
    }
}
