<script setup lang="ts">
import { Languages } from '@lucide/vue';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { useI18n } from '@/composables/useI18n';
import { cn } from '@/lib/utils';

/*
 * The public site's language switch (I18N-02, user request 2026-09-26). It
 * names the other language in that language, so an Arabic reader looking at
 * the English page sees "العربية", and the reverse.
 */
type Props = {
    /** Icon only until the xl breakpoint, where the header has room. */
    compact?: boolean;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const { t, locale, switchLocale } = useI18n();

// From Arabic or an added language (client request 2026-10-03) it goes
// back to English; from English to Arabic.
const other = computed(() =>
    locale.value !== 'en'
        ? { value: 'en', name: 'English', dir: 'ltr' }
        : { value: 'ar', name: 'العربية', dir: 'rtl' },
);
</script>

<template>
    <button
        type="button"
        :class="
            cn(
                'text-brand-700 hover:bg-brand-50 focus-visible:ring-brand-600 inline-flex min-h-11 items-center gap-2 rounded-md px-3 text-[13px] font-semibold transition-colors focus-visible:ring-2 focus-visible:outline-none',
                props.class,
            )
        "
        :aria-label="t('Switch language')"
        data-test="landing-language-toggle"
        @click="switchLocale(other.value)"
    >
        <Languages class="size-4 shrink-0" aria-hidden="true" />
        <span
            :lang="other.value"
            :dir="other.dir"
            :class="props.compact && 'sr-only xl:not-sr-only'"
            >{{ other.name }}</span
        >
    </button>
</template>
