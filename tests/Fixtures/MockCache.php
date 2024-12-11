<?php

namespace Mnemosyne\Tests\Fixtures;

use Psr\SimpleCache\CacheInterface;

class MockCache implements CacheInterface
{
    private array $cache = [];
    public array $operations = [];

    public function get(string $key, mixed $default = null): mixed
    {
        $this->operations[] = ['operation' => 'get', 'key' => $key];
        return $this->cache[$key] ?? $default;
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        $this->operations[] = ['operation' => 'set', 'key' => $key, 'value' => $value, 'ttl' => $ttl];
        $this->cache[$key] = $value;
        return true;
    }

    public function delete(string $key): bool
    {
        $this->operations[] = ['operation' => 'delete', 'key' => $key];
        unset($this->cache[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->cache = [];
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    public function has(string $key): bool
    {
        return isset($this->cache[$key]);
    }
}
