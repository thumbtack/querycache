<?php

use QueryCache\LocalCache;

class BaseCacheTest extends PHPUnit_Framework_TestCase {
    /** @var  LocalCache */
    private $cache;

    protected function setUp() {
        //using LocalCache because it is the simplest complete implementation of BaseCache
        $this->cache = new LocalCache(true, ['option' => 20, 'empty' => null]);
    }

    public function test_default_enabled() {
        $this->assertTrue($this->cache->is_enabled());
    }

    public function test_options() {
        $cache = $this->cache;
        $this->assertSame(0, $cache->get_option('missing', 0));
        $this->assertSame(null, $cache->get_option('missing', null));
        $this->assertNotSame(null, $cache->get_option('missing', 0));
        $this->assertSame(false, $cache->get_option('missing', false));
        $this->assertSame(20, $cache->get_option('option', 0));
        $this->assertSame(null, $cache->get_option('empty', 50));

        $cache->set_option('new', 'test');
        $this->assertSame(20, $cache->get_option('option', 0));
        $this->assertSame(null, $cache->get_option('empty', 50));
        $this->assertSame('test', $cache->get_option('new', 'miss'));

        $cache->set_options(['extra' => 'data']);
        $this->assertSame(20, $cache->get_option('option', 0));
        $this->assertSame(null, $cache->get_option('empty', 50));
        $this->assertSame('test', $cache->get_option('new', 'miss'));
        $this->assertSame('data', $cache->get_option('extra', 'miss'));

        $cache->set_option('option', 0);
        $this->assertSame(0, $cache->get_option('option', 100));
    }

    public function test_disable() {
        $this->cache->set_enable(false);
        $this->assertFalse($this->cache->is_enabled());
    }
}
