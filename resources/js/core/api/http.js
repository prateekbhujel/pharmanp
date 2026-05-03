import axios from 'axios';

export const apiTokenStorageKey = 'pharmanp.api_token';

export const http = axios.create({
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    withCredentials: true,
});

const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const bootToken = import.meta.env.VITE_PHARMANP_API_TOKEN || localStorage.getItem(apiTokenStorageKey);

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

if (bootToken) {
    setApiToken(bootToken);
}

export function validationErrors(error) {
    return error?.response?.data?.errors || {};
}
