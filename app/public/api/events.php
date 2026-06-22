<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Domain\FleetSnapshot;
use App\Infrastructure\RedisStore;

$snapshot = new FleetSnapshot(new RedisStore());

ignore_user_abort(true);
set_time_limit(0);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

echo "retry: 3000\n\n";
@ob_flush();
flush();

while (!connection_aborted()) {
    $payload = [
        'vehicles' => $snapshot->vehicles(),
        'alerts' => $snapshot->alerts(),
        'metrics' => $snapshot->metrics(),
        'simulation' => $snapshot->simulationState(),
    ];

    echo "event: snapshot\n";
    echo 'data: ' . json_encode($payload, JSON_THROW_ON_ERROR) . "\n\n";

    @ob_flush();
    flush();
    sleep(2);
}
