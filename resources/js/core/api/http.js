import axios from 'axios';

export const apiTokenStorageKey = 'pharmanp.api_token';

export const authMode = 'token';
export const usesTokenAuth = true;

export const http = axios.create({
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'Cache-Control': 'no-cache, no-store, must-revalidate',
        'Pragma': 'no-cache',
        'Expires': '0',
    },
    withCredentials: false,
});

const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const envToken = import.meta.env.VITE_PHARMANP_API_TOKEN;
const storedToken = localStorage.getItem(apiTokenStorageKey);
const bootToken = envToken || storedToken;

if (csrf) {
    http.defaults.headers.common['X-CSRF-TOKEN'] = csrf;
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

export function getApiToken() {
    return localStorage.getItem(apiTokenStorageKey) || null;
}

if (bootToken) {
    setApiToken(bootToken);
}

export function responseToken(body) {
    return body?.token
        || body?.access_token
        || body?.data?.token
        || body?.data?.access_token
        || null;
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
