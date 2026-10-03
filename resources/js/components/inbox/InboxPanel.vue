<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ArrowLeft, Inbox, Mail, Phone, Send } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { intlLocale, tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { reply as replyRoute } from '@/routes/inbox';
import type { InboxMessage } from '@/types';

/*
 * The list of messages beside the open one, its whole text, the answers
 * already sent and the reply box (client request 2026-10-03). On a phone
 * the list and the open message take turns.
 */
type Props = {
    messages: InboxMessage[];
    selectedId: number | null;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    open: [id: number | null];
}>();

const selected = computed(
    () => props.messages.find((item) => item.id === props.selectedId) ?? null,
);

const topicLabels: Record<string, string> = {
    problem: tk('A problem with the platform'),
    extension: tk('Extend or change our plan'),
    question: tk('A question'),
    other: tk('Something else'),
};

function when(value: string): string {
    return value === ''
        ? ''
        : new Intl.DateTimeFormat(intlLocale(), {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value));
}

const body = ref('');
const sending = ref(false);
const errors = ref<Record<string, string>>({});

watch(
    () => props.selectedId,
    () => {
        body.value = '';
        errors.value = {};
    },
);

function send(): void {
    if (selected.value === null) {
        return;
    }

    sending.value = true;
    router.post(
        replyRoute.url(selected.value.id),
        { body: body.value },
        {
            preserveScroll: true,
            preserveState: true,
            onError: (bag) => {
                errors.value = bag;
            },
            onSuccess: () => {
                errors.value = {};
                body.value = '';
            },
            onFinish: () => {
                sending.value = false;
            },
        },
    );
}
</script>

<template>
    <div
        class="border-line bg-surface shadow-card grid min-h-[420px] min-w-0 overflow-hidden rounded-lg border md:grid-cols-[320px_minmax(0,1fr)]"
        data-test="inbox-panel"
    >
        <ul
            :class="
                cn(
                    'border-line min-w-0 overflow-y-auto md:max-h-[640px] md:border-e',
                    selected !== null && 'hidden md:block',
                )
            "
        >
            <li
                v-if="messages.length === 0"
                class="grid place-items-center gap-2 p-8 text-center"
            >
                <Inbox class="text-brand-300 size-8" aria-hidden="true" />
                <p class="text-ink-slate text-[13px]">
                    {{ $t('No messages yet.') }}
                </p>
            </li>
            <li v-for="item in messages" :key="item.id">
                <button
                    type="button"
                    :class="
                        cn(
                            'border-line hover:bg-brand-50 focus-visible:bg-brand-50 grid w-full min-w-0 gap-0.5 border-b px-4 py-3 text-start focus-visible:outline-none',
                            item.id === selectedId && 'bg-brand-50',
                        )
                    "
                    :data-test="`inbox-message-${item.id}`"
                    @click="emit('open', item.id)"
                >
                    <span class="flex min-w-0 items-center gap-2">
                        <span
                            v-if="!item.read"
                            class="bg-brand-600 size-2 shrink-0 rounded-full"
                            :aria-label="$t('New')"
                        />
                        <span
                            :class="
                                cn(
                                    'text-ink-indigo min-w-0 flex-1 truncate text-[13px]',
                                    item.read ? 'font-medium' : 'font-semibold',
                                )
                            "
                        >
                            {{ item.name }}
                        </span>
                        <time
                            class="text-ink-slate shrink-0 text-[11px]"
                            :datetime="item.sentAt"
                        >
                            {{ when(item.sentAt) }}
                        </time>
                    </span>
                    <span
                        v-if="item.organisation"
                        class="text-ink-slate truncate text-[11.5px]"
                    >
                        {{ item.organisation }}
                    </span>
                    <span
                        class="text-ink-slate line-clamp-2 text-[12px] leading-5"
                        dir="auto"
                    >
                        {{ item.message }}
                    </span>
                    <span
                        v-if="item.replies.length > 0"
                        class="text-success-text text-[11px] font-semibold"
                    >
                        {{ $t('Answered') }}
                    </span>
                </button>
            </li>
        </ul>

        <section
            v-if="selected !== null"
            class="grid min-w-0 content-start gap-4 p-4 md:p-5"
            data-test="inbox-thread"
        >
            <button
                type="button"
                class="text-brand-700 inline-flex min-h-11 w-fit items-center gap-1.5 text-[13px] font-semibold md:hidden"
                @click="emit('open', null)"
            >
                <ArrowLeft class="size-4 rtl:rotate-180" aria-hidden="true" />
                {{ $t('All messages') }}
            </button>

            <header class="grid min-w-0 gap-1">
                <h2
                    class="font-heading text-brand-900 text-[17px] font-semibold break-words"
                >
                    {{ selected.name }}
                    <span
                        v-if="selected.organisation"
                        class="text-ink-slate text-[13px] font-medium"
                    >
                        · {{ selected.organisation }}
                    </span>
                </h2>
                <div
                    class="text-ink-slate flex flex-wrap items-center gap-x-4 gap-y-1 text-[12.5px]"
                >
                    <a
                        v-if="selected.email.includes('@')"
                        :href="`mailto:${selected.email}`"
                        class="hover:text-brand-700 inline-flex items-center gap-1"
                        dir="ltr"
                    >
                        <Mail class="size-3.5" aria-hidden="true" />
                        {{ selected.email }}
                    </a>
                    <span v-else-if="selected.email" dir="ltr">
                        {{ selected.email }}
                    </span>
                    <a
                        v-if="selected.phone"
                        :href="`tel:${selected.phone}`"
                        class="hover:text-brand-700 inline-flex items-center gap-1"
                        dir="ltr"
                    >
                        <Phone class="size-3.5" aria-hidden="true" />
                        {{ selected.phone }}
                    </a>
                    <span>{{ when(selected.sentAt) }}</span>
                </div>
                <p class="flex flex-wrap gap-1.5 pt-1">
                    <span
                        class="rounded-pill bg-brand-50 text-brand-700 px-2.5 py-1 text-[11px] font-semibold"
                    >
                        {{
                            selected.fromAccount
                                ? $t('From the platform')
                                : $t('From the website contact form')
                        }}
                    </span>
                    <span
                        v-if="selected.topic && topicLabels[selected.topic]"
                        class="rounded-pill bg-warning-tint text-warning-text px-2.5 py-1 text-[11px] font-semibold"
                    >
                        {{ $t(topicLabels[selected.topic]!) }}
                    </span>
                </p>
            </header>

            <p
                class="border-line bg-app text-ink rounded-md border p-3 text-[14px] leading-[1.7] break-words whitespace-pre-line"
                dir="auto"
            >
                {{ selected.message }}
            </p>

            <div
                v-for="answer in selected.replies"
                :key="answer.id"
                class="bg-brand-50 border-brand-100 ms-6 grid gap-1 rounded-md border p-3"
            >
                <p class="text-brand-700 text-[12px] font-semibold">
                    {{ answer.sender }} · {{ when(answer.sentAt) }}
                </p>
                <p
                    class="text-ink text-[13.5px] leading-[1.7] break-words whitespace-pre-line"
                    dir="auto"
                >
                    {{ answer.body }}
                </p>
            </div>

            <form class="grid min-w-0 gap-2" @submit.prevent="send">
                <label class="grid min-w-0 gap-1.5">
                    <span class="text-brand-900 text-[12px] font-semibold">
                        {{ $t('Your reply') }}
                    </span>
                    <textarea
                        v-model="body"
                        rows="4"
                        maxlength="5000"
                        required
                        dir="auto"
                        data-test="inbox-reply-body"
                        class="border-line bg-surface text-ink focus-visible:border-brand-600 focus-visible:ring-brand-600/15 min-h-28 w-full resize-y rounded-md border px-3 py-2 text-[13px] leading-[1.6] focus-visible:ring-3 focus-visible:outline-none"
                    />
                    <InputError :message="errors.body" />
                </label>
                <p class="text-ink-slate text-[12px]">
                    {{
                        selected.fromAccount
                            ? $t(
                                  'They get it in their notifications and by email when they have one.',
                              )
                            : $t('They get it by email.')
                    }}
                </p>
                <Button
                    type="submit"
                    class="min-h-11 w-fit"
                    :disabled="sending || body.trim() === ''"
                    data-test="inbox-reply-send"
                >
                    <Send class="size-4" aria-hidden="true" />
                    {{ sending ? $t('Sending…') : $t('Send reply') }}
                </Button>
            </form>
        </section>
        <section
            v-else
            class="text-ink-slate hidden place-items-center p-8 text-center text-[13px] md:grid"
        >
            {{ $t('Choose a message to read it and reply.') }}
        </section>
    </div>
</template>
