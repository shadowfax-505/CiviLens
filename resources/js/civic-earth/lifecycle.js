export function shouldConsumeWheel({
    scrollTop,
    scrollHeight,
    clientHeight,
    deltaY,
}) {
    if (!deltaY || scrollHeight <= clientHeight) {
        return false;
    }

    const maximum = Math.max(scrollHeight - clientHeight, 0);

    return deltaY > 0 ? scrollTop < maximum : scrollTop > 0;
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
