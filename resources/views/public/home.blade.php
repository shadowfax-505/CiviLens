<x-layouts.app title="About CivicLens">
    {{-- ═══════════════════════════════════════════════════════════════
         HERO
    ═══════════════════════════════════════════════════════════════ --}}
    <section class="cl-about-hero cl-about-fade">
        <div class="cl-about-orb cl-about-orb--gold"></div>
        <div class="cl-about-orb cl-about-orb--cyan"></div>

        <div class="relative z-10 max-w-3xl">
            <p class="mb-3 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-[0.68rem] font-bold uppercase tracking-widest text-cyan-200 backdrop-blur">
                <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                Civic Intelligence Platform
            </p>
            <h1 class="font-black leading-[1.02] tracking-tight text-white" style="font-size:clamp(2.2rem,5vw,4.2rem)">
                Public delivery,<br>
                <span class="bg-gradient-to-r from-cyan-200 via-emerald-200 to-amber-200 bg-clip-text text-transparent">seen&nbsp;with&nbsp;precision.</span>
            </h1>
            <p class="mt-5 max-w-2xl text-base leading-relaxed text-white/78 md:text-lg">
                CivicLens is a civic intelligence command center for projects, budgets, procurement, contractors, documents, analytics, and integrity review. It turns fragmented public delivery records into elegant, auditable, permission-aware insight.
            </p>
            <div class="mt-8 flex flex-wrap items-center gap-3">
                <a class="cl-button-primary !px-6 !py-2.5 !text-sm !font-extrabold" href="{{ route('public.projects.index') }}">
                    Explore public projects
                </a>
                <a class="inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/8 px-5 py-2.5 text-sm font-bold text-white no-underline backdrop-blur transition hover:border-white/40 hover:bg-white/15" href="{{ route('public.search') }}">
                    Search the evidence
                    <svg class="h-4 w-4 opacity-70" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </a>
            </div>
        </div>

        <div class="relative z-10 mt-auto grid grid-cols-3 gap-3 pt-4 md:gap-6">
            <div class="rounded-xl border border-white/15 bg-white/8 px-3 py-3 backdrop-blur md:px-5 md:py-4">
                <p class="text-2xl font-black text-white md:text-3xl">{{ $summary['public_projects'] }}</p>
                <p class="mt-0.5 text-[0.68rem] font-bold uppercase tracking-wider text-cyan-200/80">Public Projects</p>
            </div>
            <div class="rounded-xl border border-white/15 bg-white/8 px-3 py-3 backdrop-blur md:px-5 md:py-4">
                <p class="text-2xl font-black text-white md:text-3xl">{{ $summary['public_tenders'] }}</p>
                <p class="mt-0.5 text-[0.68rem] font-bold uppercase tracking-wider text-cyan-200/80">Public Tenders</p>
            </div>
            <div class="rounded-xl border border-white/15 bg-white/8 px-3 py-3 backdrop-blur md:px-5 md:py-4">
                <p class="text-2xl font-black text-white md:text-3xl">{{ $summary['public_documents'] }}</p>
                <p class="mt-0.5 text-[0.68rem] font-bold uppercase tracking-wider text-cyan-200/80">Public Documents</p>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════
         MISSION & VISION
    ═══════════════════════════════════════════════════════════════ --}}
    <section class="mt-8 grid gap-5 lg:grid-cols-5 cl-about-fade">
        <article class="cl-mission-block lg:col-span-3">
            <div class="cl-mission-accent"></div>
            <div class="p-6 pl-8 md:p-8 md:pl-10">
                <p class="cl-kicker">Our Mission</p>
                <h2 class="cl-card-title mt-3 text-xl md:text-2xl">
                    Make civic delivery legible, accountable, and beautifully transparent.
                </h2>
                <p class="mt-4 max-w-xl text-sm leading-relaxed cl-muted md:text-base">
                    CivicLens helps public institutions understand what is being built, who is responsible, how money moves, which documents support each decision, and where deterministic integrity signals deserve human review. It does not make legal accusations. It gives administrators and staff the evidence trail they need to act with confidence.
                </p>
            </div>
        </article>

        <article class="cl-mission-block lg:col-span-2">
            <div class="p-6 md:p-8">
                <p class="cl-kicker">Our Vision</p>
                <h2 class="cl-card-title mt-3 text-xl md:text-2xl">
                    A civic operating system for trust.
                </h2>
                <p class="mt-4 text-sm leading-relaxed cl-muted md:text-base">
                    Every project, budget, contract, vendor, document, alert, and reviewable risk signal connected into one calm, high-integrity workspace.
                </p>
                <div class="mt-6 flex items-center gap-3">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-cyan-500 to-teal-600 text-sm font-bold text-white shadow-lg shadow-teal-500/20">✓</span>
                    <span class="text-xs font-bold uppercase tracking-wider cl-muted">Evidence · Audit · Trust</span>
                </div>
            </div>
        </article>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════
         PILLARS
    ═══════════════════════════════════════════════════════════════ --}}
    <section class="mt-8 cl-about-fade">
        <p class="cl-kicker mb-1">Why CivicLens</p>
        <h2 class="cl-card-title text-lg md:text-xl">Built for institutions that care about delivery.</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['icon' => '📊', 'title' => 'Evidence-first intelligence', 'copy' => 'Every signal is deterministic, threshold-backed, and tied to source records for review.'],
                ['icon' => '🔒', 'title' => 'Permission-aware transparency', 'copy' => 'Public-safe records are separated from sensitive operational data by policies and visibility rules.'],
                ['icon' => '🗄️', 'title' => 'Normalized civic memory', 'copy' => 'Projects, finance, procurement, contractors, documents, and geography share consistent source-of-truth models.'],
                ['icon' => '📈', 'title' => 'Executive-grade analytics', 'copy' => 'Dashboards, snapshots, CSV reports, trends, and alerts help leaders see delivery patterns in real time.'],
            ] as $i => $pillar)
                <article class="cl-pillar-card" style="animation-delay: {{ $i * 0.06 }}s">
                    <div class="cl-pillar-icon">{{ $pillar['icon'] }}</div>
                    <p class="text-sm font-extrabold" style="color:var(--cl-text-muted)">{{ $pillar['title'] }}</p>
                    <p class="mt-2 text-xs leading-relaxed cl-muted">{{ $pillar['copy'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════
         NAVIGATION
    ═══════════════════════════════════════════════════════════════ --}}
    <section class="mt-8 cl-about-fade">
        <p class="cl-kicker mb-1">Explore the platform</p>
        <h2 class="cl-card-title text-lg md:text-xl">Start here.</h2>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ($navigation as $link)
                <a class="cl-about-nav-link" href="{{ $link['route'] }}">
                    {{ $link['label'] }}
                    <span class="cl-about-nav-arrow">→</span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════
         CTA
    ═══════════════════════════════════════════════════════════════ --}}
    <section class="mt-8 cl-about-cta cl-about-fade">
        <h2 class="relative z-10 font-black text-white" style="font-size:clamp(1.4rem,3vw,2.2rem)">
            Ready to see public delivery clearly?
        </h2>
        <p class="relative z-10 mt-3 max-w-xl mx-auto text-sm text-white/78 md:text-base">
            Explore projects, tenders, documents, and analytics — all in one permission-aware, audit-ready workspace.
        </p>
        <div class="relative z-10 mt-6 flex flex-wrap justify-center gap-3">
            <a class="inline-flex items-center gap-2 rounded-full bg-white px-6 py-2.5 text-sm font-extrabold text-teal-800 no-underline shadow-lg shadow-black/15 transition hover:bg-cyan-50 hover:shadow-xl" href="{{ route('public.projects.index') }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2 2z"/></svg>
                Browse projects
            </a>
            <a class="inline-flex items-center gap-2 rounded-full border border-white/30 bg-white/10 px-6 py-2.5 text-sm font-bold text-white no-underline backdrop-blur transition hover:bg-white/20" href="{{ route('public.search') }}">
                Search evidence
                <svg class="h-4 w-4 opacity-70" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
            </a>
        </div>
    </section>
</x-layouts.app>
