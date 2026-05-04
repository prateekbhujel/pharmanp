import axios from 'axios';

export const apiTokenStorageKey = 'pharmanp.api_token';
export const authMode = import.meta.env.VITE_PHARMANP_AUTH_MODE || 'session';
export const usesTokenAuth = authMode !== 'session';

export const http = axios.create({
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    withCredentials: true,
});

const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const envToken = import.meta.env.VITE_PHARMANP_API_TOKEN;
const storedToken = usesTokenAuth ? localStorage.getItem(apiTokenStorageKey) : null;
const bootToken = envToken || storedToken;

if (csrf) {
    http.defaults.headers.common['X-CSRF-TOKEN'] = csrf;
}

if (! usesTokenAuth) {
    localStorage.removeItem(apiTokenStorageKey);
}

export function setApiToken(token) {
    if (token) {
        localStorage.setItem(apiTokenStorageKey, token);
        http.defaults.headers.common.Authorization = `Bearer ${token}`;

        return;
    }

    localStorage.removeItem(apiTokenStorageKey);
    delete http.defaults.headers.common.Authorization;
}

if (bootToken) {
    setApiToken(bootToken);
}

export function validationErrors(error) {
    return error?.response?.data?.errors || {};
}

export function apiData(body, fallback = null) {
    return body?.data ?? fallback;
}

export function apiMeta(body, fallback = {}) {
    return body?.meta ?? fallback;
}

export function apiExtra(body) {
    return Object.fromEntries(Object.entries(body || {}).filter(([key]) => ![
        'status',
        'code',
        'message',
        'data',
        'meta',
        'links',
        'errors',
    ].includes(key)));
}
