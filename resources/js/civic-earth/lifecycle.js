const clamp = (value) => Math.min(1, Math.max(0, value));

export function documentJourneyProgress({ journeyTop, journeyHeight, viewportHeight, scrollY }) {
    const distance = Math.max(journeyHeight - viewportHeight, 0);

    return distance === 0 ? 0 : clamp((scrollY - journeyTop) / distance);
}

export function documentJourneyTarget({ journeyTop, journeyHeight, viewportHeight }, progress) {
    const distance = Math.max(journeyHeight - viewportHeight, 0);

    return journeyTop + (distance * clamp(progress));
}

export function createCleanupRegistry() {
    const callbacks = [];
    let cleaned = false;

    return {
        add(callback) {
            if (!cleaned) {
                callbacks.push(callback);
            }
        },
        run() {
            if (cleaned) {
                return;
            }

            cleaned = true;
            callbacks.reverse().forEach((callback) => callback());
            callbacks.length = 0;
        },
    };
}
