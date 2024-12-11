<?php

namespace Mnemosyne;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
class Cache
{
    public function __construct(
        public ?string $key = null,
        public ?int $ttl = null,
        public array $invalidates = [],
    ) {
    }
}
