<?php

declare(strict_types=1);

namespace App\Simulation;

use App\Domain\Geo;
use App\Infrastructure\Config;
use App\Infrastructure\KafkaFactory;
use App\Domain\Zones;
use App\Domain\FleetSnapshot;
use App\Infrastructure\RedisStore;

/**
 * FleetSimulator generates simulated telemetry data for a number of vehicles and produces it to a Kafka topic.
 * The simulator runs in an infinite loop, advancing the state of each vehicle and producing telemetry data at random intervals. 
 * The state of the simulation is stored in Redis.
 */
final class FleetSimulator
{
    private const ISSUE_PROFILES = [
        7 => 'engine',
        13 => 'tire',
        22 => 'engine',
    ];

    private const WALVIS_BAY_POLYGON = [
        ['lat' => -22.981824, 'lng' => 14.475108],
        ['lat' => -22.979710, 'lng' => 14.475923],
        ['lat' => -22.978249, 'lng' => 14.479999],
        ['lat' => -22.971453, 'lng' => 14.487206],
        ['lat' => -22.967778, 'lng' => 14.488343],
        ['lat' => -22.963511, 'lng' => 14.487099],
        ['lat' => -22.961238, 'lng' => 14.482208],
        ['lat' => -22.959124, 'lng' => 14.480730],
        ['lat' => -22.958215, 'lng' => 14.481652],
        ['lat' => -22.957267, 'lng' => 14.484076],
        ['lat' => -22.955983, 'lng' => 14.484162],
        ['lat' => -22.949482, 'lng' => 14.480880],
        ['lat' => -22.945511, 'lng' => 14.482896],
        ['lat' => -22.947407, 'lng' => 14.487079],
        ['lat' => -22.953236, 'lng' => 14.483861],
        ['lat' => -22.955746, 'lng' => 14.485213],
        ['lat' => -22.955943, 'lng' => 14.486478],
        ['lat' => -22.952347, 'lng' => 14.494694],
        ['lat' => -22.947289, 'lng' => 14.500914],
        ['lat' => -22.946123, 'lng' => 14.499927],
        ['lat' => -22.926461, 'lng' => 14.519328],
        ['lat' => -22.924761, 'lng' => 14.518556],
        ['lat' => -22.924484, 'lng' => 14.517012],
        ['lat' => -22.923872, 'lng' => 14.517226],
        ['lat' => -22.922567, 'lng' => 14.521542],
        ['lat' => -22.916856, 'lng' => 14.528685],
        ['lat' => -22.908258, 'lng' => 14.535442],
        ['lat' => -22.901044, 'lng' => 14.538471],
        ['lat' => -22.902111, 'lng' => 14.600457],
        ['lat' => -22.991899, 'lng' => 14.613670],
        ['lat' => -22.996007, 'lng' => 14.498699],
        ['lat' => -22.986921, 'lng' => 14.482998],
    ];

    private const WALVIS_BAY_BOUNDS = [
        'minLat' => -22.996007,
        'maxLat' => -22.901044,
        'minLng' => 14.475108,
        'maxLng' => 14.613670,
    ];

    private const NAMIBIA_BOUNDS = [
        'minLat' => -28.97,
        'maxLat' => -16.95,
        'minLng' => 11.73,
        'maxLng' => 25.26,
    ];

    private array $vehicles = [];
    private \RdKafka\Producer $producer;
    private \RdKafka\ProducerTopic $topic;
    private string $generation = '';

    public function __construct(
        private readonly FleetSnapshot $snapshot,
        private readonly int $vehicleCount,
        private readonly int $minInterval,
        private readonly int $maxInterval,
    ) {
        $this->producer = KafkaFactory::producer();
        $this->topic = $this->producer->newTopic(Config::string('FLEET_TOPIC_TELEMETRY', 'fleet.telemetry'));
    }

    public static function boot(): self
    {
        return new self(
            new FleetSnapshot(new RedisStore()),
            Config::int('FLEET_VEHICLE_COUNT', 30),
            Config::int('SIMULATION_MIN_INTERVAL', 10),
            Config::int('SIMULATION_MAX_INTERVAL', 30),
        );
    }

