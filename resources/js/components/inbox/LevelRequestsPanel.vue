<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ArrowRight, Check, GraduationCap, X } from '@lucide/vue';
import { reactive, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { intlLocale, tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { decide } from '@/routes/inbox/level-requests';
import type { LevelRequestRow } from '@/types';

/*
 * Learners' requests to change level before their Post-test (client
 * request 2026-10-04): who, from which level to which, and why. Approving
 * changes the level at once; either answer reaches the learner's
 * notifications, with the optional note.
 */
type Props = { requests: LevelRequestRow[] };

defineProps<Props>();

const notes = reactive<Record<number, string>>({});
const busy = ref<number | null>(null);

function answer(row: LevelRequestRow, decision: 'approve' | 'reject'): void {
    busy.value = row.id;
    router.post(
        decide.url(row.id),
        { decision, note: notes[row.id] ?? '' },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => (busy.value = null),
        },
    );
}

function when(value: string): string {
    return new Intl.DateTimeFormat(intlLocale(), {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

const pill: Record<LevelRequestRow['status'], string> = {
    pending: 'bg-warning-tint text-warning-text',
    approved: 'bg-success-tint text-success-text',
    rejected: 'bg-danger-tint text-danger-text',
};
const pillLabel: Record<LevelRequestRow['status'], string> = {
    pending: tk('Waiting'),
    approved: tk('Approved'),
    rejected: tk('Refused'),
};
</script>

<template>
    <div class="grid min-w-0 gap-3" data-test="level-requests-panel">
        <div
            v-if="requests.length === 0"
            class="border-line bg-surface shadow-card grid place-items-center gap-2 rounded-lg border p-8 text-center"
        >
            <GraduationCap class="text-brand-300 size-8" aria-hidden="true" />
            <p class="text-ink-slate text-[13px]">
                {{ $t('No level change requests yet.') }}
            </p>
        </div>

        <article
            v-for="row in requests"
            :key="row.id"
            class="border-line bg-surface shadow-card grid min-w-0 gap-3 rounded-lg border p-4"
            :data-test="`level-request-${row.id}`"
        >
            <header class="flex min-w-0 flex-wrap items-start gap-x-3 gap-y-1">
                <div class="min-w-0 flex-1">
                    <p class="text-brand-900 text-[14px] font-semibold">
                        {{ row.name }}
                        <span
                            v-if="row.username"
                            class="text-ink-slate text-[12px] font-medium"
                            dir="ltr"
                            >@{{ row.username }}</span
                        >
                    </p>
                    <p class="text-ink-slate text-[12px]">
                        <span v-if="row.hotel">{{ row.hotel }} · </span
                        >{{ when(row.sentAt) }}
                    </p>
                </div>
                <span
                    :class="
                        cn(
                            'rounded-pill px-2.5 py-1 text-[11px] font-semibold',
                            pill[row.status],
                        )
                    "
                >
                    {{ $t(pillLabel[row.status]) }}
                </span>
            </header>

            <p
                class="text-ink flex flex-wrap items-center gap-2 text-[13px] font-semibold"
            >
                <span class="rounded-pill bg-app px-2.5 py-1">{{
                    row.from ?? '—'
                }}</span>
                <ArrowRight
                    class="text-brand-600 size-4 rtl:rotate-180"
                    aria-hidden="true"
                />
                <span
                    class="rounded-pill bg-brand-50 text-brand-700 px-2.5 py-1"
                    >{{ row.to }}</span
                >
            </p>

            <p
                class="border-line bg-app text-ink rounded-md border p-3 text-[13.5px] leading-[1.7] break-words whitespace-pre-line"
                dir="auto"
            >
                {{ row.reason }}
            </p>

            <template v-if="row.status === 'pending'">
                <label class="grid min-w-0 gap-1">
                    <span class="text-brand-900 text-[12px] font-semibold">{{
                        $t('Note to the learner (optional)')
                    }}</span>
                    <input
                        v-model="notes[row.id]"
                        maxlength="500"
                        dir="auto"
                        class="border-line bg-surface text-ink focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 w-full rounded-md border px-3 text-[13px] focus-visible:ring-3 focus-visible:outline-none"
                    />
                </label>
                <div class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        class="min-h-10"
                        :disabled="busy === row.id"
                        :data-test="`level-request-${row.id}-approve`"
                        @click="answer(row, 'approve')"
                    >
                        <Check class="size-4" aria-hidden="true" />
                        {{ $t('Approve') }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="border-danger text-danger-text hover:bg-danger-tint min-h-10"
                        :disabled="busy === row.id"
                        :data-test="`level-request-${row.id}-reject`"
                        @click="answer(row, 'reject')"
                    >
                        <X class="size-4" aria-hidden="true" />
                        {{ $t('Refuse') }}
                    </Button>
                </div>
            </template>
            <p v-else-if="row.note" class="text-ink-slate text-[12.5px]">
                {{ row.note }}
            </p>
        </article>
    </div>
</template>
