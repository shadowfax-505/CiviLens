@props(['endpoint', 'label' => 'Project portfolio map'])

<section {{ $attributes->merge(['class' => 'cl-map-picker']) }} data-project-portfolio-map data-endpoint="{{ $endpoint }}" data-lat="{{ config('civiclens.maps.default_latitude') }}" data-lng="{{ config('civiclens.maps.default_longitude') }}" data-tile-url="{{ config('civiclens.maps.tile_url') }}" data-tile-attribution="{{ config('civiclens.maps.tile_attribution') }}" aria-label="{{ $label }}">
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.2em] cl-muted">Portfolio map</p>
            <h2 class="text-lg font-semibold">{{ $label }}</h2>
        </div>
        <p class="text-xs font-bold cl-muted" data-portfolio-map-status role="status" aria-live="polite">Loading mapped projects…</p>
    </div>
    <div class="cl-map-canvas mt-4" data-portfolio-map-canvas tabindex="0" aria-label="Interactive map of published projects"></div>
    <noscript><p class="mt-3 text-sm cl-muted">Interactive maps require JavaScript. Project results remain available below.</p></noscript>
</section>
