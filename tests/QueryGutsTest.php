<?php

require_once('QueryGuts.php');

use QueryCache\LocalCache;
use QueryCache\BlackHole;

// we don't have a full PDO object, so this will let us skip that stuff
// it cannot be mocked with phpunit because PDO objects do not serialize
class MockStatement extends \PDOStatement {
    private $response;

    public function __construct($response) {
        $this->response = $response;
    }

    public function execute($params=null) {
    }

    public function setFetchMode($mode, $params=null) {
    }

    public function fetchAll($how=null, $class_name=null, $ctor_args=null) {
        return $this->response;
    }
}

class MockPDO extends \PDO {
    private $sql;
    private $response;

    public function __construct() {}

    public function prepare($statement, $options=null) {
        $this->sql = $statement;
        return new MockStatement($this->get_response());
    }

    public function get_response() {
        return $this->response;
    }

    public function set_response($response) {
        $this->response = $response;
    }

    public function get_sql() {
        return $this->sql;
    }
}


class QueryGutsTest extends PHPUnit_Framework_TestCase {
    private $pdo;
    private $cache;
    private $query;

    protected function setUp() {
        $this->pdo = new \MockPDO();
        $this->cache = new LocalCache();
        $this->cache->clear();
        $this->query = new QueryGuts($this->pdo, $this->pdo, $this->cache);
    }

    protected function get_pdo() {
        return $this->pdo;
    }

    protected function get_cache() {
        return $this->cache;
    }

    protected function get_query() {
        return $this->query;
    }

    /**
     * @expectedException \Exception
     */
    public function test_invalid_read_db() {
        $obj = new StdClass();
        $query = new QueryGuts($obj);
    }

    /**
     * @expectedException \Exception
     */
    public function test_invalid_write_db() {
        $obj = new StdClass();
        $pdo = $this->get_pdo();
        $query = new QueryGuts($pdo, $obj);
    }

    /**
     * @expectedException \Exception
     */
    public function test_invalid_cache_obj() {
        $obj = new StdClass();
        $pdo = $this->get_pdo();
        $query = new QueryGuts($pdo, $pdo, $obj);
    }

    public function test_missing_write_db() {
        $pdo = $this->get_pdo();
        $cache = $this->get_cache();
        $query = new QueryGuts($pdo, $cache);

        $this->assertSame($pdo, $query->get_read());
        $this->assertSame($pdo, $query->get_write());
        $this->assertSame($cache, $query->cache());
    }

    public function test_no_cache() {
        $pdo = $this->get_pdo();
        $query = new QueryGuts($pdo);

        $this->assertSame($pdo, $query->get_read());
        $this->assertSame($pdo, $query->get_write());
        $this->assertTrue($query->cache() instanceof BlackHole);
    }

    public function test_sort_by_length() {
        $ary = [ 'bbbb', 'bbb', 'aa', 'aa', 'a', 'b' ];

        for ($i = 0; $i < 10; $i++) {
            shuffle($ary);
            $res = $this->get_query()->sort_by_length($ary);
            $max_len = PHP_INT_MAX;
            foreach ($ary as $entry) {
                $len = strlen($entry);
                $this->assertTrue($max_len >= $len);
            }
        }
    }

    protected function arrays_match($ary1, $ary2) {
        if (!is_array($ary1) || !is_array($ary2)) {
            return $ary1 === $ary2;
        }

        if (count($ary1) !== count($ary2)) {
            return false;
        }

        foreach (array_keys($ary1) as $index) {
            $row1 = $ary1[$index];
            $row2 = $ary2[$index];
            if (!$this->arrays_match($row1, $row2)) {
                return false;
            }
        }

        return true;
    }

