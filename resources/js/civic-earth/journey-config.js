export const CHAPTERS = [
    {
        eyebrow: 'Earth system · documented solar geometry',
        title: 'One living Earth.<br><span>Bangladesh in context.</span>',
        lede: 'A deep-space establishing view shows the whole WGS84 planet, recent cloud systems, atmosphere, and a physically valid day–night edge.',
        hint: 'Scroll to approach Earth',
        step: '01 · Earth system',
        scale: '22,000 km view',
        cardTitle: 'A planet, not a decorative globe',
        cardCopy: 'No orbit rings or invented planetary props: only Earth, atmosphere, stars, and celestial objects that the reference geometry actually places in frame.',
    },
    {
        eyebrow: 'Orbital Earth · natural color',
        title: 'Weather gives Earth<br><span>its living texture.</span>',
        lede: 'A validated local NASA composite adds real cloud bands, storms, haze, ocean color, and surface variation over a resilient global fallback.',
        hint: 'Continue to the hemisphere',
        step: '02 · Orbital Earth',
        scale: '13,835 km framing',
        cardTitle: 'Real cloud structure at planetary scale',
        cardCopy: 'Controlled contrast protects detail in clouds, land, ocean, and the atmospheric limb without exposing the browser to raw daily swaths.',
    },
    {
        eyebrow: 'Eastern hemisphere · data context',
        title: 'A connected world.<br><span>Layers gain meaning.</span>',
        lede: 'The 10,000 km presentation scale establishes continents, countries, terrain, and administrative context.',
        hint: 'Continue across the Indian Ocean',
        step: '03 · Hemisphere',
        scale: '10,000 km data view',
        cardTitle: 'Global scale, made purposeful',
        cardCopy: 'Country boundaries remain restrained while Earth observation imagery still carries the visual story.',
    },
    {
        eyebrow: 'Indian Ocean · approach corridor',
        title: 'Ocean. Monsoon.<br><span>A continental approach.</span>',
        lede: 'A measured intermediate camera keeps coastlines, cloud systems, and regional geometry readable on the approach to South Asia.',
        hint: 'Continue toward South Asia',
        step: '04 · Indian Ocean',
        scale: '6,000 km approach',
        cardTitle: 'The bridge between globe and region',
        cardCopy: 'This stage is geographic, not theatrical: a measured camera path crosses the Indian Ocean toward the subcontinent.',
    },
    {
        eyebrow: 'South Asia · terrain and watershed',
        title: 'Mountains. Basin. Bay.<br><span>The region resolves.</span>',
        lede: 'Topography, rivers, coastline, neighboring states, and transport references explain Bangladesh’s physical context.',
        hint: 'Continue into the delta basin',
        step: '05 · South Asia',
        scale: '2,800 km regional',
        cardTitle: 'The landscape explains the nation',
        cardCopy: 'Regional imagery fades in as the Himalayas, Indo-Gangetic plain, and Bay of Bengal become legible.',
    },
    {
        eyebrow: 'GBM delta · environmental system',
        title: 'Three rivers.<br><span>One immense delta.</span>',
        lede: 'The Ganges–Brahmaputra–Meghna basin becomes its own scale: floodplain, estuary, Sundarbans, sediment, and settlement patterns read together.',
        hint: 'Continue to Bangladesh',
        step: '06 · Delta basin',
        scale: '1,400 km basin view',
        cardTitle: 'A country shaped by water',
        cardCopy: 'The basin view explains Bangladesh before administrative boundaries take visual priority.',
    },
    {
        eyebrow: 'Bangladesh · verified national geometry',
        title: 'Every estuary.<br><span>The true national edge.</span>',
        lede: 'The country fills the scene with its river-cut border, coast, islands, terrain, and satellite detail.',
        hint: 'Reveal the administrative fabric',
        step: '07 · Bangladesh',
        scale: '850 km national',
        cardTitle: 'National geography without approximation',
        cardCopy: 'The outline and island geometry remain clamped to the same WGS84 Earth—not redrawn for visual convenience.',
    },
    {
        eyebrow: 'Bangladesh · administrative detail',
        title: '8 divisions.<br><span>64 real districts.</span>',
        lede: 'Roads, place labels, division borders, and district boundaries appear only when camera height makes them legible.',
        hint: 'Continue to public evidence',
        step: '08 · Districts',
        scale: '320 km administrative',
        cardTitle: 'More detail, less noise',
        cardCopy: 'Line weight and visibility follow camera altitude so the map becomes more informative without becoming less realistic.',
    },
    {
        eyebrow: 'CivicLens · evidence anchored to place',
        title: 'Real places.<br><span>Reviewable public records.</span>',
        lede: 'Projects, agencies, procurement, contractors, spending, progress, and supporting evidence attach to their permission-safe public coordinates.',
        hint: 'Civic map ready to explore',
        step: '09 · Civic data',
        scale: '60 km local',
        cardTitle: 'The map becomes public navigation',
        cardCopy: 'Production markers come only from permission-safe CivicLens records and lead to their public project evidence.',
    },
];

