<script setup lang="ts">
import { CircleAlert, LoaderCircle, Mic, PenLine } from '@lucide/vue';
import type { TestJudgedAnswer } from '@/types';

/*
 * The AI-judged speaking and writing answers of one sitting (TEST-07,
 * TEST-08, AIE-01, AIE-04): the structured criteria and summary for every
 * results viewer, the transcript / written text and the recording only when
 * the server sent them — i.e. for the Super Admin (ROLE-04, PRIV-04).
 */
defineProps<{
    answers: TestJudgedAnswer[];
}>();
</script>

<template>
    <ul class="grid gap-3">
        <li
            v-for="answer in answers"
            :key="answer.id"
            class="border-line bg-surface rounded-md border p-3"
        >
            <div class="flex flex-wrap items-center gap-2">
                <component
                    :is="answer.type === 'speaking' ? Mic : PenLine"
                    class="text-ai size-4 shrink-0"
                    aria-hidden="true"
                />
                <span class="text-brand-900 text-[12px] font-semibold">
                    {{ answer.typeLabel }}
                </span>
                <span class="text-ink-slate min-w-0 flex-1 text-[12px]">
                    {{ answer.question }}
                </span>
                <span
                    v-if="answer.aiStatus === 'done' && answer.score !== null"
                    class="text-brand-700 text-[12px] font-semibold"
                >
                    {{ Math.round(answer.score) }}/100
                </span>
                <span
                    v-else-if="
                        answer.aiStatus === 'pending' ||
                        answer.aiStatus === 'running'
                    "
                    class="text-ink-slate inline-flex items-center gap-1 text-[12px]"
                >
                    <LoaderCircle
                        class="size-3.5 animate-spin motion-reduce:animate-none"
                        aria-hidden="true"
                    />
                    {{
                        answer.type === 'speaking'
                            ? $t('Transcribing and evaluating…')
                            : $t('Evaluating…')
                    }}
                </span>
                <span
                    v-else-if="answer.aiStatus === 'failed'"
                    class="text-danger-text inline-flex items-center gap-1 text-[12px] font-semibold"
                >
                    <CircleAlert class="size-3.5" aria-hidden="true" />
                    {{ $t('Evaluation failed') }}
                </span>
            </div>

            <p
                v-if="answer.failedReason && answer.aiStatus === 'failed'"
                class="text-ink-slate mt-1 text-[11.5px]"
            >
                {{ answer.failedReason }}
            </p>

            <audio
                v-if="answer.recordingUrl"
                :src="answer.recordingUrl"
                controls
                preload="none"
                class="mt-2 h-9 w-full max-w-md"
            />

            <blockquote
                v-if="answer.answerText !== null"
                class="border-line text-ink mt-2 border-s-2 ps-3 text-[12.5px] leading-5 whitespace-pre-line"
            >
                <span class="text-ink-slate block text-[11px] font-semibold">
                    {{
                        answer.type === 'speaking'
                            ? $t('Transcript')
                            : $t('Written answer')
                    }}
                </span>
                {{ answer.answerText || $t('(nothing recognised)') }}
            </blockquote>

            <dl
                v-if="answer.criteria.length"
                class="mt-2 grid gap-1.5 sm:grid-cols-2"
            >
                <div
                    v-for="criterion in answer.criteria"
                    :key="criterion.key"
                    class="bg-app/60 rounded-sm px-2.5 py-1.5"
                >
                    <dt
                        class="text-brand-900 flex justify-between text-[11.5px] font-semibold"
                    >
                        <span>{{ criterion.label }}</span>
                        <span class="text-brand-700">{{
                            criterion.score
                        }}</span>
                    </dt>
                    <dd class="text-ink-slate text-[11.5px] leading-4">
                        {{ criterion.comment }}
                    </dd>
                </div>
            </dl>

            <p v-if="answer.summary" class="text-ink mt-2 text-[12px]">
                {{ answer.summary }}
            </p>
            <p
                v-if="answer.betterAnswer"
                class="text-ink-slate mt-1 text-[12px]"
            >
                <span class="font-semibold">{{ $t('A better answer:') }}</span>
                {{ answer.betterAnswer }}
            </p>
        </li>
    </ul>
</template>
