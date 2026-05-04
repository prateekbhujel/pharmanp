import axios from 'axios';

export const apiTokenStorageKey = 'pharmanp.api_token';
const explicitAuthMode = import.meta.env.VITE_PHARMANP_AUTH_MODE || import.meta.env.VITE_AUTH_MODE;
const rootElement = document.getElementById('pharmanp-root');
const frontendIsStandalone = import.meta.env.VITE_PHARMANP_STANDALONE === 'true'
    || import.meta.env.VITE_FRONTEND_STANDALONE === 'true'
    || rootElement?.dataset.standalone === 'true';

export const authMode = explicitAuthMode || (frontendIsStandalone ? 'token' : 'session');
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