export const CAMERAS = [
    { lon: 8, lat: 6, height: 22_000_000, heading: 0, pitch: -90 },
    { lon: 16, lat: 9, height: 13_835_000, heading: 0, pitch: -90 },
    { lon: 48, lat: 17, height: 10_000_000, heading: 0, pitch: -90 },
    { lon: 70, lat: 12, height: 6_000_000, heading: 0, pitch: -90 },
    { lon: 83.2, lat: 24.4, height: 2_800_000, heading: 0, pitch: -90 },
    { lon: 88.1, lat: 24, height: 1_400_000, heading: 0, pitch: -90 },
    { lon: 90.3, lat: 23.7, height: 850_000, heading: 0, pitch: -90 },
    { lon: 90.25, lat: 23.72, height: 320_000, heading: -5, pitch: -78 },
    { lon: 90.4, lat: 23.77, height: 60_000, heading: -10, pitch: -62 },
];

export const IMAGERY_CALIBRATION = {
    fallback: { brightness: 0.88, contrast: 1.31, saturation: 1.34, gamma: 0.86 },
    runtime: { brightness: 0.93, contrast: 1.28, saturation: 1.18, gamma: 0.88 },
    regional: { brightness: 0.96, contrast: 1.18, saturation: 1.14, gamma: 0.94 },
};

const clamp = (value, minimum = 0, maximum = 1) => Math.min(maximum, Math.max(minimum, value));
const mix = (from, to, progress) => from + (to - from) * progress;

function interpolateCamera(progress) {
    const position = progress * (CAMERAS.length - 1);
    const index = Math.min(CAMERAS.length - 2, Math.floor(position));
    const amount = clamp(position - index);
    const from = CAMERAS[index];
    const to = CAMERAS[index + 1];

    return {
        lon: mix(from.lon, to.lon, amount),
        lat: mix(from.lat, to.lat, amount),
        height: Math.exp(mix(Math.log(from.height), Math.log(to.height), amount)),
        heading: mix(from.heading, to.heading, amount),
        pitch: mix(from.pitch, to.pitch, amount),
    };
}

export function computeJourneyState(rawProgress) {
    const progress = clamp(Number.isFinite(rawProgress) ? rawProgress : 0);
    const orbitalBlend = 1 - clamp((progress - 0.34) / 0.16);

    return {
        progress,
        stageIndex: Math.min(CHAPTERS.length - 1, Math.floor(progress * CHAPTERS.length)),
        camera: interpolateCamera(progress),
        fallbackOpacity: 1,
        runtimeOpacity: 1 - clamp((progress - 0.39) / 0.13),
        regionalOpacity: 1 - orbitalBlend,
        regionalCalibration: {
            brightness: mix(0.92, 0.99, progress),
            contrast: mix(1.18, 1.22, progress),
            saturation: mix(1.12, 1.18, progress),
            gamma: mix(0.94, 0.92, progress),
        },
        labelsOpacity: mix(0.08, 0.9, clamp((progress - 0.2) / 0.5)),
        roadsOpacity: clamp((progress - 0.66) / 0.14, 0, 0.78),
        civicOpacity: clamp((progress - 0.875) / 0.1),
        mapMode: progress > 0.36,
    };
}

export function progressForStage(rawIndex) {
    return clamp(Number.isFinite(rawIndex) ? rawIndex : 0, 0, CHAPTERS.length - 1) / (CHAPTERS.length - 1);
}

export function stageIndexForKey(key, currentIndex) {
    const index = clamp(currentIndex, 0, CHAPTERS.length - 1);

    if (['ArrowDown', 'ArrowRight', 'PageDown'].includes(key)) {
        return Math.min(index + 1, CHAPTERS.length - 1);
    }

    if (['ArrowUp', 'ArrowLeft', 'PageUp'].includes(key)) {
        return Math.max(index - 1, 0);
    }

    if (key === 'Home') {
        return 0;
    }

    if (key === 'End') {
        return CHAPTERS.length - 1;
    }

    return null;
}
