<script setup lang="ts">
import { Check, ChevronLeft, Eye, EyeOff, Mic, Turtle, UserRound, Volume2 } from "@lucide/vue";
import {
    useDocumentVisibility,
    useIntersectionObserver,
    usePreferredReducedMotion,
} from "@vueuse/core";
import { computed, onBeforeUnmount, ref, watch } from "vue";
import type { HTMLAttributes } from "vue";
import { useI18n } from "@/composables/useI18n";
import { cn } from "@/lib/utils";
import type { LandingPageContent } from "@/types";

/*
 * The landing hero's phone (user request 2026-11-05): one lesson step of
 * the learner app, drawn with the app's own pieces (the 🔊 Normal / 🐢 Slow
 * pair, the Show Meaning box and panel, the reply and recorder), playing
 * on a loop. A guest speaks, the audio plays, the learner taps Show Meaning
 * and the Arabic opens (CTRL-01, CTRL-02), then the learner records a reply
 * and the step is saved (PROG-03). The copy is the Super Admin's, from
 * Website Management. Purely illustrative: the parent labels the figure,
 * so everything here is aria-hidden. The loop runs only while the phone is
 * on screen; with reduced motion it rests on the finished step.
 */
type Props = {
    hero: LandingPageContent["hero"];
    class?: HTMLAttributes["class"];
};

const props = defineProps<Props>();

const { locale } = useI18n();

/** Rest on each phase this long (ms): idle, audio, tap, meaning, reply, saved. */
const durations = [1500, 2600, 700, 2900, 2900, 3200];
const finalPhase = durations.length - 1;

const root = ref<HTMLElement | null>(null);
const onScreen = ref(false);
const reduced = usePreferredReducedMotion();
const documentVisibility = useDocumentVisibility();
const phase = ref(0);
let timer: ReturnType<typeof setTimeout> | undefined;

useIntersectionObserver(root, (entries) => {
    onScreen.value = entries.some((entry) => entry.isIntersecting);
});

function stop(): void {
    if (timer !== undefined) {
        clearTimeout(timer);
        timer = undefined;
    }
}

function schedule(): void {
    stop();
    timer = setTimeout(() => {
        phase.value = (phase.value + 1) % durations.length;
        schedule();
    }, durations[phase.value]);
}

watch(
    [onScreen, documentVisibility, reduced],
    () => {
        if (reduced.value === "reduce") {
            stop();
            phase.value = finalPhase;

            return;
        }

        if (onScreen.value && documentVisibility.value === "visible") {
            schedule();
        } else {
            stop();
        }
    },
    { immediate: true },
);

onBeforeUnmount(stop);

const playing = computed(() => phase.value === 1);
const tapping = computed(() => phase.value === 2);
const meaningShown = computed(() => phase.value >= 3);
const replyShown = computed(() => phase.value >= 4);
const saved = computed(() => phase.value >= finalPhase);

const bars = [6, 11, 17, 9, 20, 14, 7, 16, 21, 12, 8, 15, 10, 5];
</script>

