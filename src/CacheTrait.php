<?php

namespace Mnemosyne;

use Psr\SimpleCache\CacheInterface;

/**
 * Trait providing caching functionality for classes.
 *
 * This trait implements the caching behavior defined by the Cache attribute.
 * It handles cache key generation, storage, retrieval, and invalidation,
 * including support for cache tags.
 *
 * @see \Mnemosyne\Cache For the attribute that configures caching behavior
 * @see \Psr\SimpleCache\CacheInterface For the underlying cache implementation
 *
 * @example
 * ```php
 * class UserService
 * {
 *     use CacheTrait;
 *
 *     public function __construct(CacheInterface $cache)
 *     {
 *         $this->cache = $cache;
 *     }
 *
 *     #[Cache(key: 'user:{id}', ttl: 3600)]
 *     public function getUser(int $id): array
 *     {
 *         return $this->cacheCall('doGetUser', func_get_args());
 *     }
 *
 *     private function doGetUser(int $id): array
 *     {
 *         // Expensive operation here
 *         return ['id' => $id, 'name' => 'John'];
 *     }
 * }
 * ```
 *
 * @author Carlos Matos <carlosarturmatos1977@gmail.com>
 */
trait CacheTrait
{
    /** @var CacheInterface PSR-16 cache implementation */
    private CacheInterface $cache;

    /** @var array<string, string> Cache of compiled key templates */
    private array $keyTemplates = [];

    /**
     * Handle caching for a method call.
     *
     * This method is responsible for:
     * - Retrieving cache configuration from the calling method's attributes
     * - Generating cache keys based on templates and parameters
     * - Handling cache invalidation
     * - Storing and retrieving cached values
     * - Managing cache tags
     *
     * @param string $method Name of the private method to call if cache misses
     * @param array $args Arguments to pass to the method
     * @return mixed The cached or freshly computed result
     * @throws \ReflectionException If the method doesn't exist
     * @throws \BadMethodCallException If the method is not accessible
     */
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
                return $config->serialize ? unserialize($result) : $result;
            }
        }

        // Execute the original method
        $reflection = new \ReflectionMethod($this, $method);
        $result = $reflection->invokeArgs($this, $args);

        // Cache the result if we have a key
        if ($key !== null) {
            $valueToCache = $config->serialize ? serialize($result) : $result;
            $this->cache->set($key, $valueToCache, $config->ttl);

            // Store tags if any are defined
            if (!empty($config->tags)) {
                foreach ($config->tags as $tag) {
                    $resolvedTag = $this->resolveCacheKey($tag, $reflection, $args);
                    $tagKey = 'tag:' . $resolvedTag;
                    $taggedKeys = $this->cache->get($tagKey) ?? [];
                    if (!in_array($key, $taggedKeys)) {
                        $taggedKeys[] = $key;
                        $this->cache->set($tagKey, $taggedKeys);
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Resolve a cache key template using method parameters.
     *
     * Handles both automatic key generation (when template is null) and
     * parameter interpolation in key templates.
     *
     * @param string|null $template The key template with {param} placeholders
     * @param \ReflectionMethod $method The method being cached
     * @param array $args The arguments passed to the method
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
     * Invalidate a single cache key.
     *
     * @param string $key The cache key to invalidate
     */
    protected function invalidateCache(string $key): void
    {
        $this->cache->delete($key);
    }

    /**
     * Invalidate multiple cache keys.
     *
     * @param array $keys List of cache keys to invalidate
     */
    protected function invalidateCacheKeys(array $keys): void
    {
        foreach ($keys as $key) {
            $this->cache->delete($key);
        }
    }

    /**
     * Invalidate all cache entries associated with a tag.
     *
     * This will:
     * 1. Retrieve all cache keys associated with the tag
     * 2. Delete each cache entry
     * 3. Remove the tag registry itself
     *
     * @param string $tag The tag to invalidate
     */
    public function invalidateTag(string $tag): void
    {
        $tagKey = 'tag:' . $tag;
        $taggedKeys = $this->cache->get($tagKey) ?? [];

        foreach ($taggedKeys as $key) {
            $this->cache->delete($key);
        }

        $this->cache->delete($tagKey);
    }
}
