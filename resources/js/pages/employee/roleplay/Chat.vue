<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Bot, Flag, Send } from '@lucide/vue';
import { onMounted, onUnmounted, ref } from 'vue';
import AudioButton from '@/components/learning/AudioButton.vue';
import { cn } from '@/lib/utils';

/*
 * The role-play conversation (RP-03, RP-11, PERF-04; spec 0003 Part E,
 * photo_17). The guest's reply is produced by a queued job; the page polls
 * `pendingReply` and shows a typing bubble until it lands.
 */
type Turn = {
    id: number;
    role: string;
    text: string;
    audio: { normal: string | null; slow: string | null };
    at: string | null;
};

type Props = {
    attempt: {
        id: number;
        status: string;
        pendingReply: boolean;
        aiStatus: string | null;
        failedReason: string | null;
        transcript: Turn[];
        employeeTurns: number;
        minTurns: number;
        maxTurns: number;
        atTurnLimit: boolean;
        inputMode: string;
        canEnd: boolean;
    };
    scenario: {
        title: string;
        icon: string;
        usefulPhrases: string[];
        tip: string | null;
    };
    messageUrl: string;
    endUrl: string;
    feedbackUrl: string;
    pollUrl: string;
};

const props = defineProps<Props>();

const text = ref('');
const sending = ref(false);
let timer: ReturnType<typeof setInterval> | null = null;

function send(): void {
    if (
        text.value.trim() === '' ||
        props.attempt.pendingReply ||
        sending.value
    ) {
        return;
    }

    sending.value = true;
    router.post(
        props.messageUrl,
        { text: text.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => (text.value = ''),
            onFinish: () => (sending.value = false),
        },
    );
}

function end(): void {
    router.post(props.endUrl);
}

onMounted(() => {
    timer = setInterval(() => {
        if (props.attempt.pendingReply) {
            router.reload({ only: ['attempt'] });
        }
    }, 1500);
});

onUnmounted(() => {
    if (timer !== null) {
        clearInterval(timer);
    }
});
</script>

