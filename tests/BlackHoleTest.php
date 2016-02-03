<?php

use QueryCache\BlackHole;

class BlackHoleTest extends PHPUnit_Framework_TestCase {
    private $cache;

    protected function setUp() {
        $this->cache = new BlackHole(true, ['option' => 20, 'empty' => null]);
    }

    /**
     * @return BlackHole
     */
    protected function get_cache() {
        return $this->cache;
    }

    public function test_empty_get() {
        $res = $this->get_cache()->get('missing');
        $this->assertTrue($res instanceof \QueryCache\CacheItem);
        $this->assertTrue($res->miss());
        $this->assertFalse($res->get_data());
    }

    public function test_empty_get_multi() {
        $keys = ['missing', 'item'];
        $res = $this->get_cache()->get_multi($keys);
        $this->assertEmpty(array_diff($keys, array_keys($res)));
        foreach ($res as $item) {
            $this->assertTrue($item->miss());
            $this->assertFalse($item->get_data());
        }
    }

    public function test_single_set() {
        $cache = $this->get_cache();
        $key = 'single_key';
        $value = 'single_value';

        $ret = $cache->set($key, $value);
        $this->assertTrue($ret);

        $res = $cache->get($key);
        $this->assertTrue($res instanceof \QueryCache\CacheItem);
        $this->assertTrue($res->miss());
        $this->assertFalse($res->get_data());
    }

    public function test_multi_set() {
        $cache = $this->get_cache();
        $map = [
            'multi_key' => 1,
            'multi_two' => 'data',
        ];

        $ret = $cache->set($map);
        $this->assertTrue($ret);

        $res = $cache->get('multi_key');
        $this->assertTrue($res->miss());
        $this->assertFalse($res->get_data());

        $multi = $cache->get_multi(array_keys($map));
        $this->assertEmpty(array_diff(array_keys($map), array_keys($multi)));
        foreach ($multi as $key => $item) {
            $this->assertTrue($item->miss());
            $this->assertFalse($item->get_data());
        }
    }

    public function test_delete() {
        $cache = $this->get_cache();

        $map = [
            'delete_key' => 1,
            'delete_two' => 'data',
        ];
        $ret = $cache->set($map);
        $this->assertTrue($ret);

        $ret = $cache->delete(array_keys($map));
        $this->assertTrue($ret);
    }

    public function test_disable() {
        $this->get_cache()->set_enable(false);

        //disabled and enabled should be identical
        $this->test_empty_get();
        $this->test_empty_get_multi();
        $this->test_single_set();
        $this->test_multi_set();
        $this->test_delete();

        $this->get_cache()->set_enable(true);
        $this->test_empty_get();
        $this->test_empty_get_multi();
        $this->test_single_set();
        $this->test_multi_set();
        $this->test_delete();
    }

    public function test_clear() {
        // clearing should have no impact either
        $this->get_cache()->clear();
        $this->test_disable();
    }

}