    public function test_order_by() {
        $ary = [
            ['a' => 1, 'b' => 2, 'c' => 2],
            ['a' => 1, 'b' => 2, 'c' => 3],
            ['a' => 1, 'b' => 2, 'c' => 3],
            ['a' => 2, 'b' => 3, 'c' => 1],
            ['a' => 3, 'b' => 2, 'c' => 1],
        ];

        $query = $this->get_query();

        //Do we maintain the same order?
        $in_order = $query->order_by($ary, 'a ASC, b ASC, c ASC');
        $this->assertTrue($this->arrays_match($ary, $in_order));
        
        //Do we default to ASC when ordering is not defined
        $in_order = $query->order_by($ary, 'a, b, c');
        $this->assertTrue($this->arrays_match($ary, $in_order));

        //Is an array the same as a string
        $in_order = $query->order_by($ary, ['a', 'b', 'c']);
        $this->assertTrue($this->arrays_match($ary, $in_order));

        //Do we fully reverse the array
        $reversed = $query->order_by($ary, 'a DESC, b DESC, c DESC');
        $rev_ary = array_reverse($ary);

        $this->assertTrue($this->arrays_match($rev_ary, $reversed));

        //make sure this does nothing but return the empty array
        $this->assertEmpty($query->order_by([], 'a, b, c'));

        //make sure this does nothing but return the array in the exact same order
        $this->assertTrue($this->arrays_match($rev_ary, $query->order_by($rev_ary, '')));
        //make sure this does nothing but return the array in the exact same order
        $this->assertTrue($this->arrays_match($rev_ary, $query->order_by($rev_ary, [])));
        //make sure this does nothing but return the array in the exact same order
        $this->assertTrue($this->arrays_match($rev_ary, $query->order_by($rev_ary, [''])));

        //try varying ASC/DESC
        $res = $query->order_by($ary, 'a DESC, b DESC, c ASC');
        $expect = [
            ['a' => 3, 'b' => 2, 'c' => 1],
            ['a' => 2, 'b' => 3, 'c' => 1],
            ['a' => 1, 'b' => 2, 'c' => 2],
            ['a' => 1, 'b' => 2, 'c' => 3],
            ['a' => 1, 'b' => 2, 'c' => 3],
        ];
        $this->assertTrue($this->arrays_match($res, $expect));

        $res = $query->order_by($ary, 'c DESC, b ASC');
        $expect = [
            ['a' => 1, 'b' => 2, 'c' => 3],
            ['a' => 1, 'b' => 2, 'c' => 3],
            ['a' => 1, 'b' => 2, 'c' => 2],
            ['a' => 3, 'b' => 2, 'c' => 1],
            ['a' => 2, 'b' => 3, 'c' => 1],
        ];
        $this->assertTrue($this->arrays_match($res, $expect));
    }

    public function test_to_map() {
        $map_format = ['c', 'a'];
        $rows = [
            ['a' => 1, 'b' => 5, 'c' => 9],
            ['a' => 1, 'b' => 5, 'c' => 8],
            ['a' => 3, 'b' => 5, 'c' => 7],
            ['a' => 2, 'b' => 6, 'c' => 7],
        ];
        $res = $this->get_query()->to_map($map_format, $rows);
        $expect = [
            9 => [
                1 => ['a' => 1, 'b' => 5, 'c' => 9],
            ],
            8 => [
                1 => ['a' => 1, 'b' => 5, 'c' => 8],
            ],
            7 => [
                3 => ['a' => 3, 'b' => 5, 'c' => 7],
                2 => ['a' => 2, 'b' => 6, 'c' => 7],
            ],
        ];

        $this->assertTrue($this->arrays_match($expect, $res));
    }

    public function test_query_validation() {
        $query = $this->get_query();
        $sql = 'SELECT *
                FROM example
                WHERE id = :id AND id2 = :id2 OR name = :name';
        $params = [':id' => 1, ':id2' => 2, ':name' => 'name'];
        $expect = array_keys($params);
        $res = $query->validate($sql, $params);
        $this->assertTrue($this->arrays_match($expect, $res));

        $sql = 'SELECT * FROM example WHERE created > NOW()';
        $params = [];
        $expect = array_keys($params);
        $res = $query->validate($sql, $params);
        $this->assertTrue($this->arrays_match($expect, $res));
    }

    /**
     * @expectedException QueryCache\QueryException
     */
    public function test_placeholder_mismatch_query_param() {
        $query = $this->get_query();
        $sql = 'SELECT *
                FROM example
                WHERE id = :id AND id2 = :id2 OR id > :id2 AND id2 < :id1';
        $params = [':id' => 1, ':id2' => 2];
        $expect = array_keys($params);
        $res = $query->validate($sql, $params);
    }

    /**
     * @expectedException QueryCache\QueryException
     */
    public function test_missing_query_param() {
        $query = $this->get_query();
        $sql = 'SELECT *
                FROM example
                WHERE id = :id AND id2 = :id2 OR name = :name';
        $params = [':id' => 1, ':id2' => 2];
        $expect = array_keys($params);
        $res = $query->validate($sql, $params);
    }

    /**
     * @expectedException QueryCache\QueryException
     */
    public function test_misnamed_query_param() {
        $query = $this->get_query();
        $sql = 'SELECT *
                FROM example
                WHERE id = :id AND id2 = :id2 OR name = :name';
        $params = [':id' => 1, ':id2' => 2, ':nome' => 'wrong'];
        $expect = array_keys($params);
        $res = $query->validate($sql, $params);
    }

    /**
     * @expectedException QueryCache\QueryException
     */
    public function test_duplicate_query_param() {
        $query = $this->get_query();
        $sql = 'SELECT *
                FROM example
                WHERE id = :id AND id2 = :id2 OR id2 = :id2';
        $params = [':id' => 1, ':id2' => 2, ':name' => 'name'];
        $expect = array_keys($params);
        $res = $query->validate($sql, $params);
    }

