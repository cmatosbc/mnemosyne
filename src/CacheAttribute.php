<?php

namespace Mnemosyne;

use Psr\SimpleCache\CacheInterface;

/**
 * Legacy class for backwards compatibility.
 *
 * @deprecated Use Cache attribute and CacheTrait instead
 * @see \Mnemosyne\Cache For the new attribute-based caching configuration
 * @see \Mnemosyne\CacheTrait For the new trait-based implementation
 *
 * @internal This class will be removed in version 2.0
 */
class CacheAttribute
{
    /** @var array<string, string> Cache of compiled key templates */
    private array $keyTemplates = [];

    /** @var string Prefix for tag keys in cache */
    private const TAG_PREFIX = 'tag:';

    /**
     * @param CacheInterface $cache PSR-16 cache implementation
     */
    public function __construct(
        private CacheInterface $cache
    ) {
    }

    /**
     * Store tag references for a cache key
     *
     * @param string $key The cache key to tag
     * @param array $tags List of tags to associate with the key
     */
    private function storeTags(string $key, array $tags): void
    {
        foreach ($tags as $tag) {
            $tagKey = self::TAG_PREFIX . $tag;
            $taggedKeys = $this->cache->get($tagKey, []);
            if (!in_array($key, $taggedKeys)) {
                $taggedKeys[] = $key;
                $this->cache->set($tagKey, $taggedKeys);
            }
        }
    }

    /**
     * Invalidate all cache entries with the given tag
     *
     * @param string $tag The tag to invalidate
     */
    public function invalidateTag(string $tag): void
    {
        $tagKey = self::TAG_PREFIX . $tag;
        $taggedKeys = $this->cache->get($tagKey, []);

        foreach ($taggedKeys as $key) {
            $this->cache->delete($key);
        }

        $this->cache->delete($tagKey);
    }

    public function __call(string $name, array $arguments)
    {
        try {
            $reflection = new \ReflectionMethod($this, $name);
        } catch (\ReflectionException $e) {
            throw new \BadMethodCallException("Method $name does not exist");
        }

        $reflection->setAccessible(true);
        $attributes = $reflection->getAttributes(Cache::class);

        if (empty($attributes)) {
            return $reflection->invokeArgs($this, $arguments);
        }

        /** @var Cache $config */
        $config = $attributes[0]->newInstance();

        // Get or generate cache key
        $key = $this->resolveCacheKey($config->key, $reflection, $arguments);

        // Check if this method invalidates other cache entries
        if (!empty($config->invalidates)) {
            foreach ($config->invalidates as $invalidateKey) {
                $resolvedKey = $this->resolveCacheKey($invalidateKey, $reflection, $arguments);
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

        // Execute the method
        $result = $reflection->invokeArgs($this, $arguments);

        // Store the result in cache if we have a key
        if ($key !== null) {
            $this->cache->set($key, $result, $config->ttl);

            // Store tag references if any tags are defined
            if (!empty($config->tags)) {
                $this->storeTags($key, $config->tags);
            }
        }

        return $result;
    }

    /**
     * Resolve a cache key from a template or method signature
     *
     * @param string|null $template The cache key template
     * @param \ReflectionMethod $method The method being called
     * @param array $args The method arguments
     *
     * @return string|null The resolved cache key
     */
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

    /**
     * Manually invalidate a cache key
     *
     * @param string $key The cache key to invalidate
     */
    protected function invalidateCache(string $key): void
    {
        $this->cache->delete($key);
    }

    /**
     * Manually invalidate multiple cache keys
     *
     * @param array $keys The cache keys to invalidate
     */
    protected function invalidateCacheKeys(array $keys): void
    {
        foreach ($keys as $key) {
            $this->cache->delete($key);
        }
    }
}