    public function run(): void
    {
        while (true) {
            $state = $this->snapshot->simulationState();

            if (($state['running'] ?? '0') !== '1') {
                usleep(500000);
                continue;
            }

            $generation = (string) ($state['generation'] ?? '');

            if ($this->vehicles === [] || $generation !== $this->generation) {
                $this->generation = $generation;
                $this->seedVehicles();
            }

            $now = time();

            foreach ($this->vehicles as $vehicleId => &$vehicle) {
                if ($now < $vehicle['nextDueAt']) {
                    continue;
                }

                $payload = $this->advanceVehicle($vehicle, $now);
                $this->topic->produce(RD_KAFKA_PARTITION_UA, 0, json_encode($payload, JSON_THROW_ON_ERROR), $vehicleId);
                $vehicle['nextDueAt'] = $now + random_int($this->minInterval, $this->maxInterval);
            }

            unset($vehicle);
            $this->producer->poll(0);
            $this->producer->flush(1000);

            $this->snapshot->setSimulationState([
                'running' => 1,
                'targetVehicles' => $this->vehicleCount,
                'startedAt' => $state['startedAt'] ?? gmdate(DATE_ATOM),
                'lastTickAt' => gmdate(DATE_ATOM),
                'generation' => $this->generation,
            ]);

            sleep(max(1, Config::int('SIMULATION_TICK_SECONDS', 1)));
        }
    }

    private function seedVehicles(): void
    {
        $this->vehicles = [];

        for ($index = 1; $index <= $this->vehicleCount; $index++) {
            $vehicleId = sprintf('FLT-%04d', $index);
            $issueType = self::ISSUE_PROFILES[$index] ?? null;
            $basePoint = $this->randomSafePoint();
            $targetPoint = $this->randomOperationalTarget($basePoint);

            $this->vehicles[$vehicleId] = [
                'vehicleId' => $vehicleId,
                'lat' => $basePoint['lat'],
                'lng' => $basePoint['lng'],
                'speedKmh' => $issueType === null ? random_int(38, 76) : random_int(48, 84),
                'engineTempC' => $issueType === 'engine' ? random_int(101, 104) : random_int(82, 88),
                'tirePressurePsi' => $issueType === 'tire' ? random_int(29, 31) : random_int(33, 36),
                'headingDeg' => random_int(0, 359),
                'targetLat' => $targetPoint['lat'],
                'targetLng' => $targetPoint['lng'],
                'nextDueAt' => time() + random_int(1, 4),
                'issueType' => $issueType,
            ];
        }
    }

    private function advanceVehicle(array &$vehicle, int $timestamp): array
    {
        $previousLat = $vehicle['lat'];
        $previousLng = $vehicle['lng'];
        $candidate = [
            'lat' => $vehicle['lat'] + (($vehicle['targetLat'] - $vehicle['lat']) * 0.18),
            'lng' => $vehicle['lng'] + (($vehicle['targetLng'] - $vehicle['lng']) * 0.18),
        ];

        if ($this->isRestrictedPoint($candidate['lat'], $candidate['lng']) || $this->isRestrictedSegment($vehicle, $candidate)) {
            $nextTarget = $this->randomOperationalTarget([
                'lat' => $vehicle['lat'],
                'lng' => $vehicle['lng'],
            ]);
            $vehicle['targetLat'] = $nextTarget['lat'];
            $vehicle['targetLng'] = $nextTarget['lng'];
        } else {
            $vehicle['lat'] = round($candidate['lat'], 6);
            $vehicle['lng'] = round($candidate['lng'], 6);
        }

        if (abs($vehicle['targetLat'] - $vehicle['lat']) < 0.0012 && abs($vehicle['targetLng'] - $vehicle['lng']) < 0.0012) {
            $nextTarget = $this->randomOperationalTarget([
                'lat' => $vehicle['lat'],
                'lng' => $vehicle['lng'],
            ]);
            $vehicle['targetLat'] = $nextTarget['lat'];
            $vehicle['targetLng'] = $nextTarget['lng'];
        }

        if (Geo::distanceKm($previousLat, $previousLng, $vehicle['lat'], $vehicle['lng']) > 0.03) {
            $vehicle['headingDeg'] = Geo::bearingDegrees($previousLat, $previousLng, $vehicle['lat'], $vehicle['lng']);
        }

        $speedBias = $vehicle['issueType'] === 'engine' ? 1 : 0;
        $engineBias = match ($vehicle['issueType']) {
            'engine' => 3,
            default => 0,
        };
        $tireBias = $vehicle['issueType'] === 'tire' ? -1 : 0;
        $engineRecovery = $vehicle['issueType'] === 'engine'
            ? 0
            : ($vehicle['engineTempC'] > 88 ? -1 : ($vehicle['engineTempC'] < 83 ? 1 : 0));
        $tireRecovery = $vehicle['issueType'] === 'tire'
            ? 0
            : ($vehicle['tirePressurePsi'] < 33 ? 1 : ($vehicle['tirePressurePsi'] > 36 ? -1 : 0));

        $vehicle['speedKmh'] = max(0, min(102, $vehicle['speedKmh'] + random_int(-6, 6) + $speedBias));
        $vehicle['engineTempC'] = max(78, min(114, $vehicle['engineTempC'] + random_int(-1, 2) + ($vehicle['speedKmh'] > 92 ? 1 : 0) + $engineBias + $engineRecovery));
        $vehicle['tirePressurePsi'] = max(26, min(38, $vehicle['tirePressurePsi'] + random_int(-1, 1) + $tireBias + $tireRecovery));
        $vehicle['headingDeg'] = ($vehicle['headingDeg'] + random_int(-10, 10) + 360) % 360;

        $zoneName = null;

        foreach (Zones::definitions() as $zone) {
            if (Geo::pointInPolygon($vehicle['lat'], $vehicle['lng'], $zone['polygon'])) {
                $zoneName = $zone['name'];
                break;
            }
        }

        $status = 'nominal';

        if ($vehicle['engineTempC'] >= 108 || $zoneName !== null) {
            $status = 'critical';
        } elseif ($vehicle['engineTempC'] >= 101 || $vehicle['tirePressurePsi'] <= 29) {
            $status = 'warning';
        }

        return [
            'vehicleId' => $vehicle['vehicleId'],
            'timestamp' => gmdate(DATE_ATOM, $timestamp),
            'location' => [
                'lat' => round($vehicle['lat'], 6),
                'lng' => round($vehicle['lng'], 6),
            ],
            'speedKmh' => $vehicle['speedKmh'],
            'engineTempC' => $vehicle['engineTempC'],
            'tirePressurePsi' => $vehicle['tirePressurePsi'],
            'headingDeg' => $vehicle['headingDeg'],
            'status' => $status,
            'suspectedZone' => $zoneName,
        ];
    }

