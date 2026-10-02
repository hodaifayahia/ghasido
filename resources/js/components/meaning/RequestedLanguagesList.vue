<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CircleAlert, Languages, Plus } from '@lucide/vue';
import LanguageFlag from '@/components/meaning/LanguageFlag.vue';
import { edit as learningSettings } from '@/routes/learning-settings';
import type { RequestedHelperLanguage } from '@/types';

/*
 * The helper languages a customer asked for at sign-up (client request
 * 2026-10-01), on the request the Super Admin reviews: each with its flag
 * and the next step. An offered language opens the Translations page in
 * that language, where a lesson is drafted with AI, checked and saved; a
 * language that is not offered yet is added in Settings → Learning first.
 */
type Props = {
    languages: RequestedHelperLanguage[];
};

defineProps<Props>();
</script>

<template>
    <section
        v-if="languages.length > 0"
        class="border-line bg-brand-50/60 grid min-w-0 gap-2.5 rounded-md border p-3"
        data-test="requested-helper-languages"
    >
        <h3
            class="text-brand-900 flex items-center gap-1.5 text-[13px] font-semibold"
        >
            <Languages class="size-4 shrink-0" aria-hidden="true" />
            {{ $t('Helper languages asked for') }}
        </h3>
        <ul class="grid min-w-0 gap-2">
            <li
                v-for="language in languages"
                :key="language.code ?? language.name"
                class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1.5"
            >
                <span class="flex min-w-0 flex-1 items-center gap-2">
                    <LanguageFlag
                        :flag="language.flag"
                        :code="(language.code ?? language.name).slice(0, 2)"
                    />
                    <span
                        class="text-ink min-w-0 truncate text-sm font-semibold"
                    >
                        {{ language.native ?? language.name }}
                        <span
                            v-if="
                                language.native &&
                                language.native !== language.name
                            "
                            class="text-ink-slate font-normal"
                            >({{ $t(language.name) }})</span
                        >
                    </span>
                </span>
                <Link
                    v-if="language.offered && language.translationsUrl"
                    :href="language.translationsUrl"
                    class="text-brand-700 hover:bg-brand-100 focus-visible:ring-brand-600/15 inline-flex min-h-9 items-center gap-1.5 rounded-md px-2 text-[12.5px] font-semibold focus-visible:ring-3 focus-visible:outline-none"
                >
                    <Languages class="size-3.5" aria-hidden="true" />
                    {{ $t('Translate lessons') }}
                </Link>
                <template v-else>
                    <span
                        class="text-warning-text flex items-center gap-1 text-xs font-semibold"
                    >
                        <CircleAlert class="size-3.5" aria-hidden="true" />
                        {{ $t('Not offered yet') }}
                    </span>
                    <Link
                        :href="learningSettings()"
                        class="text-brand-700 hover:bg-brand-100 focus-visible:ring-brand-600/15 inline-flex min-h-9 items-center gap-1.5 rounded-md px-2 text-[12.5px] font-semibold focus-visible:ring-3 focus-visible:outline-none"
                    >
                        <Plus class="size-3.5" aria-hidden="true" />
                        {{ $t('Add the language') }}
                    </Link>
                </template>
            </li>
        </ul>
    </section>
</template>
