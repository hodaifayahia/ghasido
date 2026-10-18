<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Sprout } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import MeaningText from '@/components/learning/meaning/MeaningText.vue';
import { cn } from '@/lib/utils';
import type { EssentialsHelpLink } from '@/types';

/*
 * "Want to learn spelling first? Tap here" (client request 2026-10-18): a
 * department lesson step's link into the English Essentials lesson the
 * admin chose. The server remembers this step, so the learner comes back
 * to it when they finish or tap "Back to your lesson".
 */
type Props = {
    help: EssentialsHelpLink;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
</script>

<template>
    <aside
        :class="
            cn(
                'border-line bg-brand-50/60 flex flex-col gap-3 rounded-lg border p-4 md:flex-row md:items-center',
                props.class,
            )
        "
        :aria-label="$t('English Essentials')"
    >
        <div class="flex min-w-0 flex-1 items-start gap-3">
            <span
                class="bg-surface text-success grid size-10 shrink-0 place-items-center rounded-xl"
                aria-hidden="true"
            >
                <Sprout class="size-5" />
            </span>
            <div class="min-w-0">
                <MeaningText
                    :text="help.prompt"
                    class="text-ink text-[15px] leading-6 font-semibold"
                />
                <p class="text-ink-slate text-sm">
                    {{ $t('English Essentials') }} · {{ help.title }}
                </p>
            </div>
        </div>
        <Link
            :href="help.url"
            class="bg-brand-600 hover:bg-brand-700 shadow-btn focus-visible:ring-brand-600/30 font-heading flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-md px-4 text-sm font-semibold text-white focus-visible:ring-3 focus-visible:outline-none active:scale-[.97]"
            data-test="open-essentials-help-button"
        >
            {{ $t('Learn it now') }}
            <ArrowRight class="size-4 rtl:rotate-180" aria-hidden="true" />
        </Link>
    </aside>
</template>
