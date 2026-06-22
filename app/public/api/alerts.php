<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Domain\FleetSnapshot;
use App\Http\JsonResponse;
use App\Infrastructure\RedisStore;

$snapshot = new FleetSnapshot(new RedisStore());

JsonResponse::send([
    'alerts' => $snapshot->alerts(),
]);
