<?php

use QueryCache\LocalCache;
use QueryCache\CacheLog;

class LoggedLocalCacheTest extends CacheInterfaceTest {
    private $cache;

    protected function setUp() {
        $cache = new LocalCache();
        $logger = $this->getMockBuilder('Logger')->setMethods(['debug'])->getMock();
        $this->cache = new CacheLog($cache, $logger);
        $this->cache->clear();
    }

    protected function tearDown() {
        parent::tearDown ();
        $this->get_cache()->clear();
    }

    protected function get_cache() {
        return $this->cache;
    }

    public function test_log() {
        $buffer = CacheLog::GetActivityBuffer();
        $this->assertNotEmpty($buffer);
        $this->assertArrayHasKey('LocalCache', $buffer);

        CacheLog::ResetActivityBuffer();
        $buffer = CacheLog::GetActivityBuffer();
        $this->assertNotEmpty($buffer);
        $this->assertArrayHasKey('LocalCache', $buffer);
        $this->assertEmpty($buffer['LocalCache']['activity']);
    }
}
