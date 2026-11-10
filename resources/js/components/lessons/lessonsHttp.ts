import { t } from '@/lib/i18n';

/**
 * Plain JSON calls for the two CMS surfaces that are not page visits: the
 * image picker's listing and the media upload inside a slot (MED-02). Every
 * URL comes from Wayfinder; the CSRF token is read the way Inertia does.
 */
export type ValidationErrors = Record<string, string>;

export class JsonRequestError extends Error {
    public constructor(
        public readonly status: number,
        public readonly errors: ValidationErrors,
    ) {
        super(`Request failed with status ${status}`);
    }
}

function xsrfToken(): string {
    if (typeof document === 'undefined') {
        return '';
    }

    const match = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='));

    return match === undefined
        ? ''
        : decodeURIComponent(match.slice('XSRF-TOKEN='.length));
}

type LaravelErrorBody = {
    message?: string;
    errors?: Record<string, string[] | string>;
};

function serverFailure(status: number): string {
    if (status === 413) {
        return t(
            'The file is larger than the server accepts. Use a smaller file, or raise the upload limit in the hosting panel (PHP options: upload_max_filesize and post_max_size).',
        );
    }

    if (status === 408 || status === 504) {
        return t(
            'The upload took too long and the server stopped it. Try again on a faster connection, or with a smaller file.',
        );
    }

    if (status >= 500) {
        return t(
            'The server stopped while saving the file (error :status). Try again; if it happens again, send this message to support.',
            { status },
        );
    }

    return t('The request failed. Please try again.');
}

async function toError(response: Response): Promise<JsonRequestError> {
    const errors: ValidationErrors = {};

    try {
        const body = (await response.json()) as LaravelErrorBody;

        for (const [key, value] of Object.entries(body.errors ?? {})) {
            errors[key] = Array.isArray(value) ? (value[0] ?? '') : value;
        }

        if (Object.keys(errors).length === 0) {
            // Laravel's own 500 page says only "Server Error".
            errors._ =
                response.status >= 500 || !body.message
                    ? serverFailure(response.status)
                    : body.message;
        }
    } catch {
        // Not a Laravel answer: the web server or PHP stopped the request
        // before the app could reply (client report 2026-11-10). Say which.
        errors._ = serverFailure(response.status);
    }

    return new JsonRequestError(response.status, errors);
}

export async function getJson<T>(url: string): Promise<T> {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw await toError(response);
    }

    return (await response.json()) as T;
}

export async function postJson<T>(url: string, body: FormData): Promise<T> {
    const response = await fetch(url, {
        method: 'POST',
        body,
        headers: {
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw await toError(response);
    }

    return (await response.json()) as T;
}
