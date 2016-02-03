<?php

namespace QueryCache;

use \Exception;
use \PDO;

class Query {
    private $write_db;
    private $read_db;
    private $cache;

    /**
     * Query constructor.
     * @param PDO $read_db
     * @param PDO|CacheInterface|null $write_db
     * @param CacheInterface|null $cache
     * @throws Exception
     */
    public function __construct(PDO $read_db, $write_db=null, $cache=null) {
        $this->set_read_db($read_db);

        if ($write_db instanceof PDO) {
            $this->set_write_db($write_db);
        } else if ($write_db instanceof CacheInterface) {
            $this->set_write_db($read_db);
            if ($cache === null) {
                $cache = $write_db;
            }
        } else if ($write_db === null) {
            $this->set_write_db($read_db);
        } else {
            throw new Exception('Invalid type for: write_db');
        }

        if ($cache instanceof CacheInterface) {
            $this->set_cache($cache);
        } else if ($cache === null) {
            $cache = new BlackHole();
            $this->set_cache($cache);
        } else {
            throw new Exception('Invalid type for: cache');
        }
    }

    /**
     * @param PDO $read_db
     * @return $this
     */
    public function set_read_db(PDO $read_db) {
        $this->read_db = $read_db;
        return $this;
    }

    /**
     * @param PDO $write_db
     * @return $this
     */
    public function set_write_db(PDO $write_db) {
        $this->write_db = $write_db;
        return $this;
    }

    /**
     * @param CacheInterface $cache
     * @return $this
     */
    public function set_cache(CacheInterface $cache) {
        $this->cache = $cache;
        return $this;
    }

    /**
     * @return PDO
     */
    protected function get_read_db() {
        return $this->read_db;
    }

    /**
     * @return PDO
     */
    protected function get_write_db() {
        return $this->write_db;
    }

    /**
     * @return CacheInterface
     */
    protected function get_cache() {
        return $this->cache;
    }

    /**
     * Sorts keys from longest to shortest -- to make sure we substitute in the
     * correct order
     * Ex: /:id/:id2 would replace both ':id' strings if attempting to replace
     *     the :id token first. With the sort, we would replace the :id2 token
     *     first, and then the :id token.
     *
     * @param string[] $ary
     * @return string[]
     */
    protected function length_sort($ary) {
        usort($ary, function($a, $b) {
            return strlen($b) - strlen($a);
        });
        return $ary;
    }


    protected function cache_key_from_row($key_format, $row) {
        $key = $key_format;
        $found = preg_match_all('/:(\w+)/', $key_format, $tokens);

        $tokens = $found > 0 ? array_unique($tokens[1]) : [];
        if ($found !== count($tokens)) {
            throw new QueryException('Duplicated cache key token.');
        }
        $tokens = $this->length_sort($tokens);

        foreach ($tokens as $token) {
            if (!isset($row[$token])) {
                throw new QueryException('Column token: "' . $token . '" not found.');
            }
            $key = str_replace(':' . $token, $row[$token], $key);
        }

        return $key;
    }

    /**
     * @param string $key_format - ':' delimited token string
     * @param array $params_map - same as param map used for queries
     * @return array - map of cache keys and parameters used to build key
     * @throws QueryException
     */
    protected function cache_key_params_map($key_format, $params_map) {
        $found = preg_match_all('/(:\w+)/', $key_format, $tokens);

        $temp_keys = [$key_format => []];
        if ($found < 1) {
            return $temp_keys;
        }

        $tokens = array_unique($tokens[1]);
        if ($found !== count($tokens)) {
            throw new QueryException('Duplicated cache key token.');
        }
        $tokens = $this->length_sort($tokens);

        $keys = [];
        foreach ($tokens as $token) {
            if (!isset($params_map[$token])) {
                throw new QueryException('Token: "' . $token . '" not found.');
            }

            $keys = [];
            $params = $params_map[$token];
            if (is_array($params)) {
                foreach ($temp_keys as $temp_key => $map) {
                    foreach ($params as $param) {
                        $key = str_replace($token, $param, $temp_key);
                        $keys[$key] = array_merge($map, [$token => $param]);
                    }
                }
            } else {
                foreach ($temp_keys as $temp_key => $map) {
                    $key = str_replace($token, $params, $temp_key);
                    $keys[$key] = $map;
                }
            }

            $temp_keys = $keys;
        }

        return $keys;
    }

