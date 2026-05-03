import axios from 'axios';
import { apiTokenStorageKey } from './core/api/http';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.headers.common.Accept = 'application/json';
window.axios.defaults.withCredentials = true;

const token = document.querySelector('meta[name="csrf-token"]')?.content;
const apiToken = import.meta.env.VITE_PHARMANP_API_TOKEN || localStorage.getItem(apiTokenStorageKey);

if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
}

if (apiToken) {
    window.axios.defaults.headers.common.Authorization = `Bearer ${apiToken}`;
}
