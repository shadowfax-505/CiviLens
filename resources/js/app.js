import Alpine from 'alpinejs';
import L from 'leaflet';
import markerIconUrl from 'leaflet/dist/images/marker-icon.png';
import markerIcon2xUrl from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadowUrl from 'leaflet/dist/images/marker-shadow.png';

window.Alpine = Alpine;
Alpine.start();

// Leaflet auto-detects its marker image path by regex-matching a CSS background-image
// URL against a literal "marker-icon.png" suffix. Vite content-hashes the filename
// (e.g. marker-icon-hN30_KVU.png), so that detection silently fails and every marker
// falls back to a page-relative request that 404s. Configuring the URLs explicitly
// bypasses that detection entirely.
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconUrl: markerIconUrl,
    iconRetinaUrl: markerIcon2xUrl,
    shadowUrl: markerShadowUrl,
});

const appearance = document.documentElement.dataset.appearance || 'system';
const media = window.matchMedia('(prefers-color-scheme: dark)');

function applyAppearance(mode) {
    const prefersDark = mode === 'system' && media.matches;
    document.documentElement.classList.toggle('dark', mode === 'dark' || prefersDark);
    document.documentElement.style.colorScheme = mode === 'dark' || prefersDark ? 'dark' : 'light';
}

applyAppearance(appearance);

media.addEventListener('change', () => {
    if ((document.documentElement.dataset.appearance || 'system') === 'system') {
        applyAppearance('system');
    }
});

function ready(callback) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback);

        return;
    }

    callback();
}

function initializeUploadZones() {
    document.querySelectorAll('[data-upload-zone]').forEach((zone) => {
        if (zone.dataset.initialized === 'true') {
            return;
        }

        zone.dataset.initialized = 'true';

        const input = zone.querySelector('input[type="file"]');
        const filename = zone.querySelector('[data-upload-filename]');

        if (!input) {
            return;
        }

        const setFilename = () => {
            if (!filename) {
                return;
            }

            filename.textContent = input.files?.length ? input.files[0].name : '';
        };

        zone.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            event.preventDefault();
            input.click();
        });

        zone.addEventListener('dragover', (event) => {
            event.preventDefault();
            zone.classList.add('is-dragover');
        });

        zone.addEventListener('dragleave', (event) => {
            if (zone.contains(event.relatedTarget)) {
                return;
            }

            zone.classList.remove('is-dragover');
        });

        zone.addEventListener('drop', (event) => {
            event.preventDefault();
            zone.classList.remove('is-dragover');

            if (!event.dataTransfer?.files?.length) {
                return;
            }

            input.files = event.dataTransfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });

        input.addEventListener('change', setFilename);
    });
}

function numberValue(input) {
    if (!input?.value) {
        return null;
    }

    const parsed = Number.parseFloat(input.value);

    return Number.isFinite(parsed) ? parsed : null;
}

