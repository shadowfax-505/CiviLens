import test from 'node:test';
import assert from 'node:assert/strict';

import {
    CAMERAS,
    CHAPTERS,
    computeJourneyState,
    progressForStage,
    stageIndexForKey,
} from './journey-config.js';
import { createCleanupRegistry, shouldConsumeWheel } from './lifecycle.js';

const APPROVED_CHAPTERS = [
    {
        eyebrow: 'Earth system · documented solar geometry',
        title: "One living Earth.<br><span class='accent'>Bangladesh in context.</span>",
        lede: 'A deep-space establishing view shows the whole WGS84 planet, recent cloud systems, atmosphere, and a physically valid day–night edge.',
        hint: 'Scroll to approach Earth',
        step: '01 · Earth system',
        scale: '22,000 km view',
        cardTitle: 'A planet, not a decorative globe',
        cardCopy: 'No orbit rings or invented planetary props: only Earth, atmosphere, stars, and celestial objects that the reference geometry actually places in frame.',
    },
    {
        eyebrow: 'Orbital Earth · natural color',
        title: "Weather gives Earth<br><span class='accent'>its living texture.</span>",
        lede: 'Recent MODIS true-color imagery adds real cloud bands, storms, haze, ocean color, and surface variation over a resilient global fallback.',
        hint: 'Continue to the hemisphere',
        step: '02 · Orbital Earth',
        scale: '13,835 km framing',
        cardTitle: 'Real cloud structure at planetary scale',
        cardCopy: 'Daily satellite texture replaces the uniform white cast. Controlled contrast protects detail in clouds, land, ocean, and the atmospheric limb.',
    },
    {
        eyebrow: 'Eastern hemisphere · data context',
        title: "A connected world.<br><span class='accent'>Layers gain meaning.</span>",
        lede: 'The 10,000 km presentation scale from your Earth project establishes continents, countries, terrain, and administrative context.',
        hint: 'Continue across the Indian Ocean',
        step: '03 · Hemisphere',
        scale: '10,000 km data view',
        cardTitle: 'Your project’s global scale, made purposeful',
        cardCopy: 'Country boundaries remain restrained while Earth observation imagery still carries the visual story.',
    },
    {
        eyebrow: 'Indian Ocean · approach corridor',
        title: "Ocean. Monsoon.<br><span class='accent'>A continental approach.</span>",
        lede: 'A new intermediate camera removes the abrupt jump to South Asia and keeps coastlines, cloud systems, and regional geometry readable.',
        hint: 'Continue toward South Asia',
        step: '04 · Indian Ocean',
        scale: '6,000 km approach',
        cardTitle: 'The missing bridge between globe and region',
        cardCopy: 'This stage is geographic, not theatrical: a measured camera path crosses the Indian Ocean toward the subcontinent.',
    },
    {
        eyebrow: 'South Asia · terrain and watershed',
        title: "Mountains. Basin. Bay.<br><span class='accent'>The region resolves.</span>",
        lede: 'Topography, rivers, coastline, neighboring states, and transport references explain Bangladesh’s physical context.',
        hint: 'Continue into the delta basin',
        step: '05 · South Asia',
        scale: '2,800 km regional',
        cardTitle: 'The landscape explains the nation',
        cardCopy: 'High-resolution imagery fades in as the Himalayas, Indo-Gangetic plain, and Bay of Bengal become legible.',
    },
    {
        eyebrow: 'GBM delta · environmental system',
        title: "Three rivers.<br><span class='accent'>One immense delta.</span>",
        lede: 'The Ganges–Brahmaputra–Meghna basin becomes its own scale: floodplain, estuary, Sundarbans, sediment, and settlement patterns read together.',
        hint: 'Continue to Bangladesh',
        step: '06 · Delta basin',
        scale: '1,400 km basin view',
        cardTitle: 'A country shaped by water',
        cardCopy: 'The basin view explains Bangladesh before administrative boundaries take visual priority.',
    },
    {
        eyebrow: 'Bangladesh · verified national geometry',
        title: "Every estuary.<br><span class='accent'>The true national edge.</span>",
        lede: 'The country fills the scene with its verified river-cut border, coast, islands, terrain, and satellite detail.',
        hint: 'Reveal the administrative fabric',
        step: '07 · Bangladesh',
        scale: '850 km national',
        cardTitle: 'National geography without approximation',
        cardCopy: 'The outline and island geometry are loaded from geospatial data and clamped to the same Earth—not redrawn for visual convenience.',
    },
    {
        eyebrow: 'Bangladesh · administrative detail',
        title: "8 divisions.<br><span class='accent'>64 real districts.</span>",
        lede: 'Roads, place labels, division borders, and district boundaries appear only when camera height makes them legible.',
        hint: 'Continue to public evidence',
        step: '08 · Districts',
        scale: '320 km administrative',
        cardTitle: 'More detail, less noise',
        cardCopy: 'Line weight and visibility follow camera altitude so the map becomes more informative without becoming less realistic.',
    },
    {
        eyebrow: 'CivicLens · evidence anchored to place',
        title: "Real places.<br><span class='accent'>Reviewable public records.</span>",
        lede: 'Projects, agencies, procurement, contractors, spending, progress, and supporting evidence attach to their actual coordinates.',
        hint: 'Civic map ready to explore',
        step: '09 · Civic data',
        scale: '60 km local',
        cardTitle: 'The map becomes public navigation',
        cardCopy: 'Production markers come only from permission-safe CivicLens records and lead to the relevant institution, project, contractor, procurement, and evidence.',
    },
];

