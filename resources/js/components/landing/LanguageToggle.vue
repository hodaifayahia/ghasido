<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChevronDown, Languages } from '@lucide/vue';
import { computed, watchEffect } from 'vue';
import type { HTMLAttributes } from 'vue';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useI18n } from '@/composables/useI18n';
import { registerLocales } from '@/lib/i18n';
import type { LocaleOption } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/*
 * The public site's language switch (I18N-02, user request 2026-09-26).
 * With English and Arabic only, one tap names and opens the other language
 * in its own script. Once the Super Admin adds languages in Settings
 * (client request 2026-10-03) it opens a menu of all of them, as the app's
 * top bar does.
 */
type Props = {
    /** Icon only until the xl breakpoint, where the header has room. */
    compact?: boolean;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const { t, locale, switchLocale } = useI18n();
const page = usePage();

const languages = computed(
    (): LocaleOption[] => page.props.locale?.available ?? [],
);

watchEffect(() => registerLocales(languages.value));

const many = computed(() => languages.value.length > 2);

const other = computed(() =>
    locale.value !== 'en'
        ? { value: 'en', name: 'English', dir: 'ltr' }
        : { value: 'ar', name: 'العربية', dir: 'rtl' },
);

const currentName = computed(
    () =>
        languages.value.find((language) => language.code === locale.value)
            ?.native ?? 'English',
);

const triggerClass = computed(() =>
    cn(
        'text-brand-700 hover:bg-brand-50 focus-visible:ring-brand-600 inline-flex min-h-11 items-center gap-2 rounded-md px-3 text-[13px] font-semibold transition-colors focus-visible:ring-2 focus-visible:outline-none',
        props.class,
    ),
);
</script>

<template>
    <DropdownMenu v-if="many">
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                :class="triggerClass"
                :aria-label="t('Language')"
                data-test="landing-language-toggle"
            >
                <Languages class="size-4 shrink-0" aria-hidden="true" />
                <span :class="props.compact && 'sr-only xl:not-sr-only'">{{
                    currentName
                }}</span>
                <ChevronDown class="size-3.5 shrink-0" aria-hidden="true" />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" :side-offset="6" class="min-w-44">
            <DropdownMenuCheckboxItem
                v-for="language in languages"
                :key="language.code"
                :model-value="locale === language.code"
                :data-test="`landing-language-${language.code}`"
                @select="switchLocale(language.code)"
            >
                <span class="flex min-w-0 items-center gap-2">
                    <img
                        v-if="language.flag"
                        :src="`/flags/${language.flag}.svg`"
                        alt=""
                        aria-hidden="true"
                        class="h-3.5 w-5 shrink-0 rounded-[2px] object-cover"
                    />
                    <span :lang="language.code" :dir="language.dir">{{
                        language.native
                    }}</span>
                </span>
            </DropdownMenuCheckboxItem>
        </DropdownMenuContent>
    </DropdownMenu>

    <button
        v-else
        type="button"
        :class="triggerClass"
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
