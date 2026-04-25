import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.headers.common.Accept = 'application/json';
window.axios.defaults.withCredentials = true;

const token = document.querySelector('meta[name="csrf-token"]')?.content;

if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
}
