import axios from 'axios';
import * as bootstrap from 'bootstrap';

window.axios = axios;
window.bootstrap = bootstrap;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;

// Axios interceptor to handle CSRF token expiration (419 errors)
let isRefreshing = false;
let failedQueue = [];

const processQueue = (error) => {
    failedQueue.forEach(prom => {
        if (error) {
            prom.reject(error);
        } else {
            prom.resolve();
        }
    });
    failedQueue = [];
};

window.axios.interceptors.response.use(
    response => response,
    async error => {
        const originalRequest = error.config;

        // If 419 CSRF token mismatch and not already retrying
        if (error.response?.status === 419 && !originalRequest._retry) {
            if (isRefreshing) {
                // Queue this request while CSRF is being refreshed
                return new Promise((resolve, reject) => {
                    failedQueue.push({ resolve, reject });
                }).then(() => axios(originalRequest));
            }

            originalRequest._retry = true;
            isRefreshing = true;

            try {
                await axios.get('/sanctum/csrf-cookie');
                processQueue(null);
                return axios(originalRequest);
            } catch (refreshError) {
                processQueue(refreshError);
                return Promise.reject(refreshError);
            } finally {
                isRefreshing = false;
            }
        }

        return Promise.reject(error);
    }
);
