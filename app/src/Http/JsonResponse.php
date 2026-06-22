<?php

declare(strict_types=1);

namespace App\Http;

// We use this helper to send JSON responses from our API endpoints.
// Example usage: JsonResponse::send(['message' => 'Hello, world!'], 200);

final class JsonResponse
{
    public static function send(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}
