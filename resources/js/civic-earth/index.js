import 'cesium/Build/Cesium/Widgets/widgets.css';
import './civic-earth.css';
import './civic-earth-overlays.css';
import './civic-earth-responsive.css';

import {
    CHAPTERS,
    IMAGERY_CALIBRATION,
    computeJourneyState,
    progressForStage,
    stageIndexForKey,
} from './journey-config.js';
import { createCleanupRegistry, shouldConsumeWheel } from './lifecycle.js';

const select = (root, name) => root.querySelector(`[data-earth-${name}]`);
const formatAltitude = (metres) => metres >= 1000
    ? `${Math.round(metres / 1000).toLocaleString()} km`
    : `${Math.round(metres)} m`;

function setCalibration(layer, values) {
    Object.assign(layer, values);
}

function scrollToStage(scroll, index, reducedMotion) {
    scroll.scrollTo({
        top: (scroll.scrollHeight - scroll.clientHeight) * progressForStage(index),
        behavior: reducedMotion ? 'auto' : 'smooth',
    });
}

function setupJourneyControls(root, scroll, draw, reducedMotion, cleanup) {
    const dots = select(root, 'flight-dots');

    CHAPTERS.forEach((chapter, index) => {
        const button = document.createElement('button');
        const handleActivateStage = () => scrollToStage(scroll, index, reducedMotion);
        button.type = 'button';
        button.setAttribute('aria-label', `Go to ${chapter.step}`);
        button.addEventListener('click', handleActivateStage);
        dots?.append(button);
        cleanup.add(() => {
            button.removeEventListener('click', handleActivateStage);
            button.remove();
        });
    });

    const handleWheel = (event) => {
        if (!shouldConsumeWheel({
            scrollTop: scroll.scrollTop,
            scrollHeight: scroll.scrollHeight,
            clientHeight: scroll.clientHeight,
            deltaY: event.deltaY,
        })) {
            return;
        }

        event.preventDefault();
        scroll.scrollTop += event.deltaY;
    };

    const handleKeydown = (event) => {
        const current = computeJourneyState(
            scroll.scrollTop / Math.max(scroll.scrollHeight - scroll.clientHeight, 1),
        ).stageIndex;
        const next = stageIndexForKey(event.key, current);

        if (next === null) {
            return;
        }

        event.preventDefault();
        scrollToStage(scroll, next, reducedMotion);
    };

    const handleScroll = () => draw();

    scroll.addEventListener('wheel', handleWheel, { passive: false, capture: true });
    scroll.addEventListener('keydown', handleKeydown);
    scroll.addEventListener('scroll', handleScroll, { passive: true });

    cleanup.add(() => scroll.removeEventListener('wheel', handleWheel, { capture: true }));
    cleanup.add(() => scroll.removeEventListener('keydown', handleKeydown));
    cleanup.add(() => scroll.removeEventListener('scroll', handleScroll));
}

function updateContent(root, state) {
    const chapter = CHAPTERS[state.stageIndex];
    const values = {
        eyebrow: chapter.eyebrow,
        lede: chapter.lede,
        hint: chapter.hint,
        step: chapter.step,
        scale: chapter.scale,
        'card-title': chapter.cardTitle,
        'card-copy': chapter.cardCopy,
        'flight-name': chapter.step.replace(/^\d+\s·\s/, ''),
        altitude: formatAltitude(state.camera.height),
    };

    Object.entries(values).forEach(([name, value]) => {
        const element = select(root, name);

        if (element) {
            element.textContent = value;
        }
    });

    const title = select(root, 'title');
    if (title) {
        title.innerHTML = chapter.title;
    }

    select(root, 'progress')?.style.setProperty('transform', `scaleY(${state.progress})`);
    select(root, 'data')?.style.setProperty('opacity', state.civicOpacity);
    root.classList.toggle('is-map-mode', state.mapMode);

    select(root, 'flight-dots')?.querySelectorAll('button').forEach((dot, index) => {
        dot.classList.toggle('is-done', index < state.stageIndex);
        dot.classList.toggle('is-current', index === state.stageIndex);
        dot.setAttribute('aria-current', index === state.stageIndex ? 'step' : 'false');
    });
}

async function addSingleTileLayer(Cesium, viewer, url, credit, calibration) {
    const provider = await Cesium.SingleTileImageryProvider.fromUrl(url, {
        credit: new Cesium.Credit(credit),
    });
    const layer = viewer.imageryLayers.addImageryProvider(provider);
    setCalibration(layer, calibration);

    return layer;
}

