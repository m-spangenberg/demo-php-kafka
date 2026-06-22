<?php

declare(strict_types=1);

namespace App\Infrastructure;

use Redis;

final class RedisStore
{
    private Redis $redis;

    public function __construct()
    {
        $this->redis = new Redis();
        $this->redis->connect(
            Config::string('REDIS_HOST', 'redis'),
            Config::int('REDIS_PORT', 6379)
        );
    }

    public function client(): Redis
    {
        return $this->redis;
    }
}
