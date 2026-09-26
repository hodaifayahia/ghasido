<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Bot,
    Phone,
    Target,
    User,
    Users,
    Video,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import VoiceCall from '@/components/roleplay/VoiceCall.vue';
import type { MediaRef, VoiceCallOffer } from '@/types';

/*
 * Get Ready before a role-play (RP-01, RP-04; spec 0003 Part E, photo_16).
 * The scenario brief and the attempts left; starting queues the guest's
 * opening line.
 */
type Scenario = {
    id: number;
    title: string;
    description: string | null;
    difficulty: string;
    situation: string;
    yourRole: string;
    guestRole: string;
    objective: string;
    goals: string[];
    usefulPhrases: string[];
    tip: string | null;
    quote: string | null;
    attemptsAllowed: number;
    attemptsUsed: number;
    attemptsLeft: number;
    thumbnail?: MediaRef | null;
};

type Props = {
    scenario: Scenario;
    startUrl: string;
    backUrl: string;
    canStart: boolean;
    /** Live spoken call (spec 0004), offered beside the text chat. */
    voiceCall?: VoiceCallOffer | null;
};

const props = defineProps<Props>();

const voiceCallOpen = ref(false);

const dots = computed(() =>
    Array.from(
        { length: props.scenario.attemptsAllowed },
        (_, i) => i < props.scenario.attemptsUsed,
    ),
);

function start(): void {
    router.post(props.startUrl);
}
</script>

