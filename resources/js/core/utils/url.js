const rawBasePath = document.querySelector('meta[name="pharmanp-base-path"]')?.content || '';
const rawApiBaseUrl = import.meta.env.VITE_PHARMANP_API_BASE_URL || import.meta.env.VITE_API_BASE_URL || '';

export const basePath = rawBasePath === '/' ? '' : rawBasePath.replace(/\/$/, '');
export const apiBaseUrl = rawApiBaseUrl.replace(/\/$/, '');
export const standaloneFrontend = import.meta.env.VITE_PHARMANP_STANDALONE === 'true' || import.meta.env.VITE_FRONTEND_STANDALONE === 'true';

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
