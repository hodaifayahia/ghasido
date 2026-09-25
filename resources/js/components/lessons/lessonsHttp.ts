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

async function toError(response: Response): Promise<JsonRequestError> {
    const errors: ValidationErrors = {};

    try {
        const body = (await response.json()) as LaravelErrorBody;

        for (const [key, value] of Object.entries(body.errors ?? {})) {
            errors[key] = Array.isArray(value) ? (value[0] ?? '') : value;
        }

        if (Object.keys(errors).length === 0 && body.message) {
            errors._ = body.message;
        }
    } catch {
        errors._ = 'The request failed. Please try again.';
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
