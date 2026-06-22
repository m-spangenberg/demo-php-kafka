<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Simulation\FleetSimulator;

FleetSimulator::boot()->run();
