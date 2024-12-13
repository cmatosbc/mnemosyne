<?php

namespace Mnemosyne\Tests;

use Mnemosyne\Tests\Fixtures\MockCache;
use Mnemosyne\Tests\Fixtures\TestService;
use PHPUnit\Framework\TestCase;

class CacheAttributeTest extends TestCase
{
    private MockCache $cache;
    private TestService $service;

    protected function setUp(): void
    {
        $this->cache = new MockCache();
        $this->service = new TestService($this->cache);
    }

    public function testSimpleMethodCaching(): void
    {
        echo "\nTest: Simple Method Caching\n";
        echo "-----------------------------\n";

        // First call should cache
        echo "First call (should miss cache and store result):\n";
        $result1 = $this->service->simpleMethod();
        echo "Result: $result1\n";
        echo "Cache operations: " . count($this->cache->operations) . "\n";

        // Second call should use cache
        echo "\nSecond call (should hit cache):\n";
        $result2 = $this->service->simpleMethod();
        echo "Result: $result2\n";
        echo "Counter value: " . $this->service->getCounter() . "\n";
        echo "Total cache operations: " . count($this->cache->operations) . "\n";

        $this->assertEquals(1, $result1);
        $this->assertEquals(1, $result2);
        $this->assertEquals(1, $this->service->getCounter());
    }

    public function testMethodWithParamsCache(): void
    {
        echo "\nTest: Method With Parameters\n";
        echo "---------------------------\n";

        echo "First call with ID 42:\n";
        $result1 = $this->service->methodWithParams(42);
        echo "Result: " . json_encode($result1) . "\n";
        
        echo "\nSecond call with same ID (should hit cache):\n";
        $result2 = $this->service->methodWithParams(42);
        echo "Result: " . json_encode($result2) . "\n";
        echo "Counter value: " . $this->service->getCounter() . "\n";

        echo "\nCall with different ID 43 (should miss cache):\n";
        $result3 = $this->service->methodWithParams(43);
        echo "Result: " . json_encode($result3) . "\n";
        echo "Counter value: " . $this->service->getCounter() . "\n";

        $this->assertEquals(['id' => 42, 'count' => 1], $result1);
        $this->assertEquals($result1, $result2);
        $this->assertEquals(['id' => 43, 'count' => 2], $result3);
    }

    public function testMethodWithMultipleParamsCache(): void
    {
        echo "\nTest: Method With Multiple Parameters\n";
        echo "-----------------------------------\n";

        echo "First call:\n";
        $result1 = $this->service->methodWithMultipleParams(1, 'active');
        echo "Result: " . json_encode($result1) . "\n";

        echo "\nSecond call with same params (should hit cache):\n";
        $result2 = $this->service->methodWithMultipleParams(1, 'active');
        echo "Result: " . json_encode($result2) . "\n";
        echo "Counter value: " . $this->service->getCounter() . "\n";

        echo "\nCall with different status (should miss cache):\n";
        $result3 = $this->service->methodWithMultipleParams(1, 'inactive');
        echo "Result: " . json_encode($result3) . "\n";
        echo "Counter value: " . $this->service->getCounter() . "\n";

        $this->assertEquals(['deptId' => 1, 'status' => 'active', 'count' => 1], $result1);
        $this->assertEquals($result1, $result2);
        $this->assertEquals(['deptId' => 1, 'status' => 'inactive', 'count' => 2], $result3);
    }

    public function testCacheInvalidation(): void
    {
        echo "\nTest: Cache Invalidation\n";
        echo "----------------------\n";

        echo "Caching initial data:\n";
        $result1 = $this->service->methodWithParams(42);
        echo "Result: " . json_encode($result1) . "\n";

        echo "\nInvalidating cache for ID 42...\n";
        $this->service->invalidatingMethod(42);
        
        echo "\nFetching data again (should recompute):\n";
        $result2 = $this->service->methodWithParams(42);
        echo "Result: " . json_encode($result2) . "\n";
        echo "Counter value: " . $this->service->getCounter() . "\n";

        $this->assertEquals(['id' => 42, 'count' => 1], $result1);
        $this->assertEquals(['id' => 42, 'count' => 3], $result2);
    }