function initializeLeafletPickers() {
    document.querySelectorAll('[data-leaflet-picker]').forEach((element) => {
        if (element.dataset.initialized === 'true') {
            return;
        }

        element.dataset.initialized = 'true';

        const mapTarget = element.querySelector('[data-leaflet-map]');
        const latInput = element.querySelector('[data-leaflet-lat]');
        const lngInput = element.querySelector('[data-leaflet-lng]');
        const status = element.querySelector('[data-leaflet-status]');

        if (!mapTarget || !latInput || !lngInput) {
            return;
        }

        const fallback = [23.685, 90.3563];
        const initialLat = numberValue(latInput);
        const initialLng = numberValue(lngInput);
        const hasInitial = initialLat !== null && initialLng !== null;
        const map = L.map(mapTarget).setView(hasInitial ? [initialLat, initialLng] : fallback, hasInitial ? 7 : 6);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19,
        }).addTo(map);

        let marker = null;

        const updateStatus = (lat, lng) => {
            if (!status) {
                return;
            }

            status.textContent = `Pinned at ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        };

        const setCoordinates = (lat, lng, recenter = false) => {
            latInput.value = lat.toFixed(7);
            lngInput.value = lng.toFixed(7);

            if (marker === null) {
                marker = L.marker([lat, lng], { draggable: true }).addTo(map);
                marker.on('dragend', () => {
                    const position = marker.getLatLng();
                    setCoordinates(position.lat, position.lng);
                });
            } else {
                marker.setLatLng([lat, lng]);
            }

            if (recenter) {
                map.setView([lat, lng], Math.max(map.getZoom(), 8));
            }

            updateStatus(lat, lng);
        };

        if (hasInitial) {
            setCoordinates(initialLat, initialLng);
        }

        map.on('click', (event) => {
            setCoordinates(event.latlng.lat, event.latlng.lng, true);
        });

        [latInput, lngInput].forEach((input) => {
            input.addEventListener('change', () => {
                const lat = numberValue(latInput);
                const lng = numberValue(lngInput);

                if (lat === null || lng === null) {
                    return;
                }

                setCoordinates(lat, lng, true);
            });
        });

        setTimeout(() => map.invalidateSize(), 100);
    });
}

function initializeLeafletStaticMaps() {
    document.querySelectorAll('[data-leaflet-static-map]').forEach((element) => {
        if (element.dataset.initialized === 'true') {
            return;
        }

        element.dataset.initialized = 'true';

        const mapTarget = element.querySelector('[data-leaflet-map]');
        if (!mapTarget) {
            return;
        }

        const lat = Number.parseFloat(element.dataset.lat || '');
        const lng = Number.parseFloat(element.dataset.lng || '');
        const geojsonRaw = element.dataset.geojson || '';
        const markersRaw = element.dataset.markers || '[]';
        const hasCoordinates = Number.isFinite(lat) && Number.isFinite(lng);

        const map = L.map(mapTarget, { scrollWheelZoom: false }).setView(hasCoordinates ? [lat, lng] : [23.685, 90.3563], hasCoordinates ? 11 : 6);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19,
        }).addTo(map);

        try {
            const markers = JSON.parse(markersRaw);

            if (Array.isArray(markers)) {
                markers.forEach((marker) => {
                    const markerLat = Number.parseFloat(marker.lat);
                    const markerLng = Number.parseFloat(marker.lng);

                    if (!Number.isFinite(markerLat) || !Number.isFinite(markerLng)) {
                        return;
                    }

                    L.marker([markerLat, markerLng])
                        .addTo(map)
                        .bindPopup(marker.label || 'Location');
                });
            }

            if (geojsonRaw) {
                const geojson = JSON.parse(geojsonRaw);
                const layer = L.geoJSON(geojson, {
                    style: {
                        color: '#0f766e',
                        weight: 3,
                        opacity: 0.9,
                    },
                }).addTo(map);

                const bounds = layer.getBounds();
                if (bounds.isValid()) {
                    map.fitBounds(bounds.pad(0.15));
                }
            } else if (hasCoordinates) {
                L.marker([lat, lng]).addTo(map);
            }
        } catch {
            if (hasCoordinates) {
                L.marker([lat, lng]).addTo(map);
            }
        }

        setTimeout(() => map.invalidateSize(), 0);
    });
}

function initializeProjectPortfolioMaps() {
    document.querySelectorAll('[data-project-portfolio-map]').forEach((element) => {
        if (element.dataset.initialized === 'true') {
            return;
        }

        element.dataset.initialized = 'true';
        const canvas = element.querySelector('[data-portfolio-map-canvas]');
        const status = element.querySelector('[data-portfolio-map-status]');
        const endpoint = element.dataset.endpoint;

        if (!canvas || !endpoint) {
            return;
        }

        const fallback = [Number.parseFloat(element.dataset.lat || '23.685'), Number.parseFloat(element.dataset.lng || '90.3563')];
        const map = L.map(canvas, { scrollWheelZoom: false }).setView(fallback, 7);
        L.tileLayer(element.dataset.tileUrl || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: element.dataset.tileAttribution || '&copy; OpenStreetMap contributors',
            maxZoom: 19,
        }).addTo(map);

        const markers = L.layerGroup().addTo(map);
        let requestId = 0;

        const popup = (marker) => {
            const wrapper = document.createElement('div');
            const title = document.createElement('strong');
            title.textContent = marker.name;
            wrapper.append(title);
            if (marker.agency) {
                const agency = document.createElement('div');
                agency.textContent = marker.agency;
                wrapper.append(agency);
            }
            const link = document.createElement('a');
            link.href = marker.url;
            link.textContent = 'View project';
            wrapper.append(link);

            return wrapper;
        };

        const loadMarkers = async () => {
            const bounds = map.getBounds();
            const params = new URLSearchParams({
                south: bounds.getSouth().toFixed(6),
                west: bounds.getWest().toFixed(6),
                north: bounds.getNorth().toFixed(6),
                east: bounds.getEast().toFixed(6),
            });
            const currentRequest = ++requestId;
            status.textContent = 'Loading mapped projects…';

            try {
                const response = await fetch(`${endpoint}?${params.toString()}`, { headers: { Accept: 'application/json' } });
                if (!response.ok) {
                    throw new Error('Map data is unavailable.');
                }
                const payload = await response.json();
                if (currentRequest !== requestId) {
                    return;
                }
                markers.clearLayers();
                payload.data.forEach((marker) => L.marker([marker.latitude, marker.longitude]).addTo(markers).bindPopup(popup(marker)));
                status.textContent = payload.meta.capped ? `Showing the first ${payload.meta.count} mapped projects in this area.` : `${payload.meta.count} mapped projects in this area.`;
            } catch {
                if (currentRequest === requestId) {
                    status.textContent = 'Map data is unavailable. Project results remain available below.';
                }
            }
        };

        map.on('moveend', loadMarkers);
        loadMarkers();
        setTimeout(() => map.invalidateSize(), 0);
    });
}

ready(() => {
    initializeUploadZones();
    initializeLeafletPickers();
    initializeLeafletStaticMaps();
    initializeProjectPortfolioMaps();
});
