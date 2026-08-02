@php
    $fallbackParameters = ($dhakaDistrict ?? null) ? ['district_id' => $dhakaDistrict->id] : [];
@endphp

<div
    class="cl-district-explorer"
    data-district-explorer
    data-resolve-url="{{ route('public.district.resolve') }}"
    data-fallback-url="{{ route('public.projects.index', $fallbackParameters) }}"
>
    <button class="civic-earth__primary cl-district-explorer__button" type="button" data-district-explorer-button>
        Explore my district
    </button>
    <span class="cl-district-explorer__status" data-district-explorer-status aria-live="polite">Dhaka is used if location is unavailable.</span>
    <noscript><a class="civic-earth__secondary" href="{{ route('public.projects.index', $fallbackParameters) }}">Explore Dhaka</a></noscript>
</div>
