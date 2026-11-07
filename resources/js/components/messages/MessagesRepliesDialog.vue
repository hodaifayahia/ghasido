<script setup lang="ts">
import { ChevronDown, MessageSquareReply } from '@lucide/vue';
import { ref, watch } from 'vue';
import MessagesModal from '@/components/messages/MessagesModal.vue';
import MessagesRatingStars from '@/components/messages/MessagesRatingStars.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { useI18n } from '@/composables/useI18n';
import { cn } from '@/lib/utils';
import type { MessageReplyThread } from '@/types';

/*
 * Replies (client request 2026-11-07): every message sent with "Allow
 * replies", newest first, with who answered, their text as written, their
 * rating and when. Opening a message marks its replies read, which counts
 * the badge down. A manager's list holds only their own hotel's replies;
 * the server decides that (REM-07, ROLE-02).
 */
type Props = {
    /** Null until the first load has come back. */
    threads: MessageReplyThread[] | null;
    loading?: boolean;
    /** The message to open first (a bell click or a log row). */
    focus?: string | null;
};

const props = withDefaults(defineProps<Props>(), {
    loading: false,
    focus: null,
});

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    /** Mark the replies to this message read. */
    read: [batch: string];
}>();

const { t } = useI18n();

const expanded = ref<string[]>([]);
// Replies shown as new in this sitting, kept after they are marked read so
// the "New" label does not vanish while the admin is reading.
const fresh = ref<number[]>([]);

function isOpen(thread: MessageReplyThread): boolean {
    return expanded.value.includes(thread.batch);
}

function markSeen(thread: MessageReplyThread): void {
    const unread = thread.replies
        .filter((reply) => reply.unread)
        .map((reply) => reply.id);

    if (unread.length === 0) {
        return;
    }

    fresh.value = [...new Set([...fresh.value, ...unread])];
    emit('read', thread.batch);
}

function toggle(thread: MessageReplyThread): void {
    if (isOpen(thread)) {
        expanded.value = expanded.value.filter(
            (batch) => batch !== thread.batch,
        );

        return;
    }

    expanded.value = [...expanded.value, thread.batch];
    markSeen(thread);
}

// When the list arrives, open the asked-for message, or the newest one.
watch(
    () => [props.threads, props.focus, open.value] as const,
    ([threads, focus, isShown]) => {
        if (!isShown || threads === null || threads.length === 0) {
            return;
        }

        const target =
            threads.find((thread) => thread.batch === focus) ?? threads[0];

        if (target !== undefined && !isOpen(target)) {
            expanded.value = [...expanded.value, target.batch];
            markSeen(target);
        }
    },
    { immediate: true },
);

watch(open, (isShown) => {
    if (!isShown) {
        expanded.value = [];
        fresh.value = [];
    }
});

function isNew(id: number, unread: boolean): boolean {
    return unread || fresh.value.includes(id);
}

function replied(thread: MessageReplyThread): string {
    return t(':replies of :recipients replied', {
        replies: thread.replyCount,
        recipients: thread.recipients,
    });
}
</script>

