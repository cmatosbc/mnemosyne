<?php

namespace Mnemosyne\Tests\Fixtures;

use Mnemosyne\Cache;
use Mnemosyne\CacheTrait;
use Psr\SimpleCache\CacheInterface;

class TestService
{
    use CacheTrait;

    private int $counter = 0;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    #[Cache(ttl: 3600)]
    public function simpleMethod(): int
    {
        $result = $this->cacheCall('doSimpleMethod', func_get_args());
        return $result;
    }

    private function doSimpleMethod(): int
    {
        return ++$this->counter;
    }

    #[Cache(key: 'user:{id}', ttl: 3600)]
    public function methodWithParams(int $id): array
    {
        $result = $this->cacheCall('doMethodWithParams', func_get_args());
        return $result;
    }

    private function doMethodWithParams(int $id): array
    {
        return ['id' => $id, 'count' => ++$this->counter];
    }

    #[Cache(key: 'users:dept:{deptId}:status:{status}', ttl: 3600)]
    public function methodWithMultipleParams(int $deptId, string $status): array
    {
        $result = $this->cacheCall('doMethodWithMultipleParams', func_get_args());
        return $result;
    }

    private function doMethodWithMultipleParams(int $deptId, string $status): array
    {
        return ['deptId' => $deptId, 'status' => $status, 'count' => ++$this->counter];
    }

    #[Cache(invalidates: ['user:{id}'])]
    public function invalidatingMethod(int $id): void
    {
        $this->cacheCall('doInvalidatingMethod', func_get_args());
    }

    private function doInvalidatingMethod(int $id): void
    {
        ++$this->counter;
    }

    #[Cache(
        key: 'complex:{id}',
        ttl: 3600,
        invalidates: ['user:{id}', 'users:dept:{deptId}:status:active']
    )]
    public function complexMethod(int $id, int $deptId): array
    {
        $result = $this->cacheCall('doComplexMethod', func_get_args());
        return $result;
    }

    private function doComplexMethod(int $id, int $deptId): array
    {
        return ['id' => $id, 'deptId' => $deptId, 'count' => ++$this->counter];
    }

    #[Cache(key: 'complex:{id}', ttl: 3600, serialize: true)]
    public function methodWithSerialization(int $id): \stdClass
    {
        return $this->cacheCall('doMethodWithSerialization', func_get_args());
    }

    private function doMethodWithSerialization(int $id): \stdClass
    {
        $obj = new \stdClass();
        $obj->id = $id;
        $obj->count = ++$this->counter;
        $obj->timestamp = time();
        return $obj;
    }

    #[Cache(key: 'noserialize:{id}', ttl: 3600, serialize: false)]
    public function methodWithoutSerialization(int $id): array
    {
        return $this->cacheCall('doMethodWithoutSerialization', func_get_args());
    }

    private function doMethodWithoutSerialization(int $id): array
    {
        return ['id' => $id, 'count' => ++$this->counter];
    }

    public function manualInvalidation(int $id): void
    {
        $this->invalidateCache("user:$id");
    }

    public function getCounter(): int
    {
        return $this->counter;
    }

    // Public wrappers for testing
    public function callSimpleMethod(): int
    {
        return $this->simpleMethod();
    }

    public function callMethodWithParams(int $id): array
    {
        return $this->methodWithParams($id);
    }

    public function callMethodWithMultipleParams(int $deptId, string $status): array
    {
        return $this->methodWithMultipleParams($deptId, $status);
    }

    public function callInvalidatingMethod(int $id): void
    {
        $this->invalidatingMethod($id);
    }

    public function callComplexMethod(int $id, int $deptId): array
    {
        return $this->complexMethod($id, $deptId);
    }
}