    public function test_cache_key_from_array() {
        $query = $this->get_query();

        $format = '/test/:id2/:id/:id3';
        $row = [ 'id' => '1', 'id2' => 2, 'id3' => 3];
        $expect = '/test/2/1/3';
        $this->assertEquals($expect, $query->cache_key_from_array($format, $row));
    }

    /**
     * @expectedException QueryCache\QueryException
     */
    public function test_missing_token_cache_key_from_array() {
        $query = $this->get_query();

        $format = '/test/:id2/:id/:id3';
        $row = [ 'id' => '1', 'id2' => 2];
        $query->cache_key_from_array($format, $row);
    }

    /**
     * @expectedException QueryCache\QueryException
     */
    public function test_duplicate_token_cache_key_from_array() {
        $query = $this->get_query();

        $format = '/test/:id2/:id/:id';
        $row = [ 'id' => '1', 'id2' => 2];
        $query->cache_key_from_array($format, $row);
    }

    public function test_cache_key_from_params() {
        $query = $this->get_query();

        $format = '/test/:id2/:id/:id3';
        $params = [ ':id' => '1', ':id2' => 2, ':id3' => 3];
        $expect = [ '/test/2/1/3' => [] ];
        $res = $query->cache_keys_from_params($format, $params);
        $this->assertTrue($this->arrays_match($expect, $res));

        //Make sure combo logic works:
        $params = [ ':id' => '1', ':id2' => [2,3], ':id3' => [4,5]];
        $expect = [
            '/test/2/1/4' => [ ':id2' => 2, ':id3' => 4],
            '/test/2/1/5' => [ ':id2' => 2, ':id3' => 5],
            '/test/3/1/4' => [ ':id2' => 3, ':id3' => 4],
            '/test/3/1/5' => [ ':id2' => 3, ':id3' => 5],
        ];
        $res = $query->cache_keys_from_params($format, $params);
        $this->assertTrue($this->arrays_match($expect, $res));

        $format = '/test/no_tokens';
        $params = [ ':id' => '1', ':id2' => 2, ':id3' => 3];
        $expect = [ '/test/no_tokens' => [] ];
        $res = $query->cache_keys_from_params($format, $params);
        $this->assertTrue($this->arrays_match($expect, $res));
    }

    /**
     * @expectedException QueryCache\QueryException
     */
    public function test_missing_token_cache_key_from_params() {
        $query = $this->get_query();

        $format = '/test/:id2/:id/:id3';
        $row = [ ':id' => '1', ':id2' => 2];
        $query->cache_keys_from_params($format, $row);
    }

    /**
     * @expectedException QueryCache\QueryException
     */
    public function test_duplicate_token_cache_key_from_params() {
        $query = $this->get_query();

        $format = '/test/:id2/:id/:id';
        $row = [ ':id' => '1', ':id2' => 2];
        $query->cache_keys_from_params($format, $row);
    }

    public function test_flatten_params() {
        $query = $this->get_query();
        $sql = 'SELECT *
                FROM example
                WHERE id = IN(:id) AND id2 = IN(:id2) OR id3 = :id3';
        $params = [':id3' => 3, ':id' => 1, ':id2' => [2,4]];
        $tokens = $query->validate($sql, $params);
        $flat = $query->flatten_params($tokens, $params);
        $expect = [1, 2, 4, 3];
        $this->assertTrue($this->arrays_match($expect, $flat));

        $sql = 'SELECT * FROM example WHERE created > NOW()';
        $params = [];
        $tokens = $query->validate($sql, $params);
        $flat = $query->flatten_params($tokens, $params);
        $expect = [];
        $this->assertTrue($this->arrays_match($expect, $flat));
    }

    /**
     * @expectedException QueryCache\QueryException
     */
    public function test_flatten_params_mismatch() {
        $query = $this->get_query();
        $sql = 'SELECT * FROM example WHERE id = :fail';
        $params = [];
        $flat = $query->flatten_params([':fail'], $params);
        $expect = [];
        $this->assertTrue($this->arrays_match($expect, $flat));
    }

    public function test_to_sql() {
        $query = $this->get_query();
        $sql = 'SELECT *
                FROM example
                WHERE id = IN(:id) AND id2 = IN(:id2) OR id3 = :id3';
        $params = [':id3' => 3, ':id' => 1, ':id2' => [2,4]];
        $expect = 'SELECT *
                   FROM example
                   WHERE id = IN(?) AND id2 = IN(?, ?) OR id3 = ?';

        $res = $query->to_sql($params, $sql);
        $expect = preg_replace('/\s+/', ' ', $expect);
        $res = preg_replace('/\s+/', ' ', $res);

        $this->assertEquals($expect, $res);
    }