    private function randomCoordinate(float $min, float $max): float
    {
        return round($min + (mt_rand(0, 10000) / 10000) * ($max - $min), 6);
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    private function randomSafePoint(): array
    {
        for ($attempt = 0; $attempt < 200; $attempt++) {
            $point = [
                'lat' => $this->randomCoordinate(self::WALVIS_BAY_BOUNDS['minLat'], self::WALVIS_BAY_BOUNDS['maxLat']),
                'lng' => $this->randomCoordinate(self::WALVIS_BAY_BOUNDS['minLng'], self::WALVIS_BAY_BOUNDS['maxLng']),
            ];

            if (!$this->isRestrictedPoint($point['lat'], $point['lng'])) {
                return $point;
            }
        }

        return [
            'lat' => -22.955746,
            'lng' => 14.485213,
        ];
    }

    private function randomOperationalTarget(array $origin): array
    {
        for ($attempt = 0; $attempt < 120; $attempt++) {
            $point = [
                'lat' => $this->clamp($origin['lat'] + $this->randomOffset(0.002, 0.016), self::WALVIS_BAY_BOUNDS['minLat'], self::WALVIS_BAY_BOUNDS['maxLat']),
                'lng' => $this->clamp($origin['lng'] + $this->randomOffset(0.002, 0.02), self::WALVIS_BAY_BOUNDS['minLng'], self::WALVIS_BAY_BOUNDS['maxLng']),
            ];

            if ($this->isRestrictedPoint($point['lat'], $point['lng'])) {
                continue;
            }

            if ($this->isRestrictedSegment($origin, $point)) {
                continue;
            }

            return $point;
        }

        return $this->randomSafePoint();
    }

    private function randomOffset(float $min, float $max): float
    {
        $magnitude = $min + (mt_rand(0, 1000) / 1000) * ($max - $min);

        return mt_rand(0, 1) === 0 ? -$magnitude : $magnitude;
    }

    private function isRestrictedPoint(float $lat, float $lng): bool
    {
        if (!Geo::pointInPolygon($lat, $lng, self::WALVIS_BAY_POLYGON)) {
            return true;
        }

        foreach (Zones::defaultDefinitions() as $zone) {
            if (Geo::pointInPolygon($lat, $lng, $zone['polygon'])) {
                return true;
            }
        }

        return false;
    }

    private function isRestrictedSegment(array $origin, array $target): bool
    {
        for ($step = 1; $step <= 8; $step++) {
            $progress = $step / 8;
            $lat = Geo::interpolate($origin['lat'], $target['lat'], $progress);
            $lng = Geo::interpolate($origin['lng'], $target['lng'], $progress);

            if ($this->isRestrictedPoint($lat, $lng)) {
                return true;
            }
        }

        return false;
    }
}
