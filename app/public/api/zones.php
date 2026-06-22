<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Domain\Zones;
use App\Http\JsonResponse;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input') ?: '{}', true);
    $name = trim((string) ($body['name'] ?? ''));
    $severity = (string) ($body['severity'] ?? 'warning');
    $polygon = $body['polygon'] ?? [];

    if ($name === '' || !is_array($polygon) || count($polygon) < 3) {
        JsonResponse::send([
            'error' => 'A zone name and at least three polygon points are required.',
        ], 422);
        return;
    }

    Zones::createCustom($name, $severity, $polygon);

    JsonResponse::send([
        'zones' => Zones::definitions(),
    ], 201);
    return;
}

if ($method === 'DELETE') {
    $body = json_decode(file_get_contents('php://input') ?: '{}', true);
    $zoneId = (string) ($body['id'] ?? '');

    if ($zoneId === '') {
        JsonResponse::send([
            'error' => 'A zone id is required.',
        ], 422);
        return;
    }

    if (!Zones::deleteCustom($zoneId)) {
        JsonResponse::send([
            'error' => 'Custom zone not found.',
        ], 404);
        return;
    }

    JsonResponse::send([
        'zones' => Zones::definitions(),
    ]);
    return;
}

JsonResponse::send([
    'zones' => Zones::definitions(),
]);