    protected function read($options, $cache_map) {
        $ary = [
            ['a' => 1, 'b' => 2, 'c' => 1],
            ['a' => 2, 'b' => 3, 'c' => 1],
            ['a' => 3, 'b' => 2, 'c' => 1],
        ];

        $query = $this->get_query();
        $this->get_pdo()->set_response($ary);

        $sql = 'SELECT * FROM example WHERE a IN (:a) AND c = :c';
        $expect_sql = 'SELECT * FROM example WHERE a IN (?, ?, ?) AND c = ?';
        $params = [':a' => [1,2,3], ':c' => 1];
        $expect = [
            3 => ['a' => 3, 'b' => 2, 'c' => 1],
            2 => ['a' => 2, 'b' => 3, 'c' => 1],
            1 => ['a' => 1, 'b' => 2, 'c' => 1],
        ];

        $res = $query->read($sql, $params, $options);
        $this->assertTrue($this->arrays_match($expect, $res));
        $this->assertEquals($this->get_pdo()->get_sql(), $expect_sql);

        $cached = $this->get_cache()->get_multi(array_keys($cache_map));
        $this->assertEquals(count($cached), count($cache_map));
        foreach ($cached as $key => $entry) {
            $this->assertTrue($entry->hit());
            $this->assertTrue($this->arrays_match($entry->get_data(), $cache_map[$key]));
        }

        //2nd time through should only read from cache:
        $res = $query->read($sql, $params, $options);
        $this->assertTrue($this->arrays_match($expect, $res));
    }

    /**
     * @expectedException QueryCache\QueryException
     */
    public function test_cache_result_set_read_exception() {
        $params = [':a' => [1,2]];
        $this->get_query()->cached_result_set('/exception/:a', $params);
    }

    public function test_cache_result_set_read_empty() {
        $params = [':a' => 1];
        $res = $this->get_query()->cached_result_set('/not_exists/:a', $params);

        $this->assertEmpty($res);
        $this->assertTrue($this->arrays_match($params, [':a' => 1]));
    }

    public function test_cache_result_set_read_hit() {
        $params = [':a' => 1];
        $data = ['col' => 'data'];
        $this->get_cache()->set('/exists/1', $data, 1);
        $res = $this->get_query()->cached_result_set('/exists/:a', $params);

        $this->assertTrue($this->arrays_match($data, $res));
        $this->assertFalse($params);
    }

    public function test_cache_empty_result_set_write() {
        $this->get_query()->write_results('/empty/result_set', [], 1);
    }

    public function test_cache_row_read() {
        $options = [
            'row_cache' => '/test/:a/:c',
            'map' => ['a'],
            'sort' => 'a DESC'
        ];
        $cache_map = [
            '/test/1/1' => ['a' => 1, 'b' => 2, 'c' => 1],
            '/test/2/1' => ['a' => 2, 'b' => 3, 'c' => 1],
            '/test/3/1' => ['a' => 3, 'b' => 2, 'c' => 1],
        ];
        $this->read($options, $cache_map);
    }

    public function test_cache_result_set_read() {
        $options = ['result_set_cache' => '/test/:c', 'map' => ['a'], 'sort' => 'a DESC'];
        $cache_map = [
            '/test/1' => [
                ['a' => 1, 'b' => 2, 'c' => 1],
                ['a' => 2, 'b' => 3, 'c' => 1],
                ['a' => 3, 'b' => 2, 'c' => 1],
            ]
        ];
        $this->read($options, $cache_map);
    }

    public function test_cache_row_write() {
        $query = $this->get_query();
        $sql = 'INSERT INTO example (a, b) VALUES (:a, :b)';
        $expect_sql = 'INSERT INTO example (a, b) VALUES (?, ?)';
        $params = [':a' => 1, ':b' => 1];
        $options = [
            'row_cache' => '/write_test/:a/:b',
            'result_set_cache' => '/write_test/:a'
        ];

        $data = ['col' => 'data'];
        $keys = ['/write_test/1/1', '/write_test/1'];
        $cache = $this->get_cache();
        foreach ($keys as $key) {
            $cache->set($key, $data, 1);
            $item = $cache->get($key);
            $this->assertTrue($item->hit());
        }

        $res = $query->write($sql, $params, $options);
        $this->assertEquals($this->get_pdo()->get_sql(), $expect_sql);
        $this->assertEquals($res->get_sql(), $expect_sql);

        $cached = $this->get_cache()->get_multi($keys);
        $this->assertEquals(count($cached), count($keys));
        foreach ($cached as $key => $entry) {
            $this->assertTrue($entry->miss());
        }
    }
}

