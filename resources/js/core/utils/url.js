const rawBasePath = document.querySelector('meta[name="pharmanp-base-path"]')?.content || '';

export const basePath = rawBasePath === '/' ? '' : rawBasePath.replace(/\/$/, '');

export function appUrl(path = '') {
    if (! path) {
        return basePath || '/';
    }

    const normalized = path.startsWith('/') ? path : `/${path}`;

    return `${basePath}${normalized}` || normalized;
}