<template>
    <Head :title="$t('Role-play – :title', { title: scenario.title })" />
    <h1 class="sr-only">
        {{ $t('Role-play: :title', { title: scenario.title }) }}
    </h1>

    <section
        class="max-w-content mx-auto grid gap-6 p-4 md:p-6 xl:grid-cols-[minmax(0,1fr)_320px]"
    >
        <div class="grid content-start gap-4">
            <header class="flex items-center gap-3">
                <span
                    class="bg-brand-800 grid size-11 place-items-center rounded-xl text-white"
                >
                    <Bot class="size-6" aria-hidden="true" />
                </span>
                <div>
                    <p
                        class="font-heading text-ink-royal text-[18px] font-semibold"
                    >
                        {{ scenario.title }}
                    </p>
                    <p class="text-ink-slate text-[12.5px]">
                        {{ $t('AI Guest conversation') }}
                    </p>
                </div>
            </header>

            <div
                class="border-line bg-surface shadow-card grid gap-3 rounded-lg border p-5"
            >
                <div
                    v-for="turn in attempt.transcript"
                    :key="turn.id"
                    :class="
                        cn(
                            'flex gap-2.5',
                            turn.role === 'employee' && 'flex-row-reverse',
                        )
                    "
                >
                    <span
                        :class="
                            cn(
                                'grid size-8 shrink-0 place-items-center rounded-full text-[12px] font-semibold',
                                turn.role === 'employee'
                                    ? 'bg-success-tint text-success-text'
                                    : 'bg-brand-100 text-brand-700',
                            )
                        "
                    >
                        <Bot
                            v-if="turn.role !== 'employee'"
                            class="size-4"
                            aria-hidden="true"
                        />
                        <template v-else>{{ $t('You') }}</template>
                    </span>
                    <div
                        :class="
                            cn(
                                'max-w-[80%] rounded-2xl px-4 py-2.5 text-[14px] leading-6',
                                turn.role === 'employee'
                                    ? 'bg-success-tint text-ink'
                                    : 'bg-brand-50 text-ink',
                            )
                        "
                    >
                        <p class="whitespace-pre-line">{{ turn.text }}</p>
                        <div
                            v-if="turn.role !== 'employee'"
                            class="mt-2 flex items-center gap-1.5"
                        >
                            <AudioButton
                                :src="turn.audio.normal"
                                variant="normal"
                                size="sm"
                                :text="turn.text"
                                class="bg-surface/80"
                            />
                            <AudioButton
                                :src="turn.audio.slow"
                                variant="slow"
                                size="sm"
                                :text="turn.text"
                            />
                        </div>
                    </div>
                </div>

                <div v-if="attempt.pendingReply" class="flex gap-2.5">
                    <span
                        class="bg-brand-100 text-brand-700 grid size-8 shrink-0 place-items-center rounded-full"
                    >
                        <Bot class="size-4" aria-hidden="true" />
                    </span>
                    <p
                        class="bg-brand-50 text-ink-slate rounded-2xl px-4 py-2.5 text-[14px]"
                    >
                        …
                    </p>
                </div>

                <div
                    v-if="
                        attempt.aiStatus === 'failed' && !attempt.pendingReply
                    "
                    class="border-danger/30 bg-danger-tint text-danger-text flex items-start gap-2 rounded-md border px-3 py-2"
                >
                    <p class="text-[12px] leading-5">
                        {{
                            $t(
                                'The guest reply could not be generated. Please start a new attempt and try again.',
                            )
                        }}
                        <span
                            v-if="attempt.failedReason"
                            class="block text-[11px] opacity-80"
                        >
                            {{ attempt.failedReason }}
                        </span>
                    </p>
                </div>
            </div>

            <div
                v-if="
                    attempt.atTurnLimit &&
                    !attempt.pendingReply &&
                    attempt.status === 'in_progress'
                "
                class="border-line bg-surface shadow-card grid gap-3 rounded-lg border p-4 sm:flex sm:items-center sm:justify-between"
                data-test="roleplay-turn-limit"
            >
                <p class="text-ink text-[14px]">
                    {{ $t('The guest has wrapped up the conversation.') }}
                    <span class="text-ink-slate block text-[12.5px]">
                        {{
                            $t(
                                'You used all :count replies. Well done for keeping it going.',
                                { count: attempt.maxTurns },
                            )
                        }}
                    </span>
                </p>
                <button
                    type="button"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 focus-visible:ring-brand-600/15 inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-md px-5 text-[13px] font-semibold text-white focus-visible:ring-3 focus-visible:outline-none active:scale-[.97]"
                    data-test="roleplay-get-feedback-button"
                    @click="end"
                >
                    <Flag class="size-4" aria-hidden="true" />
                    {{ $t('Get my feedback') }}
                </button>
            </div>

            <div
                v-else
                class="border-line bg-surface shadow-card grid gap-3 rounded-lg border p-4"
            >
                <textarea
                    v-model="text"
                    rows="2"
                    :placeholder="
                        attempt.pendingReply
                            ? $t('Waiting for the guest…')
                            : $t('Type your reply…')
                    "
                    :disabled="
                        attempt.pendingReply || attempt.status !== 'in_progress'
                    "
                    class="border-line text-ink bg-surface placeholder:text-ink-faint focus-visible:border-brand-600 focus-visible:ring-brand-600/15 w-full resize-none rounded-md border px-3 py-2 text-[14px] shadow-none focus-visible:ring-3 focus-visible:outline-none"
                    @keydown.enter.exact.prevent="send"
                />
                <div class="flex items-center justify-between gap-3">
                    <button
                        type="button"
                        :disabled="
                            !attempt.canEnd || attempt.status !== 'in_progress'
                        "
                        class="border-line text-brand-700 hover:bg-brand-50 bg-surface inline-flex h-10 items-center gap-2 rounded-md border px-4 text-[13px] font-semibold shadow-none disabled:opacity-50"
                        @click="end"
                    >
                        <Flag class="size-4" aria-hidden="true" />
                        {{ $t('End & get feedback') }}
                    </button>
                    <button
                        type="button"
                        :disabled="
                            text.trim() === '' ||
                            attempt.pendingReply ||
                            sending
                        "
                        class="bg-brand-600 shadow-btn hover:bg-brand-700 inline-flex h-10 items-center gap-2 rounded-md px-5 text-[13px] font-semibold text-white active:scale-[.97] disabled:opacity-50"
                        @click="send"
                    >
                        {{ $t('Send') }}
                        <Send class="size-4" aria-hidden="true" />
                    </button>
                </div>
                <p class="text-ink-faint text-[11.5px]">
                    {{
                        $t('Reply :current of :total', {
                            current: attempt.employeeTurns,
                            total: attempt.maxTurns,
                        })
                    }}
                    <template v-if="!attempt.canEnd">
                        ·
                        {{
                            $t('you can finish after :count', {
                                count: attempt.minTurns,
                            })
                        }}
                    </template>
                </p>
            </div>
        </div>

        <aside class="grid content-start gap-4">
            <div
                v-if="scenario.usefulPhrases.length > 0"
                class="border-line bg-surface shadow-card grid gap-2 rounded-lg border p-5"
            >
                <h2
                    class="font-heading text-ink-royal text-[15px] font-semibold"
                >
                    {{ $t('Useful phrases') }}
                </h2>
                <ul class="grid gap-1.5">
                    <li
                        v-for="phrase in scenario.usefulPhrases"
                        :key="phrase"
                        class="bg-brand-50 text-brand-800 rounded-md px-3 py-2 text-[13px]"
                    >
                        {{ phrase }}
                    </li>
                </ul>
            </div>
            <div
                v-if="scenario.tip"
                class="bg-tint-note grid gap-1 rounded-lg p-4"
            >
                <span class="text-ink text-[12px] font-semibold">{{
                    $t('Tip')
                }}</span>
                <p class="text-ink-slate text-[12.5px]">{{ scenario.tip }}</p>
            </div>
        </aside>
    </section>
</template>
