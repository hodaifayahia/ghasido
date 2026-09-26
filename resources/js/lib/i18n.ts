import { router } from '@inertiajs/vue3';
import { update as updateLocale } from '@/routes/locale';
import { computed, ref } from 'vue';
import type { App, ComputedRef } from 'vue';

/**
 * The interface language (I18N-02, user request 2026-09-26): English, the
 * default, or Arabic, laid out right to left.
 *
 * Keys are the English strings themselves, the same convention as Laravel's
 * `__()`, and both sides read one dictionary: `lang/ar.json`. It is loaded
 * lazily, only when Arabic is chosen, so English pages never download it.
 * A key with no Arabic entry falls back to its English text; the
 * `TranslationsCoverageTest` keeps that from shipping.
 *
 * Learning content is not translated here: lessons stay English and their
 * Arabic lives behind Show Meaning (I18N-01, CTRL-01).
 */

export type Locale = 'en' | 'ar';

export type Replacements = Record<string, string | number>;

type Messages = Record<string, string>;

const loaders: Record<Locale, () => Promise<Messages>> = {
    en: () => Promise.resolve({}),
    ar: async () => (await import('../../../lang/ar.json')).default as Messages,
};

const messages = ref<Messages>({});
const current = ref<Locale>('en');

export function isLocale(value: unknown): value is Locale {
    return value === 'en' || value === 'ar';
}

export function directionOf(locale: Locale): 'ltr' | 'rtl' {
    return locale === 'ar' ? 'rtl' : 'ltr';
}

function replace(text: string, replacements?: Replacements): string {
    if (!replacements) {
        return text;
    }

    // Longest names first, so `:count` never eats part of `:countries`.
    return Object.keys(replacements)
        .sort((a, b) => b.length - a.length)
        .reduce(
            (result, name) =>
                result.replaceAll(`:${name}`, String(replacements[name])),
            text,
        );
}

/** The text for `key` in the current language, English when missing. */
export function t(key: string, replacements?: Replacements): string {
    const translated = messages.value[key];

    return replace(
        translated !== undefined && translated !== '' ? translated : key,
        replacements,
    );
}

/**
 * The `Intl` locale for dates in the interface language: Algerian Arabic
 * keeps Latin digits and writes month and day names in Arabic. Numbers and
 * prices keep their own formats.
 */
export function intlLocale(): string {
    return current.value === 'ar' ? 'ar-DZ' : 'en-GB';
}

/**
 * Marks an English key for translation without translating it yet, for
 * labels defined once in a constant and translated where they render
 * (`$t(item.label)`). It returns the key unchanged; its only job is to let
 * the key extractor and `TranslationsCoverageTest` find the string.
 */
export function tk<T extends string>(key: T): T {
    return key;
}

/**
 * Pick one form of a `|` separated key by `count`, like Laravel's
 * `trans_choice()`. Forms may carry an explicit range: `{0}`, `[2,10]` or
 * `[11,*]`. Without ranges the first form is singular, the last plural.
 */
export function tc(
    key: string,
    count: number,
    replacements?: Replacements,
): string {
    const forms = t(key).split('|');
    const ranged = /^\s*(\{(\d+)\}|\[(\d+),(\d+|\*)\])\s*/;
    let chosen: string | undefined;

    for (const form of forms) {
        const match = ranged.exec(form);

        if (!match) {
            continue;
        }

        const exact = match[2];
        const from = Number(match[3]);
        const to = match[4] === '*' ? Infinity : Number(match[4]);

        if (
            (exact !== undefined && Number(exact) === count) ||
            (exact === undefined && count >= from && count <= to)
        ) {
            chosen = form.slice(match[0].length);
            break;
        }
    }

    if (chosen === undefined) {
        const plain = forms.map((form) => form.replace(ranged, ''));
        chosen =
            count === 1 || plain.length === 1
                ? plain[0]
                : plain[plain.length - 1];
    }

    return replace(chosen ?? key, { count, ...replacements });
}

function applyToDocument(locale: Locale): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.lang = locale;
    document.documentElement.dir = directionOf(locale);
}

/** Load a language's strings and apply its direction to the page. */
export async function setLocale(locale: Locale): Promise<void> {
    messages.value = await loaders[locale]();
    current.value = locale;
    applyToDocument(locale);
}

/**
 * Switch the interface language: the strings and the direction change at
 * once, and the server saves the choice (cookie, and the account when signed
 * in) and answers with the page's props in that language.
 */
export async function switchLocale(locale: Locale): Promise<void> {
    if (locale === current.value) {
        return;
    }

    await setLocale(locale);

    router.put(
        updateLocale().url,
        { locale },
        { preserveScroll: true, preserveState: true },
    );
}

/**
 * Before the first render: the server wrote the chosen language on `<html>`
 * (SetLocale middleware), so the first paint is already in that language.
 * Afterwards, every page response carries `locale`, which keeps a switch
 * made in another tab or on another device in step.
 */
export async function initializeI18n(): Promise<void> {
    const initial =
        typeof document === 'undefined' ? 'en' : document.documentElement.lang;

    await setLocale(isLocale(initial) ? initial : 'en');

    router.on('success', (event) => {
        const locale = (
            event.detail.page.props.locale as { current?: unknown } | undefined
        )?.current;

        if (isLocale(locale) && locale !== current.value) {
            void setLocale(locale);
        }
    });
}

/** Makes `$t` and `$tc` available in every template. */
export function installI18n(app: App): void {
    app.config.globalProperties.$t = t;
    app.config.globalProperties.$tc = tc;
}

export type UseI18nReturn = {
    t: typeof t;
    tc: typeof tc;
    switchLocale: typeof switchLocale;
    locale: ComputedRef<Locale>;
    isRtl: ComputedRef<boolean>;
};

export function useI18n(): UseI18nReturn {
    return {
        t,
        tc,
        switchLocale,
        locale: computed(() => current.value),
        isRtl: computed(() => current.value === 'ar'),
    };
}
