<?php

declare(strict_types=1);

namespace App\Domain;

final class RoadNetwork
{
    private const DEFAULT_PATH = __DIR__ . '/../../data/namibia-major-roads.json';

    /** @var array<string, array{lat: float, lng: float}> */
    private array $nodes;

    /** @var array<string, array<int, array{to: string, distanceKm: float}>> */
    private array $adjacency;

    /** @var list<string> */
    private array $nodeIds;

    /** @var array<string, list<string>> */
    private array $routeCache = [];

    private function __construct(array $nodes, array $adjacency)
    {
        $this->nodes = $nodes;
        $this->adjacency = $adjacency;
        $this->nodeIds = array_keys($nodes);
    }

    public static function load(string $path = ''): self
    {
        $resolvedPath = $path !== '' ? $path : self::DEFAULT_PATH;

        if (!is_file($resolvedPath)) {
            return new self([], []);
        }

        $raw = file_get_contents($resolvedPath);

        if (!is_string($raw) || $raw === '') {
            return new self([], []);
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return new self([], []);
        }

        $nodes = [];

        foreach (($decoded['nodes'] ?? []) as $nodeId => $node) {
            if (!is_array($node) || !isset($node['lat'], $node['lng'])) {
                continue;
            }

            $nodes[(string) $nodeId] = [
                'lat' => round((float) $node['lat'], 6),
                'lng' => round((float) $node['lng'], 6),
            ];
        }

        $adjacency = [];

        foreach (($decoded['edges'] ?? []) as $edge) {
            if (!is_array($edge) || count($edge) < 3) {
                continue;
            }

            [$fromNode, $toNode, $distanceKm] = $edge;
            $fromId = (string) $fromNode;
            $toId = (string) $toNode;

            if (!isset($nodes[$fromId], $nodes[$toId])) {
                continue;
            }

            $distance = max(0.01, (float) $distanceKm);
            $adjacency[$fromId][] = ['to' => $toId, 'distanceKm' => $distance];
            $adjacency[$toId][] = ['to' => $fromId, 'distanceKm' => $distance];
        }

        return new self($nodes, $adjacency);
    }

    public function isAvailable(): bool
    {
        return $this->nodeIds !== [];
    }

    public function randomNode(?callable $filter = null, int $attempts = 240): ?array
    {
        if ($this->nodeIds === []) {
            return null;
        }

        $lastNode = null;
        $maxIndex = count($this->nodeIds) - 1;

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            $nodeId = $this->nodeIds[random_int(0, $maxIndex)];
            $node = $this->node($nodeId);

            if ($node === null) {
                continue;
            }

            $lastNode = $node;

            if ($filter === null || $filter($node) === true) {
                return $node;
            }
        }

        if ($filter === null) {
            return $lastNode;
        }

        foreach ($this->nodeIds as $nodeId) {
            $node = $this->node($nodeId);

            if ($node !== null && $filter($node) === true) {
                return $node;
            }
        }

        return null;
    }

    public function randomNodeNear(float $lat, float $lng, float $minDistanceKm, float $maxDistanceKm, ?callable $filter = null, int $attempts = 320): ?array
    {
        $minDistance = max(0.0, $minDistanceKm);
        $maxDistance = max($minDistance, $maxDistanceKm);

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            $node = $this->randomNode($filter, 1);

            if ($node === null) {
                return null;
            }

            $distance = Geo::distanceKm($lat, $lng, $node['lat'], $node['lng']);

            if ($distance >= $minDistance && $distance <= $maxDistance) {
                return $node;
            }
        }

        return $this->nearestNode($lat, $lng, $filter, $maxDistance);
    }

    public function nearestNode(float $lat, float $lng, ?callable $filter = null, ?float $maxDistanceKm = null): ?array
    {
        $nearestNode = null;
        $bestDistance = INF;

        foreach ($this->nodeIds as $nodeId) {
            $node = $this->node($nodeId);

            if ($node === null) {
                continue;
            }

            if ($filter !== null && $filter($node) !== true) {
                continue;
            }

            $distance = Geo::distanceKm($lat, $lng, $node['lat'], $node['lng']);

            if ($maxDistanceKm !== null && $distance > $maxDistanceKm) {
                continue;
            }

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $nearestNode = $node;
            }
        }

        return $nearestNode;
    }

    public function route(string $startNodeId, string $endNodeId): array
    {
        if ($startNodeId === $endNodeId) {
            return isset($this->nodes[$startNodeId]) ? [$startNodeId] : [];
        }

        $cacheKey = $startNodeId . ':' . $endNodeId;

        if (isset($this->routeCache[$cacheKey])) {
            return $this->routeCache[$cacheKey];
        }

        if (!isset($this->adjacency[$startNodeId], $this->adjacency[$endNodeId])) {
            return [];
        }

        $distances = [$startNodeId => 0.0];
        $previous = [];
        $queue = new \SplPriorityQueue();
        $queue->setExtractFlags(\SplPriorityQueue::EXTR_BOTH);
        $queue->insert($startNodeId, 0.0);

        while (!$queue->isEmpty()) {
            $current = $queue->extract();
            $currentNodeId = (string) $current['data'];
            $currentDistance = -((float) $current['priority']);

            if ($currentDistance > ($distances[$currentNodeId] ?? INF)) {
                continue;
            }

            if ($currentNodeId === $endNodeId) {
                break;
            }

            foreach ($this->adjacency[$currentNodeId] as $edge) {
                $tentativeDistance = $currentDistance + $edge['distanceKm'];

                if ($tentativeDistance >= ($distances[$edge['to']] ?? INF)) {
                    continue;
                }

                $distances[$edge['to']] = $tentativeDistance;
                $previous[$edge['to']] = $currentNodeId;
                $queue->insert($edge['to'], -$tentativeDistance);
            }
        }

        if (!isset($previous[$endNodeId])) {
            return [];
        }

        $route = [$endNodeId];
        $cursor = $endNodeId;

        while (isset($previous[$cursor])) {
            $cursor = $previous[$cursor];
            array_unshift($route, $cursor);
        }

        $this->rememberRoute($cacheKey, $route);
        $reverseCacheKey = $endNodeId . ':' . $startNodeId;
        $this->rememberRoute($reverseCacheKey, array_reverse($route));

        return $route;
    }

    public function coordinates(array $nodeIds): array
    {
        $points = [];

        foreach ($nodeIds as $nodeId) {
            $node = $this->node((string) $nodeId);

            if ($node !== null) {
                $points[] = [
                    'lat' => $node['lat'],
                    'lng' => $node['lng'],
                ];
            }
        }

        return $points;
    }

    public function coordinate(string $nodeId): ?array
    {
        return $this->node($nodeId);
    }

    private function node(string $nodeId): ?array
    {
        if (!isset($this->nodes[$nodeId])) {
            return null;
        }

        return [
            'id' => $nodeId,
            'lat' => $this->nodes[$nodeId]['lat'],
            'lng' => $this->nodes[$nodeId]['lng'],
        ];
    }

    private function rememberRoute(string $cacheKey, array $route): void
    {
        $this->routeCache[$cacheKey] = $route;

        if (count($this->routeCache) > 512) {
            array_shift($this->routeCache);
        }
    }
}