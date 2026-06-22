<?php

declare(strict_types=1);

namespace App\Infrastructure;

final class Config
{
    public static function string(string $key, string $default = ''): string
    {
        $value = getenv($key);

        return $value === false ? $default : $value;
    }

    public static function int(string $key, int $default = 0): int
    {
        return (int) self::string($key, (string) $default);
    }

    public static function float(string $key, float $default = 0.0): float
    {
        return (float) self::string($key, (string) $default);
    }
}
