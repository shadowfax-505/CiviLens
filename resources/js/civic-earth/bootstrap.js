export async function bootstrapCivicEarth(
    root,
    loadRenderer = () => import('./index.js'),
) {
    if (root.dataset.fallbackTexture) {
        root.style.setProperty('--civic-earth-fallback', `url("${root.dataset.fallbackTexture}")`);
    }

    try {
        const { initializeCivicEarth } = await loadRenderer();

        return initializeCivicEarth(root);
    } catch {
        root.classList.add('has-renderer-fallback');

        return null;
    }
}
