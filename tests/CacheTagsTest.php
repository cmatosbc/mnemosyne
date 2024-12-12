<?php

namespace Mnemosyne\Tests;

use Mnemosyne\Cache;
use Mnemosyne\CacheTrait;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

class CacheTagsTest extends TestCase
{
    private $mockCache;
    private $testService;

    protected function setUp(): void
    {
        $this->mockCache = $this->createMock(CacheInterface::class);
        $this->testService = new class($this->mockCache) {
            use CacheTrait;

            private int $counter = 0;

            public function __construct(CacheInterface $cache)
            {
                $this->cache = $cache;
            }

            #[Cache(key: 'user:1', ttl: 3600, tags: ['user', 'user-1'])]
            public function getUser1()
            {
                return $this->cacheCall('doGetUser1', []);
            }

            private function doGetUser1()
            {
                return ['id' => 1, 'name' => 'John', 'count' => ++$this->counter];
            }

            #[Cache(key: 'user:2', ttl: 3600, tags: ['user', 'user-2'])]
            public function getUser2()
            {
                return $this->cacheCall('doGetUser2', []);
            }

            private function doGetUser2()
            {
                return ['id' => 2, 'name' => 'Jane', 'count' => ++$this->counter];
            }

            public function getCounter(): int
            {
                return $this->counter;
            }
        };
    }

    public function testTagsAreStoredWhenCaching(): void
    {
        echo "\nTest: Cache Tags Storage\n";
        echo "--------------------\n";

        // Set up mock expectations for cache interactions
        $this->mockCache
            ->expects($this->exactly(3))
            ->method('get')
            ->willReturnMap([
                ['user:1', null],
                ['tag:user', []],
                ['tag:user-1', []]
            ]);

        // Track the set operations
        $setCalls = [];
        $this->mockCache
            ->expects($this->exactly(3))
            ->method('set')
            ->willReturnCallback(function($key, $value, $ttl = null) use (&$setCalls) {
                $setCalls[] = [$key, $value, $ttl];
                return true;
            });

        echo "First call (should store with tags):\n";
        $result = $this->testService->getUser1();
        echo "Result: " . json_encode($result) . "\n";
        echo "Counter value: " . $this->testService->getCounter() . "\n\n";

        echo "Cache operations performed:\n";
        foreach ($setCalls as [$key, $value, $ttl]) {
            if (str_starts_with($key, 'tag:')) {
                echo "- Tagged '$key' with keys: [" . implode(', ', $value) . "]\n";
            } else {
                echo "- Stored value in '$key' with TTL: " . ($ttl ?? 'null') . "s\n";
            }
        }

        // Verify the set operations happened in the correct order with correct values
        $expectedSets = [
            ['user:1', ['id' => 1, 'name' => 'John', 'count' => 1], 3600],
            ['tag:user', ['user:1'], null],
            ['tag:user-1', ['user:1'], null]
        ];

        $this->assertEquals($expectedSets, $setCalls, 'Cache sets should occur in the expected order with correct values');
    }

    public function testTagInvalidationDeletesAllTaggedItems(): void
    {
        echo "\nTest: Cache Tag Invalidation\n";
        echo "-------------------------\n";

        // Setup mock for getting tagged keys
        $this->mockCache
            ->expects($this->once())
            ->method('get')
            ->with('tag:user')
            ->willReturn(['user:1', 'user:2']);

        // Track deletion sequence
        $deleteSequence = [];
        $this->mockCache
            ->expects($this->exactly(3))
            ->method('delete')
            ->willReturnCallback(function($key) use (&$deleteSequence) {
                $deleteSequence[] = $key;
                return true;
            });

        echo "Invalidating 'user' tag...\n";
        $this->testService->invalidateTag('user');
        
        echo "\nCache items deleted:\n";
        foreach ($deleteSequence as $key) {
            if ($key === 'tag:user') {
                echo "- Removed tag registry '$key'\n";
            } else {
                echo "- Invalidated cache key '$key'\n";
            }
        }
        
        // Verify deletion sequence
        $this->assertEquals(
            ['user:1', 'user:2', 'tag:user'],
            $deleteSequence,
            'Cache items and tag should be deleted in the correct order'
        );
    }
}
