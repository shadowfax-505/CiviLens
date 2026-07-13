@props([
    'lat' => null,
    'lng' => null,
    'label' => 'Map location',
])

@php
    $latitude = old('latitude', $lat);
    $longitude = old('longitude', $lng);
@endphp

<div {{ $attributes->merge(['class' => 'cl-map-picker md:col-span-2']) }} data-leaflet-picker>
    <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="cl-kicker">Coordinates</p>
            <h2 class="cl-card-title mt-1">{{ $label }}</h2>
            <p class="mt-1 text-sm cl-muted">Click the OpenStreetMap preview or drag the marker to set the exact location.</p>
        </div>
        <p class="text-xs font-bold cl-muted" data-leaflet-status>{{ $latitude && $longitude ? 'Location pinned' : 'No location pinned yet' }}</p>
    </div>
    <div class="cl-map-canvas" data-leaflet-map></div>
    <div class="grid gap-3 md:grid-cols-2">
        <label class="grid gap-1 text-sm font-semibold">
            Latitude
            <input name="latitude" value="{{ $latitude }}" inputmode="decimal" data-leaflet-lat>
        </label>
        <label class="grid gap-1 text-sm font-semibold">
            Longitude
            <input name="longitude" value="{{ $longitude }}" inputmode="decimal" data-leaflet-lng>
        </label>
    </div>
</div>
