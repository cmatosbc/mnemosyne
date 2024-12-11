# Mnemosyne - PHP Attribute-based Caching Library

[![PHP Lint](https://github.com/cmatosbc/mnemosyne/actions/workflows/lint.yml/badge.svg)](https://github.com/cmatosbc/mnemosyne/actions/workflows/lint.yml) [![PHPUnit Tests](https://github.com/cmatosbc/mnemosyne/actions/workflows/phpunit.yml/badge.svg)](https://github.com/cmatosbc/mnemosyne/actions/workflows/phpunit.yml) [![PHP Composer](https://github.com/cmatosbc/mnemosyne/actions/workflows/composer.yml/badge.svg)](https://github.com/cmatosbc/mnemosyne/actions/workflows/composer.yml)

Mnemosyne is a powerful and flexible caching library for PHP 8.0+ that uses attributes to simplify cache management. It provides automatic caching and invalidation based on method attributes, making it easy to add caching to your application.

## Features

- Attribute-based caching configuration
- Automatic cache key generation
- Parameter-based cache keys with interpolation
- Automatic and manual cache invalidation
- PSR-16 (SimpleCache) compatibility
- Flexible cache key templates

## Installation

```bash
composer require cmatosbc/mnemosyne
```

## Usage

### Basic Usage

```php
use Mnemosyne\Cache;
use Mnemosyne\CacheTrait;
use Psr\SimpleCache\CacheInterface;

class UserService
{
    use CacheTrait;

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

MIT License

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.
