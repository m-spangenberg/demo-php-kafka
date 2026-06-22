<?php

declare(strict_types=1);

namespace App\Consumers;

use App\Domain\FleetSnapshot;
use App\Domain\Geo;
use App\Domain\Zones;
use App\Infrastructure\Config;
use App\Infrastructure\KafkaFactory;
use App\Infrastructure\RedisStore;
use RdKafka\Message;

final class FleetProjector
{
    private FleetSnapshot $snapshot;
    private \RdKafka\Producer $producer;
    private \RdKafka\ProducerTopic $alertsTopic;

    public function __construct()
    {
        $this->snapshot = new FleetSnapshot(new RedisStore());
        $this->producer = KafkaFactory::producer();
        $this->alertsTopic = $this->producer->newTopic(Config::string('FLEET_TOPIC_ALERTS', 'fleet.alerts'));
    }

    public function run(): void
    {
        $consumer = KafkaFactory::consumer('smart-fleet-projector');
        $consumer->subscribe([Config::string('FLEET_TOPIC_TELEMETRY', 'fleet.telemetry')]);

        while (true) {
            $message = $consumer->consume(1000);

            if ($message->err !== RD_KAFKA_RESP_ERR_NO_ERROR) {
                if ($message->err === RD_KAFKA_RESP_ERR__PARTITION_EOF || $message->err === RD_KAFKA_RESP_ERR__TIMED_OUT) {
                    continue;
                }

                fwrite(STDERR, sprintf("Kafka error: %s\n", $message->errstr()));
                continue;
            }

            $this->project($message);
        }
    }

    private function project(Message $message): void
    {
        $payload = json_decode($message->payload, true, flags: JSON_THROW_ON_ERROR);
        $previous = $this->snapshot->vehicle($payload['vehicleId']);
        $zone = $this->matchedZone((float) $payload['location']['lat'], (float) $payload['location']['lng']);

        $vehicle = [
            'vehicleId' => $payload['vehicleId'],
            'timestamp' => $payload['timestamp'],
            'lat' => $payload['location']['lat'],
            'lng' => $payload['location']['lng'],
            'speedKmh' => $payload['speedKmh'],
            'engineTempC' => $payload['engineTempC'],
            'tirePressurePsi' => $payload['tirePressurePsi'],
            'headingDeg' => $payload['headingDeg'],
            'status' => $payload['status'],
            'zone' => $zone,
        ];

        $this->snapshot->updateVehicle($vehicle);
        $this->updateMetrics();

        if ($zone !== null && (($previous['zone']['id'] ?? null) !== $zone['id'])) {
            $this->emitAlert([
                'id' => uniqid('alert-zone-', true),
                'severity' => 'critical',
                'type' => 'no-go-zone',
                'vehicleId' => $payload['vehicleId'],
                'message' => sprintf('%s entered %s', $payload['vehicleId'], $zone['name']),
                'timestamp' => $payload['timestamp'],
                'location' => $payload['location'],
            ]);
        }

        if ((int) $payload['engineTempC'] >= 108 && ((int) ($previous['engineTempC'] ?? 0)) < 108) {
            $this->emitAlert([
                'id' => uniqid('alert-engine-', true),
                'severity' => 'critical',
                'type' => 'engine-temp',
                'vehicleId' => $payload['vehicleId'],
                'message' => sprintf('%s engine temperature reached %s C', $payload['vehicleId'], $payload['engineTempC']),
                'timestamp' => $payload['timestamp'],
                'location' => $payload['location'],
            ]);
        }

        if ((int) $payload['tirePressurePsi'] <= 28 && ((int) ($previous['tirePressurePsi'] ?? 99)) > 28) {
            $this->emitAlert([
                'id' => uniqid('alert-tire-', true),
                'severity' => 'warning',
                'type' => 'tire-pressure',
                'vehicleId' => $payload['vehicleId'],
                'message' => sprintf('%s tire pressure dropped to %s PSI', $payload['vehicleId'], $payload['tirePressurePsi']),
                'timestamp' => $payload['timestamp'],
                'location' => $payload['location'],
            ]);
        }
    }

    private function emitAlert(array $alert): void
    {
        $this->snapshot->addAlert($alert);
        $this->alertsTopic->produce(RD_KAFKA_PARTITION_UA, 0, json_encode($alert, JSON_THROW_ON_ERROR), $alert['vehicleId']);
        $this->producer->poll(0);
        $this->producer->flush(1000);
    }

    private function matchedZone(float $lat, float $lng): ?array
    {
        foreach (Zones::definitions() as $zone) {
            if (Geo::pointInPolygon($lat, $lng, $zone['polygon'])) {
                return $zone;
            }
        }

        return null;
    }

    private function updateMetrics(): void
    {
        $vehicles = $this->snapshot->vehicles();
        $alerts = $this->snapshot->alerts(100);
        $count = count($vehicles);

        if ($count === 0) {
            return;
        }

        $speed = 0;
        $temp = 0;
        $pressure = 0;

        foreach ($vehicles as $vehicle) {
            $speed += (float) $vehicle['speedKmh'];
            $temp += (float) $vehicle['engineTempC'];
            $pressure += (float) $vehicle['tirePressurePsi'];
        }

        $criticalAlerts = count(array_filter($alerts, static fn (array $alert): bool => $alert['severity'] === 'critical'));

        $this->snapshot->setMetrics([
            'vehicleCount' => $count,
            'activeAlerts' => count($alerts),
            'criticalAlerts' => $criticalAlerts,
            'averageSpeed' => round($speed / $count, 1),
            'averageTemp' => round($temp / $count, 1),
            'averageTirePressure' => round($pressure / $count, 1),
            'updatedAt' => gmdate(DATE_ATOM),
        ]);
    }
}
