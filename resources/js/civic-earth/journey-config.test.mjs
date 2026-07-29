import test from 'node:test';
import assert from 'node:assert/strict';

import {
    CAMERAS,
    CHAPTERS,
    computeJourneyState,
    progressForStage,
    stageIndexForKey,
} from './journey-config.js';

test('the journey follows the approved nine-stage camera cadence', () => {
    assert.equal(CHAPTERS.length, 9);
    assert.equal(CAMERAS.length, 9);

    assert.deepEqual(CAMERAS[0], {
        lon: 8,
        lat: 6,
        height: 22_000_000,
        heading: 0,
        pitch: -90,
    });
    assert.deepEqual(CAMERAS[8], {
        lon: 90.4,
        lat: 23.77,
        height: 60_000,
        heading: -10,
        pitch: -62,
    });
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
