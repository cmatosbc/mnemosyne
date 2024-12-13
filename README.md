# Mnemosyne - PHP Attribute-based Caching Library

[![PHP Lint](https://github.com/cmatosbc/mnemosyne/actions/workflows/lint.yml/badge.svg)](https://github.com/cmatosbc/mnemosyne/actions/workflows/lint.yml) [![PHPUnit Tests](https://github.com/cmatosbc/mnemosyne/actions/workflows/phpunit.yml/badge.svg)](https://github.com/cmatosbc/mnemosyne/actions/workflows/phpunit.yml) [![PHP Composer](https://github.com/cmatosbc/mnemosyne/actions/workflows/composer.yml/badge.svg)](https://github.com/cmatosbc/mnemosyne/actions/workflows/composer.yml)

Mnemosyne is a powerful and flexible caching library for PHP 8.0+ that uses attributes to simplify cache management. It provides automatic caching and invalidation based on method attributes, making it easy to add caching to your application.

## Features

- Attribute-based caching configuration
- Automatic cache key generation
- Parameter-based cache keys with interpolation
- Automatic and manual cache invalidation
- Cache tags for group invalidation
- PSR-16 (SimpleCache) compatibility
- Flexible cache key templates
- Smart serialization handling:
  - Automatic serialization of complex objects
  - Optional raw storage for simple data types
  - Full control over serialization behavior

## Installation

```bash
composer require cmatosbc/mnemosyne
```

## Usage

To use Mnemosyne in your classes, you must:
1. Use the `CacheTrait` in your class
2. Inject a PSR-16 compatible cache implementation
3. Apply the `Cache` attribute to methods you want to cache

### Basic Usage

```php
use Mnemosyne\Cache;
use Mnemosyne\CacheTrait;
use Psr\SimpleCache\CacheInterface;

class UserService
{
    use CacheTrait;  // Required to enable caching functionality

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    #[Cache(ttl: 3600)]
    public function getUser(int $id): array
    {
        return $this->cacheCall('doGetUser', func_get_args());
    }

    private function doGetUser(int $id): array
    {
        // Expensive database query here
        return ['id' => $id, 'name' => 'John Doe'];
    }
}
```

### Serialization Control

The `Cache` attribute allows you to control how values are stored in cache:

```php
class UserService
{
    use CacheTrait;

    // Automatically serialize complex objects
    #[Cache(key: 'user:{id}', ttl: 3600, serialize: true)]
    public function getUser(int $id): User
    {
        return $this->cacheCall('doGetUser', func_get_args());
    }

    // Store simple arrays without serialization
    #[Cache(key: 'users:list', ttl: 3600, serialize: false)]
    public function getUsersList(): array
    {
        return $this->cacheCall('doGetUsersList', func_get_args());
    }
}
```

### Custom Cache Keys

```php
class UserService
{
    use CacheTrait;

    #[Cache(key: 'user:{id}', ttl: 3600)]
    public function getUser(int $id): array
    {
        return $this->cacheCall('doGetUser', func_get_args());
    }

    #[Cache(key: 'users:dept:{deptId}:status:{status}', ttl: 3600)]
    public function getUsersByDepartment(int $deptId, string $status): array
    {
        return $this->cacheCall('doGetUsersByDepartment', func_get_args());
    }
}
```

### Cache Invalidation

#### Automatic Invalidation

```php
class UserService
{
    use CacheTrait;

    #[Cache(
        key: 'user:{id}',
        ttl: 3600
    )]
    public function getUser(int $id): array
    {
        return $this->cacheCall('doGetUser', func_get_args());
    }

    #[Cache(invalidates: ['user:{id}'])]
    public function updateUser(int $id, array $data): void
    {
        $this->cacheCall('doUpdateUser', func_get_args());
    }

    #[Cache(
        key: 'user:profile:{id}',
        ttl: 3600,
        invalidates: ['user:{id}', 'users:dept:{deptId}:status:active']
    )]
    public function updateProfile(int $id, int $deptId): array
    {
        return $this->cacheCall('doUpdateProfile', func_get_args());
    }
}
```

#### Manual Invalidation

```php
class UserService
{
    use CacheTrait;

    public function forceRefresh(int $userId): void
    {
        $this->invalidateCache("user:$userId");
        // Or invalidate multiple keys:
        $this->invalidateCacheKeys([
            "user:$userId",
            "user:profile:$userId"
        ]);
    }
}
```

### Cache Tags

Cache tags allow you to group related cache entries and invalidate them together. This is useful for managing cache dependencies and bulk invalidation.

```php
class UserService
{
    use CacheTrait;

    #[Cache(
        key: 'user:{id}',
        ttl: 3600,
        tags: ['user', 'user-{id}']
    )]
    public function getUser(int $id): array
    {
        return $this->cacheCall('doGetUser', func_get_args());
    }

    #[Cache(
        key: 'user:profile:{id}',
        ttl: 3600,
        tags: ['user', 'user-{id}']
    )]
    public function getUserProfile(int $id): array
    {
        return $this->cacheCall('doGetUserProfile', func_get_args());
    }

    public function updateUser(int $id): void
    {
        // Invalidate all caches for a specific user
        $this->invalidateTag("user-$id");
    }

    public function clearAllUserCaches(): void
    {
        // Invalidate all user-related caches
        $this->invalidateTag('user');
    }
}
```

Tags support parameter interpolation just like cache keys, allowing you to create dynamic tag names. When a tag is invalidated, all cache entries associated with that tag are automatically removed.

## Best Practices

1. Split cached methods into two parts:
   - A public method with the Cache attribute that handles caching
   - A private method with the actual implementation
   
2. Use meaningful cache keys that reflect the data structure
3. Set appropriate TTL values based on data volatility
4. Use cache invalidation when data is modified
5. Consider using cache tags for group invalidation

## Testing

The library includes comprehensive PHPUnit tests. Run them with:

```bash
./vendor/bin/phpunit
```

## License

This project is licensed under the GNU General Public License v3.0 or later - see the LICENSE file for details. This means you are free to use, modify, and distribute this software, but any modifications must also be released under the GPL-3.0-or-later license.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.
