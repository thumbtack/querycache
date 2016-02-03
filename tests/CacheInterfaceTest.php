<?php

use QueryCache\BaseCache;

abstract class CacheInterfaceTest extends PHPUnit_Framework_TestCase {

    /**
     * @return BaseCache
     */
    abstract protected function get_cache();

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
        $this->assertTrue($res->hit());
        $this->assertEquals($value, $res->get_data());
    }

    public function test_multi_set() {
        $cache = $this->get_cache();
        $map = [
            'multi_key' => 1,
            'multi_two' => 'data',
        ];

        $ret = $cache->set($map, null);
        $this->assertTrue($ret);

        $res = $cache->get('multi_key');
        $this->assertTrue($res->hit());
        $this->assertEquals(1, $res->get_data());

        $multi = $cache->get_multi(array_keys($map));
        $this->assertEmpty(array_diff(array_keys($map), array_keys($multi)));
        foreach ($multi as $key => $item) {
            $this->assertTrue($item->hit());
            $this->assertEquals($map[$key], $item->get_data());
        }
    }

    public function test_delete() {
        $cache = $this->get_cache();

        $map = [
            'delete_key' => 1,
            'delete_two' => 'data',
        ];
        $ret = $cache->set($map, null);
        $this->assertTrue($ret);

        $res = $cache->get('delete_key');
        $this->assertTrue($res->hit());
        $this->assertEquals(1, $res->get_data());

        $res = $cache->get('delete_two');
        $this->assertTrue($res->hit());
        $this->assertEquals('data', $res->get_data());

        $ret = $cache->delete('delete_key');
        $this->assertTrue($ret);

        $res = $cache->get('delete_key');
        $this->assertTrue($res->miss());
        $this->assertFalse($res->get_data());

        $res = $cache->get('delete_two');
        $this->assertTrue($res->hit());
        $this->assertEquals('data', $res->get_data());

        $ret = $cache->delete('delete_two');
        $this->assertTrue($ret);

        $res = $cache->get('delete_key');
        $this->assertTrue($res->miss());
        $this->assertFalse($res->get_data());

        $res = $cache->get('delete_two');
        $this->assertTrue($res->miss());
        $this->assertFalse($res->get_data());

        $ret = $cache->delete('missing');
        $this->assertTrue($ret);
    }

    public function test_disable() {
        $cache = $this->get_cache();

        $cache->set('exists', 100);
        $res = $cache->get('exists');
        $this->assertTrue($res->hit());
        $this->assertEquals(100, $res->get_data());

        $cache->set_enable(false);

        $this->assertFalse($cache->is_enabled());
        $res = $cache->get('missing');
        $this->assertTrue($res instanceof \QueryCache\CacheItem);
        $this->assertTrue($res->miss());
        $this->assertFalse($res->get_data());

        $res = $cache->get('exists');
        $this->assertTrue($res->miss());
        $this->assertFalse($res->get_data());

        $keys = ['cannot', 'get_keys'];
        $res = $cache->get_multi($keys);
        $this->assertEmpty(array_diff($keys, array_keys($res)));
        foreach ($res as $item) {
            $this->assertTrue($item->miss());
        }

        $ret = $cache->set('noop', 1);
        $this->assertFalse($ret);

        $ret = $cache->set(['map' => 'value', 'item' => 'noop'], null);
        $this->assertFalse($ret);

        $ret = $cache->delete(['noop', 'exists']);
        $this->assertFalse($ret);
        $cache->clear(); //this should do nothing and should be safe to call

        //re-enable and make sure things are in the same state before we disabled
        $cache->set_enable(true);

        //Make sure data set before we disabled is still there
        $res = $cache->get('exists');
        $this->assertTrue($res->hit());
        $this->assertEquals(100, $res->get_data());

        //Make sure data set after we disabled is not there
        $res = $cache->get('noop');
        $this->assertTrue($res->miss());
    }
}