function addRegionalLayers(Cesium, viewer) {
    const regional = viewer.imageryLayers.addImageryProvider(new Cesium.UrlTemplateImageryProvider({
        url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        credit: 'Esri, Maxar, Earthstar Geographics',
    }));
    setCalibration(regional, IMAGERY_CALIBRATION.regional);
    regional.alpha = 0;

    const roads = viewer.imageryLayers.addImageryProvider(new Cesium.UrlTemplateImageryProvider({
        url: 'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Transportation/MapServer/tile/{z}/{y}/{x}',
        credit: 'Esri',
    }));
    roads.alpha = 0;
    roads.contrast = 1.12;

    const labels = viewer.imageryLayers.addImageryProvider(new Cesium.UrlTemplateImageryProvider({
        url: 'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}',
        credit: 'Esri',
    }));
    labels.alpha = 0;
    labels.contrast = 1.08;

    return { regional, roads, labels };
}

async function addPublicProjectMarkers(Cesium, viewer, endpoint, signal) {
    if (!endpoint) {
        return [];
    }

    const url = new URL(endpoint, window.location.href);
    url.search = new URLSearchParams({
        south: '20.5',
        west: '88',
        north: '26.8',
        east: '92.8',
    }).toString();

    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
        signal,
    });
    if (!response.ok) {
        throw new Error('Public project markers are unavailable.');
    }

    const payload = await response.json();
    if (!Array.isArray(payload.data)) {
        return [];
    }

    return payload.data.flatMap((marker, index) => {
        const latitude = Number.parseFloat(marker.latitude);
        const longitude = Number.parseFloat(marker.longitude);

        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
            return [];
        }

        return [viewer.entities.add({
            id: `public-project-${index}`,
            name: String(marker.name || 'Public project'),
            show: false,
            position: Cesium.Cartesian3.fromDegrees(longitude, latitude, 60),
            point: {
                pixelSize: 10,
                color: Cesium.Color.fromCssColorString('#f42a41'),
                outlineColor: Cesium.Color.WHITE,
                outlineWidth: 2,
                disableDepthTestDistance: Number.POSITIVE_INFINITY,
            },
            label: {
                text: String(marker.name || 'Public project'),
                font: '600 12px system-ui',
                fillColor: Cesium.Color.WHITE,
                outlineColor: Cesium.Color.BLACK.withAlpha(0.8),
                outlineWidth: 3,
                style: Cesium.LabelStyle.FILL_AND_OUTLINE,
                pixelOffset: new Cesium.Cartesian2(0, -20),
                distanceDisplayCondition: new Cesium.DistanceDisplayCondition(0, 100_000),
                disableDepthTestDistance: Number.POSITIVE_INFINITY,
            },
            properties: {
                publicUrl: String(marker.url || ''),
                agency: String(marker.agency || ''),
                status: String(marker.status || ''),
            },
        })];
    });
}

function createViewer(Cesium, container) {
    const viewer = new Cesium.Viewer(container, {
        baseLayer: false,
        animation: false,
        timeline: false,
        baseLayerPicker: false,
        geocoder: false,
        homeButton: false,
        sceneModePicker: false,
        navigationHelpButton: false,
        fullscreenButton: false,
        infoBox: false,
        selectionIndicator: false,
        scene3DOnly: true,
        shouldAnimate: false,
        requestRenderMode: true,
        maximumRenderTimeChange: Number.POSITIVE_INFINITY,
        terrainProvider: new Cesium.EllipsoidTerrainProvider(),
    });
    const scene = viewer.scene;
    const referenceDate = new Date().toISOString().slice(0, 10);

    viewer.clock.currentTime = Cesium.JulianDate.fromIso8601(`${referenceDate}T06:00:00Z`);
    viewer.clock.shouldAnimate = false;
    viewer.resolutionScale = Math.min(window.devicePixelRatio || 1, 1.5);
    scene.globe.enableLighting = true;
    scene.globe.showGroundAtmosphere = true;
    scene.globe.maximumScreenSpaceError = 1.15;
    scene.globe.tileCacheSize = 420;
    scene.highDynamicRange = true;
    scene.skyBox = Cesium.SkyBox.createEarthSkyBox();
    scene.sun.show = true;
    scene.moon.show = true;
    scene.skyAtmosphere.show = true;
    scene.skyAtmosphere.saturationShift = 0.12;
    scene.skyAtmosphere.brightnessShift = -0.08;
    scene.screenSpaceCameraController.enableInputs = false;

    if (scene.postProcessStages?.fxaa) {
        scene.postProcessStages.fxaa.enabled = true;
    }

    if (scene.atmosphere && Cesium.DynamicAtmosphereLightingType) {
        scene.atmosphere.dynamicLighting = Cesium.DynamicAtmosphereLightingType.SUNLIGHT;
    }

    return { viewer, referenceDate };
}

