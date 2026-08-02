import test from 'node:test';
import assert from 'node:assert/strict';

import { bootstrapCivicEarth } from './bootstrap.js';

function createRoot() {
    const classes = new Set();
    const properties = new Map();

    return {
        classList: {
            add: (value) => classes.add(value),
            contains: (value) => classes.has(value),
        },
        dataset: {
            fallbackTexture: '/images/orbital/fallback.jpg',
        },
        style: {
            setProperty: (name, value) => properties.set(name, value),
        },
        classes,
        properties,
    };
}

test('the static NASA fallback is prepared before the renderer chunk loads', async () => {
    const root = createRoot();
    let fallbackAtImport = null;

    await bootstrapCivicEarth(root, async () => {
        fallbackAtImport = root.properties.get('--civic-earth-fallback');
        throw new Error('renderer unavailable');
    });

    assert.equal(fallbackAtImport, 'url("/images/orbital/fallback.jpg")');
    assert.equal(root.classList.contains('has-renderer-fallback'), true);
});

test('a successful renderer import still uses the existing initializer interface', async () => {
    const root = createRoot();
    let initializedRoot = null;

    const result = await bootstrapCivicEarth(root, async () => ({
        initializeCivicEarth: (value) => {
            initializedRoot = value;

            return { destroy: () => {} };
        },
    }));

    assert.equal(initializedRoot, root);
    assert.equal(typeof result.destroy, 'function');
});
