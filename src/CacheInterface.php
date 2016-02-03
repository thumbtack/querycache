<?php
namespace QueryCache;

interface CacheInterface {
    /**
     * @param string $key
     * @return CacheItem
     */
    public function get($key);

    /**
     * @param string[] $keys
     * @return CacheItem[]
     */
    public function get_multi($keys);

    /**
     * @param string|string[] $key_or_map
     * @param mixed $val
     * @param null|int $ttl
     * @return bool - true if all sets were successful, false if any set was unsuccessful
     */
    public function set($key_or_map, $val=null, $ttl=null);

    /**
     * @param string|string[] $key_or_keys
     * @return bool - true if no errors, false if errors
     */
    public function delete($key_or_keys);

    /**
     * Clear the contents of a cache -- need to be careful
     * @return void
     */
    public function clear();
}
