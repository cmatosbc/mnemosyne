<?php

namespace Mnemosyne;

/**
 * Cache attribute for configuring method-level caching behavior.
 *
 * This attribute can be applied to methods to define their caching strategy,
 * including cache key template, TTL, invalidation rules, and tags.
 *
 * @see \Mnemosyne\CacheTrait For the implementation of caching behavior
 *
 * @example
 * ```php
 * #[Cache(key: 'user:{id}', ttl: 3600, tags: ['user', 'user-{id}'])]
 * public function getUser(int $id): array
 * {
 *     return $this->cacheCall('doGetUser', func_get_args());
 * }
 * ```
 *
 * @author Carlos Matos <carlos@example.com>
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
class Cache
{
    /**
     * Configure caching behavior for a method.
     *
     * @param string|null $key Cache key template. Supports parameter interpolation using {param} syntax.
     *                        If null, a key will be auto-generated based on class, method and arguments.
     * @param int|null $ttl Time-to-live in seconds. If null, cache will not expire.
     * @param array $invalidates List of cache key templates to invalidate when this method is called.
     * @param array $tags List of tags to associate with this cache entry. Supports parameter interpolation.
     * @param bool|null $serialize Whether to serialize the result before caching.
     */
    public function __construct(
        public ?string $key = null,
        public ?int $ttl = null,
        public array $invalidates = [],
        public array $tags = [],
        public ?bool $serialize = null,
    ) {
    }
}