const APPROVED_CAMERAS = [
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

test('the journey follows the approved nine-stage camera cadence', () => {
    assert.deepEqual(CHAPTERS, APPROVED_CHAPTERS);
    assert.deepEqual(CAMERAS, APPROVED_CAMERAS);
});

test('the local fallback remains visible while orbital and regional layers cross-fade', () => {
    assert.deepEqual(
        {
            fallback: computeJourneyState(0).fallbackOpacity,
            runtime: computeJourneyState(0).runtimeOpacity,
            regional: computeJourneyState(0).regionalOpacity,
        },
        { fallback: 1, runtime: 1, regional: 0 },
    );

    assert.deepEqual(
        {
            fallback: computeJourneyState(0.52).fallbackOpacity,
            runtime: computeJourneyState(0.52).runtimeOpacity,
            regional: computeJourneyState(0.52).regionalOpacity,
        },
        { fallback: 1, runtime: 0, regional: 1 },
    );
});

test('regional imagery retains the approved v10 progressive calibration', () => {
    assert.deepEqual(computeJourneyState(0).regionalCalibration, {
        brightness: 0.92,
        contrast: 1.18,
        saturation: 1.12,
        gamma: 0.94,
    });
    assert.deepEqual(computeJourneyState(1).regionalCalibration, {
        brightness: 0.99,
        contrast: 1.22,
        saturation: 1.18,
        gamma: 0.92,
    });
});

test('stage progress and keyboard navigation are deterministic and bounded', () => {
    assert.equal(progressForStage(4), 0.5);
    assert.equal(progressForStage(99), 1);
    assert.equal(stageIndexForKey('ArrowDown', 4), 5);
    assert.equal(stageIndexForKey('PageUp', 4), 3);
    assert.equal(stageIndexForKey('Home', 4), 0);
    assert.equal(stageIndexForKey('End', 4), 8);
    assert.equal(stageIndexForKey('Enter', 4), null);
});

test('wheel input is consumed only when the inner journey can scroll in that direction', () => {
    const dimensions = { scrollHeight: 800, clientHeight: 200 };

    assert.equal(shouldConsumeWheel({ ...dimensions, scrollTop: 0, deltaY: -1 }), false);
    assert.equal(shouldConsumeWheel({ ...dimensions, scrollTop: 0, deltaY: 1 }), true);
    assert.equal(shouldConsumeWheel({ ...dimensions, scrollTop: 300, deltaY: -1 }), true);
    assert.equal(shouldConsumeWheel({ ...dimensions, scrollTop: 600, deltaY: 1 }), false);
    assert.equal(shouldConsumeWheel({ ...dimensions, scrollTop: 600, deltaY: -1 }), true);
    assert.equal(shouldConsumeWheel({ scrollHeight: 200, clientHeight: 200, scrollTop: 0, deltaY: 1 }), false);
    assert.equal(shouldConsumeWheel({ ...dimensions, scrollTop: 300, deltaY: 0 }), false);
});

test('cleanup registry runs each teardown once in reverse registration order', () => {
    const calls = [];
    const cleanup = createCleanupRegistry();

    cleanup.add(() => calls.push('first'));
    cleanup.add(() => calls.push('second'));
    cleanup.run();
    cleanup.run();

    assert.deepEqual(calls, ['second', 'first']);
});
