import axios from 'axios';

export const http = axios.create({
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    withCredentials: true,
});

const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

if (csrf) {
    http.defaults.headers.common['X-CSRF-TOKEN'] = csrf;
}

export function validationErrors(error) {
    return error?.response?.data?.errors || {};
}
