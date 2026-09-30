<script setup lang="ts">
import { CircleAlert, CircleCheck, Lock, ShieldCheck } from '@lucide/vue';
import { Spinner } from '@/components/ui/spinner';

/*
 * "Review & submit": what is about to be sent, the approval note, and the
 * one submit button. While the receipt uploads the button shows progress
 * and stays disabled, so a second tap cannot send the request twice.
 */
export type ReviewRow = {
    label: string;
    value: string;
    /** Shown with a warning icon: this still needs filling in. */
    missing?: boolean;
};

type Props = {
    rows: ReviewRow[];
    approvalNote: string;
    approvalDescription: string;
    submitLabel: string;
    processing: boolean;
    /** Upload progress, 0–100, while a receipt is being sent. */
    progress: number | null;
    hasErrors: boolean;
};

defineProps<Props>();
</script>

<template>
    <div class="grid gap-5">
        <dl
            class="border-line divide-line bg-app-alt divide-y overflow-hidden rounded-lg border"
        >
            <div
                v-for="row in rows"
                :key="row.label"
                class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 py-3"
            >
                <dt class="text-ink-muted text-[13px]">{{ row.label }}</dt>
                <dd
                    :class="
                        row.missing
                            ? 'text-warning-text'
                            : 'text-ink font-semibold'
                    "
                    class="flex min-w-0 items-center gap-1.5 text-[14px] break-words"
                >
                    <CircleAlert
                        v-if="row.missing"
                        class="size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <CircleCheck
                        v-else
                        class="text-success size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span class="min-w-0 break-all">{{ row.value }}</span>
                </dd>
            </div>
        </dl>

        <div class="border-line bg-surface rounded-lg border p-4">
            <div class="flex items-start gap-3">
                <ShieldCheck
                    class="text-success mt-0.5 size-5 shrink-0"
                    aria-hidden="true"
                />
                <div>
                    <p class="text-brand-900 text-[13px] font-semibold">
                        {{ approvalNote }}
                    </p>
                    <p class="text-ink-slate mt-1 text-[12.5px] leading-5">
                        {{ approvalDescription }}
                    </p>
                </div>
            </div>
        </div>

        <p
            v-if="hasErrors && !processing"
            class="bg-danger-tint text-danger-text flex items-start gap-2 rounded-md px-4 py-3 text-[13px] leading-5"
            role="alert"
        >
            <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            {{
                $t(
                    'Some details need your attention. Check the fields marked in red above.',
                )
            }}
        </p>

        <div class="grid gap-2">
            <button
                type="submit"
                :disabled="processing"
                :aria-busy="processing"
                class="bg-brand-600 text-surface shadow-btn hover:bg-brand-700 focus-visible:ring-brand-600/30 disabled:bg-brand-400 font-heading relative inline-flex h-12 w-full items-center justify-center gap-2 overflow-hidden rounded-md px-5 text-[15px] font-semibold transition-[background-color,transform] duration-100 focus-visible:ring-3 focus-visible:outline-none active:scale-[.98] disabled:cursor-wait motion-reduce:transition-none"
                data-test="checkout-submit-button"
            >
                <span
                    v-if="processing && progress !== null"
                    class="bg-brand-700 absolute inset-y-0 start-0 transition-[width] duration-200 motion-reduce:transition-none"
                    :style="{ width: `${progress}%` }"
                    aria-hidden="true"
                />
                <span class="relative inline-flex items-center gap-2">
                    <Spinner v-if="processing" />
                    <Lock v-else class="size-4" aria-hidden="true" />
                    <template v-if="processing && progress !== null">
                        {{ $t('Uploading… :percent%', { percent: progress }) }}
                    </template>
                    <template v-else-if="processing">
                        {{ $t('Sending…') }}
                    </template>
                    <template v-else>{{ submitLabel }}</template>
                </span>
            </button>
            <p class="text-ink-muted text-center text-[12px] leading-5">
                {{
                    $t(
                        'Nothing is charged here: you pay with your own app or bank, and we confirm it by hand.',
                    )
                }}
            </p>
        </div>
    </div>
</template>