    protected function read_cached_result_set($key_format, array &$params_map) {
        $key_map = $this->cache_key_params_map($key_format, $params_map);
        if (count($key_map) > 1) {
            throw new QueryException('Result set key evaluates to more than one key');
        }

        $key = current(array_keys($key_map));
        $item = $this->get_cache()->get($key);
        if ($item->hit()) {
            $params_map = false;
            return $item->get_data();
        }

        return [];
    }

    protected function write_cached_result_set($key_format, array $result_set, $ttl) {
        //build cache key from first row
        $row = current($result_set);
        $key = $this->cache_key_from_row($key_format, $row);
        $this->get_cache()->set($key, $result_set, $ttl);
    }

    protected function read_cached_rows($key_format, array &$params_map) {
        $key_map = $this->cache_key_params_map($key_format, $params_map);
        $rows = $this->get_cache()->get_multi(array_keys($key_map));

        $replace = $params_map;
        //use the first key_map entry to initialize the replace map
        foreach (current($key_map) as $key => $val) {
            $replace[$key] = [];
        }

        $miss = 0;
        $resp = [];
        foreach ($rows as $key => $row) {
            if ($row->hit()) {
                $resp[] = $row->get_data();
            } else {
                $miss++;
                foreach ($key_map[$key] as $param => $val) {
                    $replace[$param][] = $val;
                }
            }
        }

        if ($miss === 0) {
            $params_map = false;
        } else {
            $params_map = array_replace($params_map, $replace);
        }

        return $resp;
    }

    protected function write_cached_rows($key_format, array $rows, $ttl) {
        $cache_map = [];
        foreach ($rows as $row) {
            $key = $this->cache_key_from_row($key_format, $row);
            $cache_map[$key] = $row;
        }

        $this->get_cache()->set($cache_map, null, $ttl);
    }

    /**
     * Takes an array and an sql-like ORDER BY clause and performs
     * the sort on associative arrays or object properties
     *
     * @param mixed[] $ary - array of arrays or array of objects to sort
     * @param string|string[] $order_by - sql-like ORDER BY clause
     * @return mixed[]
     */
    protected function sort($ary, $order_by) {
        $dirMap = [ 'DESC' => 1, 'ASC' => -1 ];
        $default = -1; //ASC
        if (is_string($order_by)) {
            $order_by = explode(',', $order_by);
        }

        $keys = [];
        foreach ($order_by as $expr) {
            $expr = trim($expr);
            if ($expr === '') {
                continue;
            }

            $parts = explode(' ', $expr);
            $key = trim($parts[0]);
            if (isset($parts[1])) {
                $dir = strtoupper(trim($parts[1]));
                $keys[$key] = $dirMap[$dir] ? $dirMap[$dir] : $default;
            } else {
                $keys[$key] = $default;
            }
        }

        if (count($keys) <= 0) {
            return $ary;
        }

        usort($ary, function($a, $b) use($keys) {
            foreach ($keys as $key => $dir) {
                if ($a[$key] !== $b[$key]) {
                    return $a[$key] < $b[$key] ? $dir : -1 * $dir;
                }
            }
            return 0;
        });

        return $ary;
    }

    protected function format_results($map_format, $sort, $rows) {
        if ($sort) {
            $rows = $this->sort($rows, $sort);
        }
        if (empty($map_format)) {
            return $rows;
        }

        $row_map = [];
        $reverse = array_reverse($map_format);
        foreach ($rows as $row) {
            $entry = $row;
            foreach ($reverse as $column_key) {
                $entry = [ $row[$column_key] => $entry ];
            }
            $row_map = array_replace_recursive($row_map, $entry);
        }

        return $row_map;
    }

    /**
     * @param string $sql
     * @param array $params_map
     * @return string[]
     *
     * @throws QueryException
     */
    protected function validate_query($sql, $params_map) {
        //Validate parameter counts and check for parameter dupes:
        $found = preg_match_all('/(:\w+)/', $sql, $tokens);
        if (count($params_map) !== $found) {
            throw new QueryException('SQL placeholders count does not match parameter count.');
        }
        $tokens = array_unique($tokens[1]);
        if ($found !== count($tokens)) {
            throw new QueryException('Duplicated query token.');
        }

        $leftovers = array_diff(array_keys($params_map), $tokens);
        if (count($leftovers)) {
            $extra = array_diff($tokens, array_keys($params_map));
            $leftovers = array_merge($leftovers, $extra);
            $errstr = 'Mishandled query tokens: ' . implode(', ', $leftovers);
            throw new QueryException($errstr);
        }
        return $tokens;
    }