<template>
    <div
        ref="root"
        :class="cn('bg-ink-night shadow-pop rounded-[2.75rem] p-2.5 select-none', props.class)"
        aria-hidden="true"
        data-test="landing-hero-phone"
    >
        <div
            class="bg-app relative flex h-[548px] flex-col overflow-hidden rounded-[2.2rem] px-3.5 pt-9 pb-4"
        >
            <span
                class="bg-ink-night rounded-pill absolute top-2.5 left-1/2 h-[18px] w-[86px] -translate-x-1/2"
            />

            <!-- Lesson step header, as LessonStepHeader draws it. -->
            <div class="flex items-center gap-2">
                <span
                    class="border-line bg-surface text-brand-800 grid size-7 shrink-0 place-items-center rounded-md border"
                >
                    <ChevronLeft class="size-4" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-ink-slate text-[10.5px] leading-4 font-semibold">
                        {{ $t("Step :current of :total", { current: 4, total: 9 }) }}
                    </p>
                    <p
                        class="font-heading text-ink-night line-clamp-2 text-[12.5px] leading-[1.3] font-semibold"
                    >
                        {{ hero.demo_situation }}
                    </p>
                </div>
            </div>
            <div class="bg-tint-track rounded-pill mt-2.5 h-1.5 overflow-hidden">
                <div class="bg-brand-600 rounded-pill h-full w-[44%]" />
            </div>

            <!-- The guest's line with the Normal / Slow audio pair. -->
            <div class="border-line bg-surface shadow-card mt-3.5 rounded-lg border p-3">
                <div class="flex items-center gap-2">
                    <span
                        class="bg-tint-grid text-ink-graphite grid size-6 place-items-center rounded-full"
                    >
                        <UserRound class="size-3.5" />
                    </span>
                    <span class="text-ink-slate text-[11px] font-semibold">
                        {{ $t("Guest") }}
                    </span>
                </div>
                <p
                    lang="en"
                    dir="ltr"
                    class="text-ink-night mt-2 text-start text-[15px] leading-[1.45] font-medium"
                >
                    {{ hero.demo_guest_line }}
                </p>

                <div class="mt-3 flex items-center gap-2">
                    <span
                        :class="
                            cn(
                                'bg-brand-50 text-brand-700 flex h-9 items-center gap-1.5 rounded-md px-2.5 text-[11.5px] font-semibold transition-shadow duration-300 motion-reduce:transition-none',
                                playing && 'animate-pulse-ring motion-reduce:animate-none',
                            )
                        "
                    >
                        <Volume2 class="text-brand-600 size-4" />
                        {{ $t("Normal") }}
                    </span>
                    <span
                        class="bg-success-tint text-success-text flex h-9 items-center gap-1.5 rounded-md px-2.5 text-[11.5px] font-semibold"
                    >
                        <Turtle class="size-4" />
                        {{ $t("Slow") }}
                    </span>
                    <span class="ms-auto flex h-6 items-center gap-[2.5px]" dir="ltr">
                        <span
                            v-for="(height, index) in bars"
                            :key="index"
                            :class="
                                cn(
                                    'rounded-pill w-[2.5px] origin-center transition-colors duration-300 motion-reduce:transition-none',
                                    playing
                                        ? 'bg-brand-500 animate-landing-wave motion-reduce:animate-none'
                                        : 'bg-line-strong',
                                )
                            "
                            :style="{
                                height: `${height}px`,
                                animationDelay: `${(index % 5) * 90}ms`,
                            }"
                        />
                    </span>
                </div>

                <!-- Show Meaning: the Arabic opens only on a tap. -->
                <div
                    :class="
                        cn(
                            'bg-tint-grid relative mt-3 flex min-h-11 items-center justify-center gap-3 overflow-hidden rounded-md px-3 transition-[background-color,scale] duration-200 motion-reduce:transition-none',
                            tapping && 'bg-line scale-[.97]',
                        )
                    "
                >
                    <span
                        v-if="tapping"
                        class="bg-brand-600/25 animate-landing-tap absolute top-1/2 left-1/2 size-16 -translate-x-1/2 -translate-y-1/2 rounded-full motion-reduce:hidden"
                    />
                    <component
                        :is="meaningShown ? EyeOff : Eye"
                        class="text-ink-graphite relative size-5 shrink-0"
                    />
                    <span class="relative flex flex-col items-center leading-tight">
                        <span
                            lang="ar"
                            dir="rtl"
                            class="font-arabic text-ink text-[13px] !leading-5 font-semibold"
                        >
                            {{ meaningShown ? "إخفاء المعنى" : "إظهار المعنى" }}
                        </span>
                        <span v-if="locale === 'en'" class="text-ink-slate text-[10.5px]">
                            {{ meaningShown ? $t("Hide Meaning") : $t("Show Meaning") }}
                        </span>
                    </span>
                </div>
                <div
                    :class="
                        cn(
                            'grid transition-[grid-template-rows,opacity,margin] duration-500 ease-brand motion-reduce:transition-none',
                            meaningShown
                                ? 'mt-2 grid-rows-[1fr] opacity-100'
                                : 'mt-0 grid-rows-[0fr] opacity-0',
                        )
                    "
                >
                    <div class="overflow-hidden">
                        <p
                            lang="ar"
                            dir="rtl"
                            class="bg-brand-50 text-ink rounded-md px-3 py-2 text-start text-[15px] !leading-7 font-semibold"
                        >
                            {{ hero.demo_meaning }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- The learner's spoken reply. -->
            <div
                :class="
                    cn(
                        'mt-3.5 transition-[opacity,translate] duration-500 ease-brand motion-reduce:transition-none',
                        replyShown ? 'translate-y-0 opacity-100' : 'translate-y-3 opacity-0',
                    )
                "
            >
                <p class="text-ink-slate text-end text-[11px] font-semibold">
                    {{ $t("Your reply") }}
                </p>
                <p
                    lang="en"
                    dir="ltr"
                    class="bg-brand-600 text-surface ms-6 mt-1.5 rounded-lg rounded-se-sm px-3 py-2.5 text-start text-[13.5px] leading-[1.45] font-medium"
                >
                    {{ hero.demo_reply }}
                </p>
                <div class="mt-2.5 flex h-8 items-center justify-end">
                    <span
                        v-if="!saved"
                        class="text-danger-text bg-danger-tint rounded-pill inline-flex h-8 items-center gap-2 px-3 text-[11px] font-semibold"
                    >
                        <Mic class="size-3.5" />
                        <span
                            class="bg-danger size-1.5 animate-pulse rounded-full motion-reduce:animate-none"
                        />
                        {{ $t("Recording…") }}
                    </span>
                    <span
                        v-else
                        class="bg-success-tint text-success-text rounded-pill animate-pop-in inline-flex h-8 items-center gap-1.5 px-3 text-[11px] font-semibold motion-reduce:animate-none"
                    >
                        <Check class="size-3.5" />
                        {{ $t("Saved to your progress") }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