export function initializeCivicEarth(root) {
    if (!root || root.dataset.initialized === 'true') {
        return null;
    }

    const scroll = select(root, 'scroll');
    const container = select(root, 'globe');
    if (!scroll || !container) {
        return null;
    }

    root.dataset.initialized = 'true';
    root.style.setProperty('--civic-earth-fallback', `url("${root.dataset.fallbackTexture}")`);
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const status = select(root, 'status');
    const cleanup = createCleanupRegistry();
    const markerRequest = new AbortController();
    let frame = 0;
    let lastStage = -1;
    let viewer = null;
    let fallbackLayer = null;
    let runtimeLayer = null;
    let regionalLayers = null;
    let projectMarkers = [];
    let cesium = null;
    let destroyed = false;

    const draw = () => {
        frame = 0;
        const rawProgress = scroll.scrollTop / Math.max(scroll.scrollHeight - scroll.clientHeight, 1);
        let state = computeJourneyState(rawProgress);

        if (reducedMotion) {
            state = computeJourneyState(progressForStage(state.stageIndex));
        }

        if (lastStage !== state.stageIndex) {
            lastStage = state.stageIndex;
            updateContent(root, state);
            if (status) {
                status.textContent = `${CHAPTERS[state.stageIndex].step}: ${CHAPTERS[state.stageIndex].cardTitle}`;
            }
        } else {
            select(root, 'progress')?.style.setProperty('transform', `scaleY(${state.progress})`);
            select(root, 'altitude').textContent = formatAltitude(state.camera.height);
            select(root, 'data')?.style.setProperty('opacity', state.civicOpacity);
            root.classList.toggle('is-map-mode', state.mapMode);
        }

        if (viewer && cesium && !viewer.isDestroyed()) {
            viewer.camera.setView({
                destination: cesium.Cartesian3.fromDegrees(
                    state.camera.lon,
                    state.camera.lat,
                    state.camera.height,
                ),
                orientation: {
                    heading: cesium.Math.toRadians(state.camera.heading),
                    pitch: cesium.Math.toRadians(state.camera.pitch),
                    roll: 0,
                },
            });

            if (fallbackLayer) {
                fallbackLayer.alpha = state.fallbackOpacity;
            }
            if (runtimeLayer) {
                runtimeLayer.alpha = state.runtimeOpacity;
            }
            if (regionalLayers) {
                regionalLayers.regional.alpha = state.regionalOpacity;
                setCalibration(regionalLayers.regional, state.regionalCalibration);
                regionalLayers.labels.alpha = state.labelsOpacity;
                regionalLayers.roads.alpha = state.roadsOpacity;
            }
            projectMarkers.forEach((entity) => {
                entity.show = state.civicOpacity > 0.15;
            });
            viewer.scene.requestRender();
        }
    };

    const scheduleDraw = () => {
        if (!frame) {
            frame = window.requestAnimationFrame(draw);
        }
    };

    cleanup.add(() => markerRequest.abort());
    setupJourneyControls(root, scroll, scheduleDraw, reducedMotion, cleanup);
    draw();

    const initializeRenderer = async () => {
        try {
            window.CESIUM_BASE_URL = '/build/cesium/';
            const Cesium = await import('cesium');
            if (destroyed) {
                return;
            }

            cesium = Cesium;
            const setup = createViewer(Cesium, container);
            viewer = setup.viewer;
            select(root, 'time').textContent = `${setup.referenceDate} 06:00 UTC`;

            fallbackLayer = await addSingleTileLayer(
                Cesium,
                viewer,
                root.dataset.fallbackTexture,
                'NASA Blue Marble',
                IMAGERY_CALIBRATION.fallback,
            );
            if (destroyed) {
                return;
            }

            root.classList.add('is-renderer-ready');
            regionalLayers = addRegionalLayers(Cesium, viewer);

            try {
                runtimeLayer = await addSingleTileLayer(
                    Cesium,
                    viewer,
                    root.dataset.runtimeTexture,
                    'NASA EOSDIS MODIS observation composite',
                    IMAGERY_CALIBRATION.runtime,
                );
            } catch {
                if (!destroyed) {
                    select(root, 'imagery').textContent = 'NASA Blue Marble · resilient fallback';
                }
            }

            if (destroyed) {
                return;
            }

            try {
                projectMarkers = await addPublicProjectMarkers(
                    Cesium,
                    viewer,
                    root.dataset.projectMarkersUrl,
                    markerRequest.signal,
                );
            } catch (error) {
                if (error?.name !== 'AbortError' && !destroyed && status) {
                    status.textContent = 'Project markers are temporarily unavailable; public project links remain available.';
                }
            }

            if (!destroyed) {
                draw();
            }
        } catch (error) {
            if (!destroyed && error?.name !== 'AbortError') {
                root.classList.add('has-renderer-fallback');
                if (status) {
                    status.textContent = 'The interactive globe is unavailable. The validated NASA fallback remains visible.';
                }
            }
        }
    };

    const destroy = () => {
        if (destroyed) {
            return;
        }

        destroyed = true;
        cleanup.run();
        if (frame) {
            window.cancelAnimationFrame(frame);
            frame = 0;
        }
        if (viewer && !viewer.isDestroyed()) {
            viewer.destroy();
        }
        viewer = null;
        projectMarkers = [];
        root.classList.remove('is-map-mode', 'is-renderer-ready', 'has-renderer-fallback');
        root.style.removeProperty('--civic-earth-fallback');
        delete root.dataset.initialized;
    };

    void initializeRenderer();

    return { destroy };
}
