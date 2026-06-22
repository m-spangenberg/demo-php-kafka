<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Domain\Geo;

ini_set('memory_limit', '768M');

$outputPath = __DIR__ . '/../data/namibia-major-roads.json';
$highways = [
    'trunk',
    'trunk_link',
    'primary',
    'primary_link',
    'secondary',
    'secondary_link',
];

$query = <<<'OVERPASS'
[out:json][timeout:300];
area["ISO3166-1"="NA"][admin_level=2]->.searchArea;
(
    way["highway"~"^(trunk|trunk_link|primary|primary_link|secondary|secondary_link)$"](area.searchArea);
);
out geom qt;
OVERPASS;

fwrite(STDOUT, "Fetching Namibia major roads from OpenStreetMap via Overpass...\n");
$response = fetchOverpass($query);
$payload = json_decode($response, true, flags: JSON_THROW_ON_ERROR);
$nodes = [];
$edges = [];

foreach (($payload['elements'] ?? []) as $element) {
    if (($element['type'] ?? '') !== 'way' || !isset($element['geometry']) || !is_array($element['geometry'])) {
        continue;
    }

    $geometry = array_values(array_filter(
        $element['geometry'],
        static fn (mixed $point): bool => is_array($point) && isset($point['lat'], $point['lon'])
    ));

    if (count($geometry) < 2) {
        continue;
    }

    $simplified = simplifyGeometry($geometry);

    for ($index = 1; $index < count($simplified); $index++) {
        $origin = $simplified[$index - 1];
        $target = $simplified[$index];
        $originId = nodeId((float) $origin['lat'], (float) $origin['lon']);
        $targetId = nodeId((float) $target['lat'], (float) $target['lon']);

        if ($originId === $targetId) {
            continue;
        }

        $nodes[$originId] = [
            'lat' => round((float) $origin['lat'], 6),
            'lng' => round((float) $origin['lon'], 6),
        ];
        $nodes[$targetId] = [
            'lat' => round((float) $target['lat'], 6),
            'lng' => round((float) $target['lon'], 6),
        ];

        $distanceKm = Geo::distanceKm(
            (float) $origin['lat'],
            (float) $origin['lon'],
            (float) $target['lat'],
            (float) $target['lon']
        );

        if ($distanceKm <= 0.0) {
            continue;
        }

        $edgeKey = edgeKey($originId, $targetId);
        $edges[$edgeKey] = [$originId, $targetId, round($distanceKm, 4)];
    }
}

ksort($nodes);
ksort($edges);

if (!is_dir(dirname($outputPath))) {
    mkdir(dirname($outputPath), 0777, true);
}

$encoded = json_encode([
    'metadata' => [
        'generatedAt' => gmdate(DATE_ATOM),
        'source' => 'https://overpass-api.de/api/interpreter',
        'country' => 'Namibia',
        'highways' => $highways,
        'nodeCount' => count($nodes),
        'edgeCount' => count($edges),
    ],
    'nodes' => $nodes,
    'edges' => array_values($edges),
], JSON_THROW_ON_ERROR);

file_put_contents($outputPath, $encoded);
fwrite(STDOUT, sprintf("Wrote %d nodes and %d edges to %s\n", count($nodes), count($edges), $outputPath));

function fetchOverpass(string $query): string
{
    $endpoint = 'https://overpass-api.de/api/interpreter';

    if (function_exists('curl_init')) {
        $handle = curl_init($endpoint);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => http_build_query(['data' => $query]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 300,
        ]);

        $response = curl_exec($handle);

        if (!is_string($response)) {
            $error = curl_error($handle);
            curl_close($handle);
            throw new RuntimeException($error !== '' ? $error : 'Overpass request failed.');
        }

        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($statusCode >= 400) {
            throw new RuntimeException('Overpass request failed with HTTP ' . $statusCode . '.');
        }

        return $response;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query(['data' => $query]),
            'timeout' => 300,
        ],
    ]);
    $response = file_get_contents($endpoint, false, $context);

    if (!is_string($response) || $response === '') {
        throw new RuntimeException('Overpass request returned an empty response.');
    }

    return $response;
}

function nodeId(float $lat, float $lng): string
{
    return sprintf('%.6F,%.6F', round($lat, 6), round($lng, 6));
}

function edgeKey(string $originId, string $targetId): string
{
    return $originId < $targetId ? $originId . '|' . $targetId : $targetId . '|' . $originId;
}

function simplifyGeometry(array $geometry): array
{
    $count = count($geometry);

    if ($count <= 2) {
        return $geometry;
    }

    $simplified = [$geometry[0]];
    $lastKept = $geometry[0];

    for ($index = 1; $index < $count - 1; $index++) {
        $candidate = $geometry[$index];
        $distanceKm = Geo::distanceKm(
            (float) $lastKept['lat'],
            (float) $lastKept['lon'],
            (float) $candidate['lat'],
            (float) $candidate['lon']
        );

        if ($distanceKm < 1.2) {
            continue;
        }

        $simplified[] = $candidate;
        $lastKept = $candidate;
    }

    $lastPoint = $geometry[$count - 1];

    if ($simplified[count($simplified) - 1] !== $lastPoint) {
        $simplified[] = $lastPoint;
    }

    return $simplified;
}