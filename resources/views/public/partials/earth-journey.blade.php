<section
    class="civic-earth cl-about-fade"
    data-civic-earth
    data-fallback-texture="{{ asset('images/orbital/nasa-blue-marble-2004-12-5400x2700.jpg') }}"
    data-runtime-texture="{{ asset('images/orbital/nasa-blue-marble-cloud-observation-composite-5400x2700.jpg') }}"
    data-project-markers-url="{{ route('public.projects.map-data') }}"
    data-public-projects="{{ $summary['public_projects'] }}"
    data-public-tenders="{{ $summary['public_tenders'] }}"
    data-public-documents="{{ $summary['public_documents'] }}"
>
    <div
        class="civic-earth__scroll"
        data-earth-scroll
        tabindex="0"
        role="region"
        aria-label="Nine-stage scroll journey from Earth orbit to public projects in Bangladesh"
    >
        <div class="civic-earth__track">
            <div class="civic-earth__stage">
                <div class="civic-earth__fallback" aria-hidden="true"></div>
                <div class="civic-earth__globe" data-earth-globe aria-hidden="true"></div>
                <div class="civic-earth__shade" aria-hidden="true"></div>
                <div class="civic-earth__grain" aria-hidden="true"></div>

                <header class="civic-earth__header">
                    <a class="civic-earth__brand" href="{{ route('public.about') }}">
                        <span class="civic-earth__logo" aria-hidden="true"></span>
                        <span>CivicLens</span>
                    </a>
                    <span class="civic-earth__stage-count">Earth → Bangladesh · 9 stages</span>
                    <a class="civic-earth__enter" href="{{ route('public.projects.index') }}">Explore projects ↗</a>
                </header>

                <div class="civic-earth__science" aria-label="Scene information">
                    <div><small>Orbital imagery</small><b data-earth-imagery>NASA local composite</b></div>
                    <div><small>Camera altitude</small><b data-earth-altitude>22,000 km</b></div>
                    <div><small>Lighting</small><b>Solar ephemeris</b></div>
                    <div><small>Reference clock</small><b class="civic-earth__live" data-earth-time>Documented UTC</b></div>
                </div>

                <div class="civic-earth__copy">
                    <p class="civic-earth__eyebrow" data-earth-eyebrow>Earth system · documented solar geometry</p>
                    <h1 class="civic-earth__title" data-earth-title>One living Earth.<br><span class="accent">Bangladesh in context.</span></h1>
                    <p class="civic-earth__lede" data-earth-lede>A deep-space establishing view shows the whole WGS84 planet, recent cloud systems, atmosphere, and a physically valid day–night edge.</p>
                    <div class="civic-earth__actions">
                        <a class="civic-earth__primary" href="{{ route('public.projects.index') }}">Explore public data →</a>
                        <a class="civic-earth__secondary" href="{{ route('public.search') }}">Search evidence</a>
                    </div>
                    <p class="civic-earth__hint"><i aria-hidden="true"></i><span data-earth-hint>Scroll inside this scene to descend</span></p>
                </div>

                <aside class="civic-earth__card">
                    <div class="civic-earth__card-meta">
                        <span data-earth-step>01 · Earth system</span>
                        <em data-earth-scale>22,000 km view</em>
                    </div>
                    <strong data-earth-card-title>A planet, not a decorative globe</strong>
                    <p data-earth-card-copy>No orbit rings or invented planetary props: only Earth, atmosphere, stars, and celestial objects that the reference geometry actually places in frame.</p>
                </aside>

                <div class="civic-earth__data" data-earth-data aria-label="Public CivicLens summary">
                    <div><small>Public projects</small><b>{{ $summary['public_projects'] }}</b></div>
                    <div><small>Public tenders</small><b>{{ $summary['public_tenders'] }}</b></div>
                    <div><small>Public documents</small><b>{{ $summary['public_documents'] }}</b></div>
                </div>

                <div class="civic-earth__flight">
                    <span data-earth-flight-name>Earth system</span>
                    <div data-earth-flight-dots aria-label="Nine-stage camera journey"></div>
                </div>

                <div class="civic-earth__progress" aria-hidden="true"><i data-earth-progress></i></div>
                <p class="civic-earth__status sr-only" data-earth-status aria-live="polite"></p>
                <p class="civic-earth__credit">
                    Imagery: <a href="https://science.nasa.gov/earth/earth-observatory/blue-marble-next-generation/" rel="noreferrer">NASA Blue Marble</a>,
                    NASA EOSDIS MODIS observation composite, and
                    <a href="https://www.esri.com/en-us/legal/overview" rel="noreferrer">Esri, Maxar, Earthstar Geographics</a>.
                    Globe: <a href="https://cesium.com/legal/" rel="noreferrer">CesiumJS</a>.
                </p>
            </div>
        </div>
    </div>
</section>
