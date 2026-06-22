<?php

declare(strict_types=1);

namespace App\Domain;

// We use a simple ray-casting algorithm to determine if a point is inside a polygon.
// We could use a library for this, but this should be fine for the demo.

final class Geo
{
    public static function pointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        $inside = false;
        $count = count($polygon);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = (float) $polygon[$i]['lng'];
            $yi = (float) $polygon[$i]['lat'];
            $xj = (float) $polygon[$j]['lng'];
            $yj = (float) $polygon[$j]['lat'];

            $intersects = (($yi > $lat) !== ($yj > $lat))
                && ($lng < (($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 0.0000001)) + $xi);

            if ($intersects) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    public static function distanceKm(float $latA, float $lngA, float $latB, float $lngB): float
    {
        $earthRadiusKm = 6371.0;
        $latDelta = deg2rad($latB - $latA);
        $lngDelta = deg2rad($lngB - $lngA);
        $originLat = deg2rad($latA);
        $targetLat = deg2rad($latB);

        $haversine = sin($latDelta / 2) ** 2
            + cos($originLat) * cos($targetLat) * sin($lngDelta / 2) ** 2;

        return 2 * $earthRadiusKm * asin(min(1.0, sqrt($haversine)));
    }

    public static function interpolate(float $start, float $end, float $progress): float
    {
        return $start + (($end - $start) * $progress);
    }

    public static function bearingDegrees(float $latA, float $lngA, float $latB, float $lngB): int
    {
        $originLat = deg2rad($latA);
        $targetLat = deg2rad($latB);
        $deltaLng = deg2rad($lngB - $lngA);
        $y = sin($deltaLng) * cos($targetLat);
        $x = (cos($originLat) * sin($targetLat))
            - (sin($originLat) * cos($targetLat) * cos($deltaLng));
        $bearing = rad2deg(atan2($y, $x));

        return (int) round(fmod($bearing + 360.0, 360.0));
    }
}
