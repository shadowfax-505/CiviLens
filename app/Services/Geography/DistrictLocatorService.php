<?php

namespace App\Services\Geography;

use App\Models\District;

class DistrictLocatorService
{
    public function locate(float $latitude, float $longitude): ?District
    {
        $districts = District::query()
            ->where(function ($query): void {
                $query->whereNotNull('geojson')
                    ->orWhere(function ($query): void {
                        $query->whereNotNull('latitude')->whereNotNull('longitude');
                    });
            })
            ->orderBy('id')
            ->get();

        $containingDistrict = $districts->first(
            fn (District $district): bool => $this->geometryContains($district->geojson, $latitude, $longitude),
        );

        if ($containingDistrict instanceof District) {
            return $containingDistrict;
        }

        $nearest = $districts
            ->filter(fn (District $district): bool => $district->latitude !== null && $district->longitude !== null)
            ->sortBy(fn (District $district): float => $this->distanceSquared($district, $latitude, $longitude))
            ->first();

        if ($nearest instanceof District && $this->distanceSquared($nearest, $latitude, $longitude) <= 9) {
            return $nearest;
        }

        return District::query()->whereRaw('LOWER(name) = ?', ['dhaka'])->first();
    }

    /** @param array<string, mixed>|string|null $geojson */
    private function geometryContains(array|string|null $geojson, float $latitude, float $longitude): bool
    {
        if (is_string($geojson)) {
            $geojson = json_decode($geojson, true);
        }

        if (! is_array($geojson)) {
            return false;
        }

        $geometry = ($geojson['type'] ?? null) === 'Feature'
            ? ($geojson['geometry'] ?? null)
            : $geojson;

        if (! is_array($geometry)) {
            return false;
        }

        $coordinates = $geometry['coordinates'] ?? [];
        if (! is_array($coordinates)) {
            return false;
        }

        if (($geometry['type'] ?? null) === 'Polygon') {
            return $this->polygonContains($coordinates, $latitude, $longitude);
        }

        if (($geometry['type'] ?? null) !== 'MultiPolygon') {
            return false;
        }

        foreach ($coordinates as $polygon) {
            if (is_array($polygon) && $this->polygonContains($polygon, $latitude, $longitude)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, mixed> $rings */
    private function polygonContains(array $rings, float $latitude, float $longitude): bool
    {
        if ($rings === [] || ! is_array($rings[0]) || ! $this->ringContains($rings[0], $latitude, $longitude)) {
            return false;
        }

        foreach (array_slice($rings, 1) as $hole) {
            if (is_array($hole) && $this->ringContains($hole, $latitude, $longitude)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<int, mixed> $ring */
    private function ringContains(array $ring, float $latitude, float $longitude): bool
    {
        $inside = false;
        $count = count($ring);

        if ($count < 3) {
            return false;
        }

        for ($current = 0, $previous = $count - 1; $current < $count; $previous = $current++) {
            $currentPoint = $ring[$current];
            $previousPoint = $ring[$previous];
            if (! is_array($currentPoint) || ! is_array($previousPoint)
                || ! is_numeric($currentPoint[0] ?? null) || ! is_numeric($currentPoint[1] ?? null)
                || ! is_numeric($previousPoint[0] ?? null) || ! is_numeric($previousPoint[1] ?? null)) {
                return false;
            }

            $currentLongitude = (float) $currentPoint[0];
            $currentLatitude = (float) $currentPoint[1];
            $previousLongitude = (float) $previousPoint[0];
            $previousLatitude = (float) $previousPoint[1];
            $crossesLatitude = ($currentLatitude > $latitude) !== ($previousLatitude > $latitude);

            if ($crossesLatitude) {
                $intersection = ($previousLongitude - $currentLongitude)
                    * ($latitude - $currentLatitude)
                    / ($previousLatitude - $currentLatitude)
                    + $currentLongitude;

                if ($longitude < $intersection) {
                    $inside = ! $inside;
                }
            }
        }

        return $inside;
    }

    private function distanceSquared(District $district, float $latitude, float $longitude): float
    {
        $latitudeDelta = (float) $district->latitude - $latitude;
        $longitudeDelta = ((float) $district->longitude - $longitude) * cos(deg2rad($latitude));

        return ($latitudeDelta ** 2) + ($longitudeDelta ** 2);
    }
}