    public function testComplexMethodWithMultipleInvalidations(): void
    {
        echo "\nTest: Complex Method With Multiple Invalidations\n";
        echo "------------------------------------------\n";

        echo "Caching initial data:\n";
        $user42 = $this->service->methodWithParams(42);
        echo "User 42: " . json_encode($user42) . "\n";
        $dept1Users = $this->service->methodWithMultipleParams(1, 'active');
        echo "Dept 1 Users: " . json_encode($dept1Users) . "\n";
        
        echo "\nCalling complex method (should invalidate both caches):\n";
        $complex = $this->service->complexMethod(42, 1);
        echo "Complex result: " . json_encode($complex) . "\n";
        
        echo "\nFetching data again (both should recompute):\n";
        $user42New = $this->service->methodWithParams(42);
        echo "User 42 (new): " . json_encode($user42New) . "\n";
        $dept1UsersNew = $this->service->methodWithMultipleParams(1, 'active');
        echo "Dept 1 Users (new): " . json_encode($dept1UsersNew) . "\n";
        echo "Counter value: " . $this->service->getCounter() . "\n";

        $this->assertNotEquals($user42['count'], $user42New['count']);
        $this->assertNotEquals($dept1Users['count'], $dept1UsersNew['count']);
    }

    public function testManualCacheInvalidation(): void
    {
        echo "\nTest: Manual Cache Invalidation\n";
        echo "----------------------------\n";

        echo "Caching initial data:\n";
        $result1 = $this->service->methodWithParams(42);
        echo "Result: " . json_encode($result1) . "\n";

        echo "\nManually invalidating cache...\n";
        $this->service->manualInvalidation(42);
        
        echo "\nFetching data again (should recompute):\n";
        $result2 = $this->service->methodWithParams(42);
        echo "Result: " . json_encode($result2) . "\n";
        echo "Counter value: " . $this->service->getCounter() . "\n";

        $this->assertEquals(['id' => 42, 'count' => 1], $result1);
        $this->assertEquals(['id' => 42, 'count' => 2], $result2);
    }

    public function testCacheOperations(): void
    {
        echo "\nTest: Cache Operations\n";
        echo "--------------------\n";

        echo "Performing cache operation:\n";
        $this->service->methodWithParams(42);
        
        $operations = $this->cache->operations;
        echo "\nCache operations log:\n";
        foreach ($operations as $i => $op) {
            echo sprintf(
                "%d. %s on key '%s'%s\n",
                $i + 1,
                strtoupper($op['operation']),
                $op['key'],
                isset($op['ttl']) ? " (TTL: {$op['ttl']}s)" : ''
            );
        }
        
        $this->assertEquals('get', $operations[0]['operation']);
        $this->assertEquals('user:42', $operations[0]['key']);
        $this->assertEquals('set', $operations[1]['operation']);
        $this->assertEquals('user:42', $operations[1]['key']);
        $this->assertEquals(3600, $operations[1]['ttl']);
    }

    public function testSerializationEnabled(): void
    {
        echo "\nTest: Method With Serialization\n";
        echo "----------------------------\n";

        // First call should cache the serialized object
        echo "First call (should miss cache and store serialized result):\n";
        $result1 = $this->service->methodWithSerialization(42);
        echo "Result: " . json_encode($result1) . "\n";

        // Verify the cached value is serialized
        $cachedValue = $this->cache->get('complex:42');
        $this->assertTrue(is_string($cachedValue));
        $this->assertStringStartsWith('O:8:"stdClass":', $cachedValue);

        // Second call should unserialize from cache
        echo "\nSecond call (should hit cache and unserialize):\n";
        $result2 = $this->service->methodWithSerialization(42);
        echo "Result: " . json_encode($result2) . "\n";

        // Verify both results are identical objects
        $this->assertEquals($result1->id, $result2->id);
        $this->assertEquals($result1->count, $result2->count);
        $this->assertEquals($result1->timestamp, $result2->timestamp);
        $this->assertEquals(1, $this->service->getCounter());
    }

    public function testSerializationDisabled(): void
    {
        echo "\nTest: Method Without Serialization\n";
        echo "--------------------------------\n";

        // First call should cache the raw array
        echo "First call (should miss cache and store raw result):\n";
        $result1 = $this->service->methodWithoutSerialization(42);
        echo "Result: " . json_encode($result1) . "\n";

        // Verify the cached value is not serialized
        $cachedValue = $this->cache->get('noserialize:42');
        $this->assertIsArray($cachedValue);
        $this->assertEquals($result1, $cachedValue);

        // Second call should return the raw cached array
        echo "\nSecond call (should hit cache):\n";
        $result2 = $this->service->methodWithoutSerialization(42);
        echo "Result: " . json_encode($result2) . "\n";

        $this->assertEquals($result1, $result2);
        $this->assertEquals(1, $this->service->getCounter());
    }
}
