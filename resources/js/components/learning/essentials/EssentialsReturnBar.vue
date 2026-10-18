<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import type { EssentialsReturn } from '@/types';

/*
 * Inside an English Essentials lesson opened from a lesson step (client
 * request 2026-10-18): where the learner came from and the way straight
 * back to that same step.
 */
type Props = {
    returnTo: EssentialsReturn;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
</script>

<template>
    <div
        :class="
            cn(
                'bg-brand-50 flex flex-col gap-2 rounded-md px-4 py-3 md:flex-row md:items-center md:justify-between',
                props.class,
            )
        "
    >
        <p class="text-ink-slate min-w-0 text-sm">
            {{ $t('You came here from :lesson.', { lesson: returnTo.title }) }}
        </p>
        <Link
            :href="returnTo.url"
            class="text-brand-700 border-brand-600 bg-surface hover:bg-brand-50 focus-visible:ring-brand-600/30 inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-md border px-4 text-sm font-semibold focus-visible:ring-3 focus-visible:outline-none"
            data-test="back-to-lesson-button"
        >
            <ArrowLeft class="size-4 rtl:rotate-180" aria-hidden="true" />
            {{ $t('Back to your lesson') }}
        </Link>
    </div>
</template>
