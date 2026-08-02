function sameOriginUrl(value) {
    if (!value) {
        return null;
    }

    const candidate = new URL(value, window.location.origin);

    return candidate.origin === window.location.origin ? candidate.href : null;
}

function navigateTo(value, fallback) {
    window.location.assign(sameOriginUrl(value) ?? sameOriginUrl(fallback) ?? '/public/projects');
}

async function resolvePosition(element, position) {
    const response = await fetch(element.dataset.resolveUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
        }),
    });

    if (!response.ok) {
        throw new Error(`District resolution failed with ${response.status}`);
    }

    return response.json();
}

export function initializeDistrictExplorers() {
    document.querySelectorAll('[data-district-explorer]').forEach((element) => {
        if (element.dataset.initialized === 'true') {
            return;
        }

        element.dataset.initialized = 'true';
        const button = element.querySelector('[data-district-explorer-button]');
        const status = element.querySelector('[data-district-explorer-status]');

        if (!button) {
            return;
        }

        button.addEventListener('click', () => {
            if (button.getAttribute('aria-busy') === 'true') {
                return;
            }

            button.setAttribute('aria-busy', 'true');
            button.disabled = true;
            status.textContent = 'Finding your district…';

            const useFallback = () => {
                status.textContent = 'Location unavailable. Opening Dhaka.';
                navigateTo(element.dataset.fallbackUrl, '/public/projects');
            };

            if (!navigator.geolocation) {
                useFallback();

                return;
            }

            navigator.geolocation.getCurrentPosition(
                async (position) => {
                    try {
                        const result = await resolvePosition(element, position);
                        navigateTo(result.url, element.dataset.fallbackUrl);
                    } catch {
                        useFallback();
                    }
                },
                useFallback,
                { enableHighAccuracy: true, timeout: 8000, maximumAge: 300000 },
            );
        });
    });
}
