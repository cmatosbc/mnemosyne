<?php

namespace Mnemosyne;

use Psr\SimpleCache\CacheInterface;

trait CacheTrait
{
    private array $keyTemplates = [];
    private CacheInterface $cache;

    private function cacheCall(string $method, array $args)
    {
        // Get the public method that called us
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $callingMethod = $trace[1]['function'];

        // Get cache configuration from the public method
        $reflection = new \ReflectionMethod($this, $callingMethod);
        $attributes = $reflection->getAttributes(Cache::class);

        if (empty($attributes)) {
            $reflection = new \ReflectionMethod($this, $method);
            return $reflection->invokeArgs($this, $args);
        }

        /** @var Cache $config */
        $config = $attributes[0]->newInstance();

        // Get or generate cache key
        $key = $this->resolveCacheKey($config->key, $reflection, $args);

        // Check if this method invalidates other cache entries
        if (!empty($config->invalidates)) {
            foreach ($config->invalidates as $invalidateKey) {
                $resolvedKey = $this->resolveCacheKey($invalidateKey, $reflection, $args);
                $this->cache->delete($resolvedKey);
            }
        }

        // Try to get from cache if not an invalidation-only call
        if ($key !== null) {
            $result = $this->cache->get($key);
            if ($result !== null) {
                return $result;
            }
        }

        // Execute the original method
        $reflection = new \ReflectionMethod($this, $method);
        $result = $reflection->invokeArgs($this, $args);

        // Cache the result if we have a key
        if ($key !== null) {
            $this->cache->set($key, $result, $config->ttl);
        }

        return $result;
    }

    private function resolveCacheKey(?string $template, \ReflectionMethod $method, array $args): ?string
    {
        if ($template === null) {
            return hash(
                'xxh3',
                $method->getDeclaringClass()->getName() . '::' . $method->getName() . '::' . serialize($args)
            );
        }

        // Parse parameter names if not already cached
        if (!isset($this->keyTemplates[$template])) {
            preg_match_all('/{([^}]+)}/', $template, $matches);
            $this->keyTemplates[$template] = $matches[1];
        }

        $params = $method->getParameters();
        $key = $template;

        foreach ($this->keyTemplates[$template] as $param) {
            $position = array_search($param, array_map(fn($p) => $p->getName(), $params));
            if ($position !== false && isset($args[$position])) {
                $key = str_replace('{' . $param . '}', (string)$args[$position], $key);
            }
        }

        return $key;
    }

    protected function invalidateCache(string $key): void
    {
        $this->cache->delete($key);
    }

    protected function invalidateCacheKeys(array $keys): void
    {
        foreach ($keys as $key) {
            $this->cache->delete($key);
        }
    }
}
