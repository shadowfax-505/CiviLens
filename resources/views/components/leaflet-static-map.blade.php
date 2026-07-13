@props([
    'label' => 'Map',
    'lat' => null,
    'lng' => null,
    'geojson' => null,
    'markers' => [],
])

<section {{ $attributes->merge(['class' => 'cl-map-picker']) }} data-leaflet-static-map @if ($lat !== null) data-lat="{{ $lat }}" @endif @if ($lng !== null) data-lng="{{ $lng }}" @endif @if ($geojson !== null) data-geojson='@js($geojson)' @endif @if ($markers !== []) data-markers='@js($markers)' @endif>
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.2em] cl-muted">Location Map</p>
            <h3 class="text-sm font-semibold">{{ $label }}</h3>
        </div>
        <p class="text-xs font-bold cl-muted">{{ $lat !== null && $lng !== null ? 'Exact location' : 'Map view' }}</p>
    </div>
    <div class="cl-map-canvas mt-4" data-leaflet-map></div>
</section>