<template>
    <Head :title="$t('Get ready – :title', { title: scenario.title })" />

    <section
        class="max-w-content mx-auto grid gap-6 p-4 md:p-6 xl:grid-cols-[minmax(0,1fr)_360px]"
    >
        <div class="grid content-start gap-5">
            <header class="flex items-start gap-4">
                <span
                    class="bg-brand-800 grid size-16 shrink-0 place-items-center rounded-xl text-white"
                >
                    <Bot class="size-9" aria-hidden="true" />
                </span>
                <div class="grid gap-1">
                    <h1
                        class="font-heading text-ink-royal text-[26px] font-bold tracking-[-0.02em]"
                    >
                        {{ $t('AI Role-play') }}
                    </h1>
                    <p class="text-ink-slate text-[15px]">
                        {{ $t('Get ready for your conversation.') }}
                    </p>
                </div>
            </header>

            <div
                class="border-line bg-surface shadow-card grid gap-3 rounded-lg border p-5"
            >
                <div class="flex items-center justify-between gap-3">
                    <span
                        class="text-ink shrink-0 text-[13px] font-semibold whitespace-nowrap"
                    >
                        {{
                            $t('Attempt :current of :total', {
                                current: scenario.attemptsUsed + 1,
                                total: scenario.attemptsAllowed,
                            })
                        }}
                    </span>
                    <div
                        class="flex min-w-0 flex-wrap items-center justify-end gap-1.5"
                    >
                        <span
                            v-for="(used, index) in dots"
                            :key="index"
                            :class="[
                                'size-3 rounded-full',
                                used ? 'bg-brand-600' : 'bg-brand-100',
                            ]"
                        />
                    </div>
                </div>
                <p v-if="scenario.tip" class="text-ink-slate text-[13px]">
                    {{ scenario.tip }}
                </p>
            </div>

            <div
                class="border-line bg-surface shadow-card grid gap-4 rounded-lg border p-5"
            >
                <div class="flex items-center gap-2">
                    <span
                        class="bg-brand-100 text-brand-700 grid size-9 place-items-center rounded-md"
                    >
                        <Target class="size-5" aria-hidden="true" />
                    </span>
                    <h2
                        class="font-heading text-ink-royal text-[18px] font-semibold"
                    >
                        {{ scenario.title }}
                    </h2>
                </div>
                <p class="text-ink text-[14px] leading-6">
                    {{ scenario.situation }}
                </p>

                <dl class="grid gap-3">
                    <div class="flex items-start gap-3">
                        <User
                            class="text-brand-700 mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <div class="grid gap-0.5">
                            <dt class="text-ink text-[13px] font-semibold">
                                {{ $t('Your role') }}
                            </dt>
                            <dd class="text-ink-slate text-[13px]">
                                {{ scenario.yourRole }}
                            </dd>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <Users
                            class="text-brand-700 mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <div class="grid gap-0.5">
                            <dt class="text-ink text-[13px] font-semibold">
                                {{ $t('The guest (AI)') }}
                            </dt>
                            <dd class="text-ink-slate text-[13px]">
                                {{ scenario.guestRole }}
                            </dd>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <Target
                            class="text-brand-700 mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <div class="grid gap-1">
                            <dt class="text-ink text-[13px] font-semibold">
                                {{ $t('Your goals') }}
                            </dt>
                            <dd>
                                <ul class="grid gap-1">
                                    <li
                                        v-for="goal in scenario.goals"
                                        :key="goal"
                                        class="text-ink-slate flex items-start gap-2 text-[13px]"
                                    >
                                        <span
                                            class="bg-brand-400 mt-1.5 size-1.5 shrink-0 rounded-full"
                                        />
                                        {{ goal }}
                                    </li>
                                </ul>
                            </dd>
                        </div>
                    </div>
                </dl>

                <div
                    class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between"
                >
                    <Link
                        :href="backUrl"
                        class="border-line text-brand-700 hover:bg-brand-50 bg-surface inline-flex h-11 items-center justify-center gap-2 rounded-md border px-5 text-[14px] font-semibold shadow-none"
                    >
                        <ArrowLeft class="size-4" aria-hidden="true" />
                        {{ $t('Back to Scenarios') }}
                    </Link>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <button
                            v-if="voiceCall"
                            type="button"
                            :disabled="!canStart"
                            class="border-brand-600 text-brand-700 hover:bg-brand-50 bg-surface focus-visible:ring-brand-600/15 inline-flex h-11 items-center justify-center gap-2 rounded-md border px-5 text-[14px] font-semibold focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] disabled:opacity-50"
                            data-test="start-voice-call-button"
                            @click="voiceCallOpen = true"
                        >
                            <Video class="size-4" aria-hidden="true" />
                            {{ $t('Start voice call') }}
                        </button>
                        <button
                            type="button"
                            :disabled="!canStart"
                            class="bg-brand-600 shadow-btn hover:bg-brand-700 inline-flex h-11 items-center justify-center gap-2 rounded-md px-5 text-[14px] font-semibold text-white active:scale-[.97] disabled:opacity-50"
                            @click="start"
                        >
                            <Phone class="size-4" aria-hidden="true" />
                            {{ $t('Start Role-play') }}
                            <ArrowRight class="size-4" aria-hidden="true" />
                        </button>
                    </div>
                </div>
                <p v-if="!canStart" class="text-danger-text text-[12.5px]">
                    {{
                        $t(
                            'You have used all :count attempts for this scenario.',
                            {
                                count: scenario.attemptsAllowed,
                            },
                        )
                    }}
                </p>
            </div>
        </div>

        <aside class="grid content-start gap-4">
            <figure
                v-if="scenario.quote"
                class="bg-grad-brand grid gap-2 rounded-lg p-6 text-white"
            >
                <blockquote class="font-quote text-[18px] leading-7 italic">
                    {{ scenario.quote }}
                </blockquote>
                <span class="bg-gold h-1 w-12 rounded-full" />
            </figure>

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
        </aside>
    </section>

    <VoiceCall
        v-if="voiceCall"
        v-model:open="voiceCallOpen"
        :start-url="voiceCall.startUrl"
        :title="scenario.title"
        :guest-role="voiceCall.guestRole"
        :thumbnail="scenario.thumbnail?.url ?? null"
    />
</template>
