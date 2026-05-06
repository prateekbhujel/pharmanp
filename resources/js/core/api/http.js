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
    const rawErrors = error?.response?.data?.errors || {};
    const normalized = {};

    const append = (field, messages) => {
        const cleanField = normalizeErrorField(field);
        const cleanMessages = normalizeErrorMessages(messages);

        if (!normalized[cleanField]) {
            normalized[cleanField] = [];
        }

        normalized[cleanField].push(...cleanMessages);
    };

    if (Array.isArray(rawErrors)) {
        rawErrors.forEach((entry, index) => {
            if (entry && typeof entry === 'object') {
                append(entry.field || entry.name || entry.key || `form_${index + 1}`, entry.messages || entry.errors || entry.message || entry);
                return;
            }

            append('form', entry);
        });

        return normalized;
    }

    if (!rawErrors || typeof rawErrors !== 'object') {
        return {};
    }

    Object.entries(rawErrors).forEach(([field, messages]) => append(field, messages));

    return normalized;
}

export function formErrors(error) {
    return Object.entries(validationErrors(error)).map(([name, errors]) => ({
        name: name.includes('.') ? name.split('.') : name,
        errors,
    }));
}

export function firstValidationMessage(error) {
    const errors = validationErrors(error);
    const first = Object.values(errors).flat()[0];

    return first || null;
}

export function apiErrorMessage(error, fallback = 'Request failed') {
    return error?.response?.data?.message
        || firstValidationMessage(error)
        || error?.message
        || fallback;
}

export function apiSuccessMessage(response, fallback = 'Saved successfully') {
    return response?.data?.message || fallback;
}

function normalizeErrorField(field) {
    const value = String(field || 'form')
        .replace(/^errors\.?\d+\.?/i, '')
        .replace(/^\.+/, '');

    return value || 'form';
}

function normalizeErrorMessages(messages) {
    if (Array.isArray(messages)) {
        return messages.flatMap((message) => normalizeErrorMessages(message));
    }

    if (messages && typeof messages === 'object') {
        return Object.values(messages).flatMap((message) => normalizeErrorMessages(message));
    }

    return [String(messages || 'The submitted value is invalid.')];
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