<template>
    <MessagesModal
        v-model:open="open"
        list
        :title="$t('Replies')"
        :description="
            $t(
                'Answers from employees to the messages you sent with “Allow replies”, newest first.',
            )
        "
        class="sm:max-w-[720px]"
    >
        <div :aria-busy="loading || undefined" data-test="replies-list">
            <div
                v-if="threads === null"
                class="flex flex-col gap-2"
                :aria-label="$t('Loading replies')"
            >
                <Skeleton v-for="n in 3" :key="n" class="h-16 rounded-md" />
            </div>

            <div
                v-else-if="threads.length === 0"
                class="text-ink-slate flex flex-col items-center gap-2 rounded-md border border-dashed px-4 py-10 text-center text-[12.5px]"
            >
                <MessageSquareReply
                    class="text-brand-600 size-6"
                    aria-hidden="true"
                />
                <p>
                    {{
                        $t(
                            'No message has asked for replies yet. Tick “Allow replies” when you send one.',
                        )
                    }}
                </p>
            </div>

            <ul v-else class="flex flex-col gap-2">
                <li
                    v-for="thread in threads"
                    :key="thread.batch"
                    :class="
                        cn(
                            'border-line/80 bg-surface rounded-md border',
                            thread.batch === focus && 'border-brand-200',
                        )
                    "
                    :data-test="`reply-thread-${thread.batch}`"
                >
                    <button
                        type="button"
                        class="hover:bg-brand-50/60 focus-visible:ring-brand-600/15 flex min-h-11 w-full items-start gap-3 rounded-md px-3 py-2.5 text-start focus-visible:ring-3 focus-visible:outline-none"
                        :aria-expanded="isOpen(thread)"
                        @click="toggle(thread)"
                    >
                        <div class="min-w-0 flex-1">
                            <p
                                class="text-brand-900 flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px] font-semibold"
                            >
                                <span class="min-w-0 break-words">{{
                                    thread.title
                                }}</span>
                                <span
                                    v-if="thread.unread > 0"
                                    class="rounded-pill bg-danger-tint text-danger-text inline-flex min-h-5 items-center px-2 text-[10.5px] font-semibold"
                                >
                                    {{
                                        $t(':count new', {
                                            count: thread.unread,
                                        })
                                    }}
                                </span>
                            </p>
                            <p
                                v-if="thread.subject !== thread.title"
                                class="text-ink-slate text-[12px] leading-4.5 break-words"
                            >
                                {{ thread.subject }}
                            </p>
                            <p
                                class="text-ink-muted mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11.5px]"
                            >
                                <span>{{ thread.sentAt }}</span>
                                <span aria-hidden="true">·</span>
                                <span class="text-brand-700 font-semibold">{{
                                    replied(thread)
                                }}</span>
                                <template v-if="thread.averageRating !== null">
                                    <span aria-hidden="true">·</span>
                                    <span
                                        class="inline-flex items-center gap-1"
                                    >
                                        <MessagesRatingStars
                                            :rating="thread.averageRating"
                                        />
                                        <span>{{
                                            $t('average :rating', {
                                                rating: thread.averageRating,
                                            })
                                        }}</span>
                                    </span>
                                </template>
                            </p>
                        </div>
                        <ChevronDown
                            :class="
                                cn(
                                    'text-ink-muted mt-1 size-4 shrink-0 transition-transform duration-150 motion-reduce:transition-none',
                                    isOpen(thread) && 'rotate-180',
                                )
                            "
                            aria-hidden="true"
                        />
                    </button>

                    <div
                        v-if="isOpen(thread)"
                        class="border-line/80 border-t px-3 py-2"
                    >
                        <p
                            v-if="thread.replies.length === 0"
                            class="text-ink-slate py-3 text-center text-[12.5px]"
                        >
                            {{ $t('No replies yet.') }}
                        </p>
                        <ul
                            v-else
                            class="divide-line/80 flex flex-col divide-y"
                        >
                            <li
                                v-for="reply in thread.replies"
                                :key="reply.id"
                                class="py-2.5"
                                :data-test="`reply-${reply.id}`"
                            >
                                <div
                                    class="flex flex-wrap items-center gap-x-2 gap-y-1"
                                >
                                    <p
                                        class="text-brand-900 text-[13px] font-semibold"
                                    >
                                        {{ reply.name }}
                                    </p>
                                    <span
                                        v-if="isNew(reply.id, reply.unread)"
                                        class="rounded-pill bg-brand-100/70 text-brand-700 inline-flex min-h-5 items-center px-2 text-[10.5px] font-semibold"
                                    >
                                        {{ $t('New') }}
                                    </span>
                                    <MessagesRatingStars
                                        v-if="reply.rating !== null"
                                        :rating="reply.rating"
                                        class="ms-auto"
                                    />
                                </div>
                                <p class="text-ink-muted text-[11.5px]">
                                    {{ reply.hotel }} · {{ reply.department }}
                                </p>
                                <p
                                    v-if="reply.body !== null"
                                    class="text-ink mt-1 text-[13px] leading-5 break-words whitespace-pre-line"
                                >
                                    {{ reply.body }}
                                </p>
                                <p class="text-ink-faint mt-1 text-[11px]">
                                    {{ reply.repliedAt }}
                                    <template v-if="reply.edited">
                                        · {{ $t('edited') }}
                                    </template>
                                </p>
                            </li>
                        </ul>
                    </div>
                </li>
            </ul>
        </div>
    </MessagesModal>
</template>
