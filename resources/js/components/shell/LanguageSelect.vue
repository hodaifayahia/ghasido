<script setup lang="ts">
// LOCKED geometry from md up (below md it shrinks to a 36px chip at the
// user's request, 2026-09-30): the admin topbar's language box (111×46 at x 1147 / y 15
// on the approved Admin Dashboard mockup, AGENTS.md §0.4), extracted here so
// the lesson top bar reuses the same control (spec 0003 H.1).
import { ChevronDown, Globe } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useI18n } from '@/composables/useI18n';
import type { Locale } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type Props = {
    /**
     * The lesson top bar's smaller box: 100×40 at x 1162 / y 21 on
     * desginphotos/employ/photo_1 (globe 20px, "EN" 15px).
     */
    compact?: boolean;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

// The interface language, English or Arabic (I18N-02). Each language names
// itself, so a reader of either can always find their own.
const { t, locale, switchLocale } = useI18n();

const languages: { value: Locale; code: string; name: string }[] = [
    { value: 'en', code: 'EN', name: 'English' },
    { value: 'ar', code: 'AR', name: 'العربية' },
];

function choose(value: Locale): void {
    void switchLocale(value);
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                :aria-label="t('Language')"
                data-test="language-select"
                :class="
                    cn(
                        'border-line bg-surface shadow-card ease-brand hover:bg-brand-50 focus-visible:border-brand-600 focus-visible:ring-brand-600/15 data-[state=open]:bg-brand-50 ms-1 flex h-9 shrink-0 items-center gap-0.5 rounded-md border ps-2 pe-1 transition-colors duration-150 focus-visible:ring-3 focus-visible:outline-none md:ms-6 md:h-[46px] md:w-[111px] md:gap-0 md:ps-[17px] md:pe-[11px]',
                        props.compact &&
                            'md:h-10 md:w-[100px] md:ps-[15px] md:pe-3',
                        props.class,
                    )
                "
            >
                <Globe
                    :class="
                        cn(
                            'text-brand-700 size-4 shrink-0 md:size-[22px]',
                            props.compact && 'md:size-5',
                        )
                    "
                    aria-hidden="true"
                />
                <span
                    :class="
                        cn(
                            'text-ink-indigo ms-1 text-[13px] leading-none font-semibold md:ms-2 md:text-base',
                            props.compact && 'md:ms-[7px] md:text-[15px]',
                        )
                    "
                >
                    {{ locale === 'ar' ? 'AR' : 'EN' }}
                </span>
                <ChevronDown
                    :class="
                        cn(
                            'text-ink-slate ms-auto size-4 shrink-0 stroke-[2.25] md:size-6',
                            props.compact && 'md:size-5',
                        )
                    "
                    aria-hidden="true"
                />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="end"
            :side-offset="8"
            class="min-w-44 rounded-md"
        >
            <DropdownMenuLabel class="text-ink-muted text-xs">
                {{ t('Language') }}
            </DropdownMenuLabel>
            <DropdownMenuCheckboxItem
                v-for="language in languages"
                :key="language.value"
                :model-value="locale === language.value"
                :data-test="`language-${language.value}`"
                @select="choose(language.value)"
            >
                <span
                    :lang="language.value"
                    :dir="language.value === 'ar' ? 'rtl' : 'ltr'"
                    >{{ language.name }}</span
                >
            </DropdownMenuCheckboxItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
