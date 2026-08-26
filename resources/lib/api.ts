import { __ } from '@wordpress/i18n';
import axios, { AxiosError } from 'axios';

export const api = axios.create({
    baseURL: window.jooosiFon.rest_api.url,
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-WP-Nonce': window.jooosiFon.rest_api.nonce,
    },
});

export function getErrorMessage(error: unknown) {
    if (error instanceof AxiosError) {
        const message = error.response?.data?.message;
        if (typeof message === 'string' && message.length > 0) {
            return message;
        }

        return error.message;
    }

    return error instanceof Error
        ? error.message
        : __('An unexpected error occurred.', 'jooosi-fon');
}
