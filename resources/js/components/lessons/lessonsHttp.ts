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

/** The size of one piece of a chunked upload: below even a 2 MB PHP upload limit. */
export const CHUNK_BYTES = 1.5 * 1024 * 1024;

/**
 * Raised when the browser got no answer at all: the connection was cut
 * (by the hosting, a proxy or the network) before the server replied.
 */
export function connectionCutError(): JsonRequestError {
    return new JsonRequestError(0, {
        _: t(
            'The connection to the server was cut during the upload. Check the internet connection and try again.',
        ),
    });
}

/**
 * Send a large file in pieces of CHUNK_BYTES, one after the other, each
 * retried up to three times (client report 2026-11-12: a whole video in
 * one request was cut by the hosting). `fields` go with every piece; the
 * server joins the pieces and answers the last one like a normal upload.
 */
export async function postInChunks<T>(
    url: string,
    file: File,
    fields: Record<string, string>,
    onProgress?: (fraction: number) => void,
): Promise<T> {
    const total = Math.max(1, Math.ceil(file.size / CHUNK_BYTES));
    const uploadId =
        typeof crypto !== 'undefined' && 'randomUUID' in crypto
            ? crypto.randomUUID()
            : `${Date.now().toString(16).padStart(8, '0')}-0000-4000-8000-${Math.random().toString(16).slice(2, 14).padEnd(12, '0')}`;
    let last: T | null = null;

    for (let index = 0; index < total; index++) {
        const body = new FormData();

        for (const [key, value] of Object.entries(fields)) {
            body.set(key, value);
        }

        body.set('upload_id', uploadId);
        body.set('index', String(index));
        body.set('total', String(total));
        body.set('name', file.name);
        body.set(
            'chunk',
            file.slice(index * CHUNK_BYTES, (index + 1) * CHUNK_BYTES),
            `${index}.part`,
        );

        last = await withRetries(() => postJson<T>(url, body));
        onProgress?.((index + 1) / total);
    }

    return last as T;
}

async function withRetries<T>(send: () => Promise<T>): Promise<T> {
    for (let attempt = 1; ; attempt++) {
        try {
            return await send();
        } catch (error) {
            // A validation answer will not change on a retry.
            if (error instanceof JsonRequestError) {
                throw error;
            }

            if (attempt >= 3) {
                throw connectionCutError();
            }

            await new Promise((resolve) => setTimeout(resolve, attempt * 1500));
        }
    }
}
