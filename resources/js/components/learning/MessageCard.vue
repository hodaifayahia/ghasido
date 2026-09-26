<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Mail, MailOpen } from '@lucide/vue';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { intlLocale } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { read } from '@/routes/learn/messages';
import type { LearnerMessage } from '@/types';

/*
 * One in-app reminder (REM-01, REM-04): subject, date, body, and "Mark as
 * read", which stamps `read_at` and counts the topbar bell down.
 */
type Props = {
    message: LearnerMessage;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const sent = computed(() =>
    props.message.sentAt
        ? new Date(props.message.sentAt).toLocaleDateString(intlLocale(), {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          })
        : '',
);
</script>

<template>
    <article
        :class="
            cn(
                'border-line bg-surface shadow-card flex min-w-0 gap-4 rounded-lg border p-5',
                !message.read && 'border-brand-200',
                props.class,
            )
        "
    >
        <span
            :class="
                cn(
                    'grid size-11 shrink-0 place-items-center rounded-xl',
                    message.read
                        ? 'bg-tint-grid text-ink-slate'
                        : 'bg-brand-50 text-brand-600',
                )
            "
        >
            <component
                :is="message.read ? MailOpen : Mail"
                class="size-[22px]"
                aria-hidden="true"
            />
        </span>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                <h2
                    :class="
                        cn(
                            'font-heading text-ink-night text-base leading-6',
                            message.read ? 'font-medium' : 'font-semibold',
                        )
                    "
                >
                    {{ message.subject }}
                    <span
                        v-if="!message.read"
                        class="rounded-pill bg-brand-50 text-brand-700 ms-2 px-2 py-0.5 align-middle text-[11px] font-semibold"
                    >
                        {{ $t('New') }}
                    </span>
                </h2>
                <time
                    class="text-ink-slate text-xs"
                    :datetime="message.sentAt ?? undefined"
                >
                    {{ sent }}
                </time>
            </div>
            <p
                class="text-ink-graphite mt-1 text-sm leading-6 whitespace-pre-line"
            >
                {{ message.body }}
            </p>
            <Form
                v-if="!message.read"
                v-bind="read.form({ reminder: message.id })"
                :options="{ preserveScroll: true }"
                class="mt-3"
                v-slot="{ processing }"
            >
                <button
                    type="submit"
                    :disabled="processing"
                    class="text-brand-600 focus-visible:ring-brand-600/40 inline-flex min-h-11 items-center rounded-md px-2 text-sm font-semibold underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:outline-none disabled:opacity-50"
                    data-test="mark-message-read-button"
                >
                    {{ $t('Mark as read') }}
                </button>
            </Form>
        </div>
    </article>
</template>
