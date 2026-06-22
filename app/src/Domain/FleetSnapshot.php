<?php

declare(strict_types=1);

namespace App\Domain;

use App\Infrastructure\RedisStore;

// We use the FleetSnapshot class to store the current state of the fleet in Redis. 
// This allows us to easily access the current state of the fleet without having to query Kafka.

final class FleetSnapshot
{
    private const VEHICLE_HASH = 'fleet:vehicles';
    private const ALERT_LIST = 'fleet:alerts';
    private const STATUS_HASH = 'fleet:simulation';
    private const METRICS_KEY = 'fleet:metrics';

    public function __construct(private readonly RedisStore $store)
    {
    }

    public function setSimulationState(array $state): void
    {
        $payload = [];

        foreach ($state as $key => $value) {
            $payload[(string) $key] = (string) $value;
        }

        $this->store->client()->hMSet(self::STATUS_HASH, $payload);
    }

    public function simulationState(): array
    {
        $state = $this->store->client()->hGetAll(self::STATUS_HASH);

        if ($state === false || $state === []) {
            return [
                'running' => '0',
                'targetVehicles' => (string) 0,
                'startedAt' => '',
                'lastTickAt' => '',
                'generation' => '',
            ];
        }

        return $state;
    }

    public function vehicle(string $vehicleId): ?array
    {
        $raw = $this->store->client()->hGet(self::VEHICLE_HASH, $vehicleId);

        if (!is_string($raw) || $raw === '') {
            return null;
        }

        return json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
    }

    public function updateVehicle(array $payload): void
    {
        $this->store->client()->hSet(self::VEHICLE_HASH, $payload['vehicleId'], json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function vehicles(): array
    {
        $raw = $this->store->client()->hGetAll(self::VEHICLE_HASH);

        if ($raw === false || $raw === []) {
            return [];
        }

        $vehicles = [];

        foreach ($raw as $item) {
            $vehicles[] = json_decode($item, true, flags: JSON_THROW_ON_ERROR);
        }

        usort($vehicles, static fn (array $left, array $right): int => strcmp($left['vehicleId'], $right['vehicleId']));

        return $vehicles;
    }

    public function addAlert(array $alert): void
    {
        $redis = $this->store->client();
        $redis->lPush(self::ALERT_LIST, json_encode($alert, JSON_THROW_ON_ERROR));
        $redis->lTrim(self::ALERT_LIST, 0, 99);
    }

    public function alerts(int $limit = 25): array
    {
        $entries = $this->store->client()->lRange(self::ALERT_LIST, 0, max(0, $limit - 1));

        if ($entries === false || $entries === []) {
            return [];
        }

        return array_map(
            static fn (string $entry): array => json_decode($entry, true, flags: JSON_THROW_ON_ERROR),
            $entries
        );
    }

    public function setMetrics(array $metrics): void
    {
        $this->store->client()->set(self::METRICS_KEY, json_encode($metrics, JSON_THROW_ON_ERROR));
    }

    public function metrics(): array
    {
        $raw = $this->store->client()->get(self::METRICS_KEY);

        if (!is_string($raw) || $raw === '') {
            return [
                'vehicleCount' => 0,
                'activeAlerts' => 0,
                'criticalAlerts' => 0,
                'averageSpeed' => 0,
                'averageTemp' => 0,
                'averageTirePressure' => 0,
                'updatedAt' => gmdate(DATE_ATOM),
            ];
        }

        return json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
    }

    public function clearAll(): void
    {
        $redis = $this->store->client();
        $redis->del([self::VEHICLE_HASH, self::ALERT_LIST, self::METRICS_KEY]);
        $this->setSimulationState([
            'running' => 0,
            'targetVehicles' => 0,
            'startedAt' => '',
            'lastTickAt' => '',
            'generation' => bin2hex(random_bytes(8)),
        ]);
    }
}