    /**
     * @param string[] $tokens
     * @param array $params_map
     * @return array
     *
     * @throws QueryException
     */
    protected function flatten_params_map($tokens, $params_map) {
        // Generate flat_params using the order tokens are defined in the query
        $flat_params = [];
        foreach ($tokens as $token) {
            if (!isset($params_map[$token])) {
                throw new QueryException('Query token: "' . $token . '" not found');
            }
            $token_params = $params_map[$token];
            if (is_array($token_params)) {
                $flat_params = array_merge($flat_params, $token_params);
            } else {
                $flat_params[] = $token_params;
            }
        }
        return $flat_params;
    }


    /**
     * @param array $params_map
     * @param string $sql
     * @return string
     */
    protected function parameterize_sql($params_map, $sql) {
        $tokens = array_keys($params_map);
        $tokens = $this->length_sort($tokens);

        // Swap :tokens for '?' tokens. Use '?' because it is easier to support
        // variable counts of params (arrays)
        foreach ($tokens as $token) {
            $token_params = $params_map[$token];
            $expanded = '?';
            if (is_array($token_params)) {
                $expanded = implode(', ', array_fill(0, count($token_params), '?'));
            }
            $sql = str_replace($token, $expanded, $sql);
        }

        return $sql;
    }

    /**
     * Gets result sets for prepared SELECT statements.
     * Expands query tokens when attempting to bind an array.
     * Handles result set and row level caching based on template strings. The
     * cache string templates use ':' as a token delimiter.
     * Can build a map of maps for the return format
     *
     * @param string $sql - an sql SELECT query
     * @param array $params_map - params to bind to $sql query
     * @param array $options
     *      row_cache is a template string for row level cache keys
     *      result_set_cache is a template string for caching result sets
     *      ttl is the cache ttl to set when writing to cache
     *      map is an array of columns we want to build a map of
     * @return array
     * @throws QueryException
     */
    public function read($sql, $params_map=[], $options=[]) {
        $defaults = [
            'row_cache' => '',
            'result_set_cache' => '',
            'ttl' => null,
            'map' => [],
            'sort' => [],
        ];
        $options = array_replace($defaults, $options);

        //Extract options
        $row_cache = $options['row_cache'];
        $result_set_cache = $options['result_set_cache'];
        $ttl = $options['ttl'];
        $map = $options['map'];
        $sort = $options['sort'];

        $tokens = $this->validate_query($sql, $params_map);

        $result_set = [];
        //Attempt to get results from cache
        if ($result_set_cache) {
            $result_set = $this->read_cached_result_set($result_set_cache, $params_map);
        }
        if (empty($result_set) && $row_cache) {
            $result_set = $this->read_cached_rows($row_cache, $params_map);
        }

        //params_map is set to false to let us know nothing else can be done to get more data
        if ($params_map === false) {
            return $this->format_results($map, $sort, $result_set);
        }

        $flat_params = $this->flatten_params_map($tokens, $params_map);
        $sql = $this->parameterize_sql($params_map, $sql);

        $db = $this->get_read_db();
        $statement = $db->prepare($sql);
        $statement->execute($flat_params);
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        $rows = $statement->fetchAll();

        if ($rows !== false) {
            $result_set = array_merge($result_set, $rows);
            if ($result_set_cache) {
                $this->write_cached_result_set($result_set_cache, $rows, $ttl);
            }
            if ($row_cache) {
                $this->write_cached_rows($row_cache, $rows, $ttl);
            }
        }

        return $this->format_results($map, $sort, $result_set);
    }

    /**
     * Runs INSERT/UPDATE/DELETE statements against the master (read/write) database
     *
     * @param $sql
     * @param array $params_map
     * @param array $options
     * @return PDO
     *
     * @throws QueryException
     */
    public function write($sql, $params_map=[], $options=[]) {
        $db = $this->get_write_db();

        $defaults = [
            'row_cache' => '',
            'result_set_cache' => '',
        ];
        $options = array_replace($defaults, $options);
        //Extract options
        $row_cache = $options['row_cache'];
        $result_set_cache = $options['result_set_cache'];

        $tokens = $this->validate_query($sql, $params_map);
        $flat_params = $this->flatten_params_map($tokens, $params_map);
        $sql = $this->parameterize_sql($params_map, $sql);

        $statement = $db->prepare($sql);
        $statement->execute($flat_params);

        $keys = [];
        if ($result_set_cache) {
            $result_keys = $this->cache_key_params_map($result_set_cache, $params_map);
            $keys = array_merge($keys, array_keys($result_keys));
        }
        if ($row_cache) {
            $row_keys = $this->cache_key_params_map($row_cache, $params_map);
            $keys = array_merge($keys, array_keys($row_keys));
        }

        $this->get_cache()->delete($keys);

        return $db;
    }
}
