export function isMacPlatform() {
    if (typeof window === 'undefined') {
        return false;
    }

    const platform = window.navigator?.userAgentData?.platform || window.navigator?.platform || '';

    return String(platform).toUpperCase().includes('MAC');
}
