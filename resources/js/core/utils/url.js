const rawBasePath = document.querySelector('meta[name="pharmanp-base-path"]')?.content || '';
const rawApiBaseUrl = import.meta.env.VITE_PHARMANP_API_BASE_URL || import.meta.env.VITE_API_BASE_URL || '';
const rawAppBaseUrl = import.meta.env.VITE_PHARMANP_APP_BASE_URL || '';
const rawMediaBaseUrl = import.meta.env.VITE_PHARMANP_MEDIA_BASE_URL || '';
const rootElement = document.getElementById('pharmanp-root');

export const basePath = rawBasePath === '/' ? '' : rawBasePath.replace(/\/$/, '');
export const apiBaseUrl = rawApiBaseUrl.replace(/\/$/, '');
export const appBaseUrl = rawAppBaseUrl.replace(/\/$/, '');
export const mediaBaseUrl = rawMediaBaseUrl.replace(/\/$/, '');
export const standaloneFrontend = import.meta.env.VITE_PHARMANP_STANDALONE === 'true'
    || import.meta.env.VITE_FRONTEND_STANDALONE === 'true'
    || rootElement?.dataset.standalone === 'true';

export function appUrl(path = '') {
    if (! path) {
        return basePath || '/';
    }

    const normalized = path.startsWith('/') ? path : `/${path}`;

    return `${basePath}${normalized}` || normalized;
}

export function apiUrl(path = '') {
    const normalized = path.startsWith('/') ? path : `/${path}`;

    if (apiBaseUrl) {
        return `${apiBaseUrl}${normalized}`;
    }

    return appUrl(normalized);
}

export function backendUrl(path = '') {
    const normalized = path.startsWith('/') ? path : `/${path}`;
    let origin = window.location.origin;

    if (apiBaseUrl && apiBaseUrl.startsWith('http')) {
        try {
            origin = new URL(apiBaseUrl).origin;
        } catch (e) {
            origin = apiBaseUrl.split('/api')[0];
        }
    }

    return `${origin}${normalized}`;
}
