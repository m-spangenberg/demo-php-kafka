<?php

declare(strict_types=1);

namespace App\Domain;

use App\Infrastructure\RedisStore;

// These zones represent common restricted areas where the rental vehicles should not be driven.
// In practice, we would want to keep this in a database.

final class Zones
{
    private const CUSTOM_ZONES_KEY = 'fleet:custom-zones';

    public static function definitions(): array
    {
        return array_values(array_merge(self::defaultDefinitions(), self::customDefinitions()));
    }

    public static function defaultDefinitions(): array
    {
        return [
            [
                'id' => 'etosha-core-1',
                'name' => 'Etosha National Park Core',
                'severity' => 'critical',
                'source' => 'default',
                'custom' => false,
                'polygon' => [
                    ['lat' => -19.5200, 'lng' => 15.5200],
                    ['lat' => -19.5200, 'lng' => 16.2800],
                    ['lat' => -18.7800, 'lng' => 16.2800],
                    ['lat' => -18.7800, 'lng' => 15.5200],
                ],
            ],
            [
                'id' => 'sperrgebiet-restricted-2',
                'name' => 'Sperrgebiet Diamond Zone',
                'severity' => 'critical',
                'source' => 'default',
                'custom' => false,
                'polygon' => [
                    ['lat' => -28.4500, 'lng' => 15.0000],
                    ['lat' => -28.4500, 'lng' => 16.9000],
                    ['lat' => -25.9000, 'lng' => 16.9000],
                    ['lat' => -25.9000, 'lng' => 15.0000],
                ],
            ],
            [
                'id' => 'namib-naukluft-3',
                'name' => 'Namib-Naukluft Protected Dunes',
                'severity' => 'warning',
                'source' => 'default',
                'custom' => false,
                'polygon' => [
                    ['lat' => -25.9000, 'lng' => 14.2000],
                    ['lat' => -25.9000, 'lng' => 16.2000],
                    ['lat' => -23.1500, 'lng' => 16.2000],
                    ['lat' => -23.1500, 'lng' => 14.2000],
                ],
            ],
        ];
    }

    public static function createCustom(string $name, string $severity, array $polygon): array
    {
        $zones = self::customDefinitions();
        $zone = [
            'id' => uniqid('custom-zone-', true),
            'name' => trim($name),
            'severity' => $severity === 'critical' ? 'critical' : 'warning',
            'source' => 'custom',
            'custom' => true,
            'polygon' => array_map(
                static fn (array $point): array => [
                    'lat' => round((float) $point['lat'], 6),
                    'lng' => round((float) $point['lng'], 6),
                ],
                $polygon
            ),
        ];

        $zones[] = $zone;
        self::persistCustomDefinitions($zones);

        return $zone;
    }

    public static function deleteCustom(string $zoneId): bool
    {
        $zones = self::customDefinitions();
        $filtered = array_values(array_filter(
            $zones,
            static fn (array $zone): bool => $zone['id'] !== $zoneId
        ));

        if (count($filtered) === count($zones)) {
            return false;
        }

        self::persistCustomDefinitions($filtered);

        return true;
    }

    private static function customDefinitions(): array
    {
        $raw = (new RedisStore())->client()->get(self::CUSTOM_ZONES_KEY);

        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $zones = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

        return array_values(array_filter(
            $zones,
            static fn (mixed $zone): bool => is_array($zone)
                && isset($zone['id'], $zone['name'], $zone['severity'], $zone['polygon'])
        ));
    }

    private static function persistCustomDefinitions(array $zones): void
    {
        (new RedisStore())->client()->set(self::CUSTOM_ZONES_KEY, json_encode($zones, JSON_THROW_ON_ERROR));
    }
}
