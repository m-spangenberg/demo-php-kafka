<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Domain\FleetSnapshot;
use App\Http\JsonResponse;
use App\Infrastructure\Config;
use App\Infrastructure\RedisStore;

$snapshot = new FleetSnapshot(new RedisStore());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input') ?: '{}', true);
    $action = $body['action'] ?? '';

    if ($action === 'reset') {
        $snapshot->clearAll();
    } elseif ($action === 'start') {
        $current = $snapshot->simulationState();
        $snapshot->setSimulationState([
            'running' => 1,
            'targetVehicles' => Config::int('FLEET_VEHICLE_COUNT', 100),
            'startedAt' => $current['startedAt'] ?: gmdate(DATE_ATOM),
            'lastTickAt' => $current['lastTickAt'] ?: gmdate(DATE_ATOM),
            'generation' => $current['generation'] ?: bin2hex(random_bytes(8)),
        ]);
    } elseif ($action === 'stop') {
        $current = $snapshot->simulationState();
        $snapshot->setSimulationState([
            'running' => 0,
            'targetVehicles' => $current['targetVehicles'] ?? Config::int('FLEET_VEHICLE_COUNT', 100),
            'startedAt' => $current['startedAt'] ?? '',
            'lastTickAt' => $current['lastTickAt'] ?? '',
            'generation' => $current['generation'] ?? '',
        ]);
    }
}

JsonResponse::send([
    'simulation' => $snapshot->simulationState(),
]);
