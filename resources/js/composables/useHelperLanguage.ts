import { usePage } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';

/*
 * The learner's Show Meaning language (client request 2026-09-30): Arabic,
 * French or any language the Super Admin adds. Every meaning panel takes
 * its `lang`, direction and font from here, so an Arabic meaning is set in
 * Cairo right to left and a French one left to right in the body font.
 */
export type UseHelperLanguageReturn = {
    code: ComputedRef<string>;
    name: ComputedRef<string>;
    dir: ComputedRef<'ltr' | 'rtl'>;
    isArabic: ComputedRef<boolean>;
    /** The font and line height for a meaning in this language. */
    fontClass: ComputedRef<string>;
};

export function useHelperLanguage(): UseHelperLanguageReturn {
    const page = usePage();
    const language = computed(() => page.props.helperLanguage ?? null);

    const code = computed(() => language.value?.code ?? 'ar');
    const isArabic = computed(() => code.value === 'ar');

    return {
        code,
        name: computed(() => language.value?.name ?? 'Arabic'),
        dir: computed(() =>
            (language.value?.dir ?? 'rtl') === 'rtl' ? 'rtl' : 'ltr',
        ),
        isArabic,
        fontClass: computed(() =>
            isArabic.value ? 'font-arabic leading-[1.9]' : 'leading-relaxed',
        ),
    };
}
