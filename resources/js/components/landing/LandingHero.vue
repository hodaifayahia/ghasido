<script setup lang="ts">
import { ArrowRight, Check, Hotel, Smartphone, Sparkles } from '@lucide/vue';
import { usePreferredReducedMotion, useWindowScroll } from '@vueuse/core';
import { computed } from 'vue';
import type { Component } from 'vue';
import InstallAppButton from '@/components/landing/InstallAppButton.vue';
import LandingHeroPhone from '@/components/landing/LandingHeroPhone.vue';
import { Button } from '@/components/ui/button';
import { vReveal } from '@/directives/vReveal';
import type { LandingPageContent } from '@/types';

/*
 * The landing page's first screen (user request 2026-11-05: an
 * intentional, hospitality-specific hero, not decoration for its own
 * sake). An editorial headline that rises word by word, the client's own
 * hotel arcade photograph fading into the page, and in front of it a phone
 * playing one real lesson step (LandingHeroPhone), with the learner
 * journey from "How it works" beside it. The photo and the phone drift
 * apart a little as the page scrolls. Every word comes from Website
 * Management; with reduced motion nothing moves.
 */
type Props = {
    content: LandingPageContent;
};

const props = defineProps<Props>();

const words = computed(() =>
    props.content.hero.title.split(/\s+/).filter(Boolean),
);

const reduced = usePreferredReducedMotion();
const { y } = useWindowScroll();

/** Scroll distance the parallax follows; it stops past the first screen. */
const shift = computed(() =>
    reduced.value === 'reduce' ? 0 : Math.min(Math.max(y.value, 0), 900),
);
const photoStyle = computed(() => ({
    transform: `translate3d(0, ${(shift.value * 0.12).toFixed(1)}px, 0)`,
}));
const deviceStyle = computed(() => ({
    transform: `translate3d(0, ${(shift.value * -0.05).toFixed(1)}px, 0)`,
}));

/** The phone plays a department lesson: the journey's second step. */
const currentStep = 1;
const steps = computed(() => props.content.journey.steps.slice(0, 5));

const proofIcons: Component[] = [Hotel, Smartphone, Sparkles];
</script>

<template>
    <section
        class="bg-surface relative isolate overflow-hidden"
        aria-labelledby="hero-title"
    >
        <div
            class="relative mx-auto grid max-w-7xl gap-6 px-5 pt-10 pb-12 sm:px-8 sm:pt-14 md:gap-10 lg:grid-cols-[minmax(0,1.04fr)_minmax(0,1fr)] lg:items-center lg:gap-6 lg:px-10 lg:pt-12 lg:pb-14"
        >
            <div class="relative z-10 min-w-0">
                <h1
                    id="hero-title"
                    class="font-heading text-ink-night text-[clamp(2.4rem,4.7vw,4.1rem)] leading-[1.04] font-bold tracking-[-0.045em] text-balance"
                >
                    <template v-for="(word, index) in words" :key="index">
                        <span
                            class="animate-landing-word inline-block motion-reduce:animate-none"
                            :style="{ animationDelay: `${80 + index * 55}ms` }"
                            >{{ word }}</span
                        >{{ ' ' }}
                    </template>
                </h1>
                <p
                    v-reveal:fade="120 + words.length * 55"
                    class="text-ink-graphite mt-6 max-w-xl text-[16px] leading-7 sm:text-[17.5px] sm:leading-8"
                >
                    {{ content.hero.description }}
                </p>
                <div
                    v-reveal="220 + words.length * 55"
                    class="mt-8 flex flex-wrap items-center gap-3"
                >
                    <Button
                        as-child
                        class="bg-brand-600 text-surface shadow-btn hover:bg-brand-700 group h-12 rounded-md px-6 text-[14px] font-semibold"
                    >
                        <a
                            href="#how-it-works"
                            data-test="landing-hero-primary"
                        >
                            {{ content.hero.primary_cta }}
                            <ArrowRight
                                class="size-4 transition-[translate] duration-200 group-hover:translate-x-0.5 motion-reduce:transition-none rtl:group-hover:-translate-x-0.5"
                            />
                        </a>
                    </Button>
                    <Button
                        as-child
                        variant="outline"
                        class="border-line-strong bg-surface text-brand-700 shadow-card hover:bg-brand-50 h-12 rounded-md px-6 text-[14px] font-semibold"
                    >
                        <a href="#pricing">{{ content.hero.secondary_cta }}</a>
                    </Button>
                    <InstallAppButton variant="outline" />
                </div>
            </div>

            <!-- The stage. Below lg the photo is a banner the phone rises
                 out of; from lg it sits at the end of the hero and bleeds
                 to the edge, behind the phone. -->
            <div class="relative min-w-0 lg:static">
                <div
                    class="pointer-events-none relative -mx-5 select-none sm:-mx-8 lg:absolute lg:end-0 lg:top-8 lg:mx-0 lg:w-[62%]"
                    aria-hidden="true"
                >
                    <div :style="photoStyle" class="will-change-transform">
                        <img
                            src="/decor/hotel-arcade.jpg"
                            alt=""
                            width="1008"
                            height="550"
                            fetchpriority="high"
                            class="animate-landing-photo block aspect-[1008/550] w-full [mask-image:linear-gradient(to_bottom,black_52%,transparent_94%),linear-gradient(to_right,transparent_0%,black_36%)] [mask-composite:intersect] object-cover motion-reduce:animate-none rtl:-scale-x-100"
                        />
                    </div>
                </div>

                <figure
                    role="img"
                    :aria-label="content.hero.image_alt"
                    class="relative z-10 mx-auto -mt-24 w-full max-w-[560px] sm:-mt-40 md:h-[600px] lg:mt-0"
                >
                    <div :style="deviceStyle" class="md:absolute md:inset-0">
                        <div
                            v-reveal:rise="260"
                            class="mx-auto w-[296px] md:absolute md:start-[2%] md:top-1"
                        >
                            <LandingHeroPhone :hero="content.hero" />
                        </div>

                        <!-- Sticky note pointing at Show Meaning. -->
                        <div
                            v-reveal:pop="900"
                            class="pointer-events-none absolute end-[3%] top-[212px] hidden select-none md:block"
                            aria-hidden="true"
                        >
                            <div
                                class="bg-tint-note shadow-card font-script text-brand-800 rtl:font-arabic max-w-[176px] rotate-2 rounded-sm px-4 py-2.5 text-[23px] leading-[1.05] font-bold rtl:-rotate-2 rtl:text-[15px] rtl:leading-6"
                            >
                                {{ content.hero.demo_note }}
                            </div>
                            <svg
                                viewBox="0 0 64 40"
                                width="64"
                                height="40"
                                fill="none"
                                class="text-brand-700 ms-[-22px] mt-1 rtl:-scale-x-100"
                            >
                                <path
                                    d="M60 4C46 6 30 14 12 30"
                                    stroke="currentColor"
                                    stroke-width="2.25"
                                    stroke-linecap="round"
                                />
                                <path
                                    d="M10 18l1.5 13.5L24 29"
                                    stroke="currentColor"
                                    stroke-width="2.25"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                        </div>

                        <!-- The learner journey, from "How it works". -->
                        <div
                            v-if="steps.length"
                            v-reveal:rise="700"
                            class="border-line bg-surface shadow-hover absolute end-0 bottom-4 hidden w-[224px] rounded-lg border p-4 md:block"
                            aria-hidden="true"
                        >
                            <p
                                class="text-brand-600 text-[10.5px] font-bold tracking-[0.14em] uppercase"
                            >
                                {{ content.journey.eyebrow }}
                            </p>
                            <ol class="mt-3">
                                <li
                                    v-for="(step, index) in steps"
                                    :key="step.title"
                                    class="relative flex items-start gap-2.5 pb-3 last:pb-0"
                                >
                                    <span
                                        v-if="index < steps.length - 1"
                                        :class="
                                            index < currentStep
                                                ? 'bg-success'
                                                : 'bg-tint-connector'
                                        "
                                        class="absolute start-[11px] top-6 bottom-0 w-0.5"
                                    />
                                    <span
                                        :class="
                                            index < currentStep
                                                ? 'bg-success text-surface'
                                                : index === currentStep
                                                  ? 'bg-brand-600 text-surface ring-brand-100 ring-4'
                                                  : 'bg-tint-step text-ink-steel'
                                        "
                                        class="font-heading relative grid size-6 shrink-0 place-items-center rounded-full text-[11px] font-bold"
                                    >
                                        <Check
                                            v-if="index < currentStep"
                                            class="size-3.5"
                                        />
                                        <template v-else>{{
                                            index + 1
                                        }}</template>
                                    </span>
                                    <span
                                        :class="
                                            index === currentStep
                                                ? 'text-ink-night font-semibold'
                                                : index < currentStep
                                                  ? 'text-ink-graphite'
                                                  : 'text-ink-steel'
                                        "
                                        class="pt-0.5 text-[12.5px] leading-5"
                                    >
                                        {{ step.title }}
                                    </span>
                                </li>
                            </ol>
                        </div>
                    </div>
                </figure>
            </div>
        </div>

        <!-- Proof points, a quiet band closing the first screen. -->
        <div class="border-line bg-app relative z-10 border-y">
            <ul
                class="mx-auto grid max-w-7xl gap-x-8 gap-y-3 px-5 py-5 sm:grid-cols-3 sm:px-8 lg:px-10"
            >
                <li
                    v-for="(point, index) in content.hero.proof_points"
                    :key="point"
                    v-reveal:fade="500 + index * 120"
                    class="text-ink-indigo border-line flex items-center gap-3 text-[13px] leading-5 font-semibold sm:border-s sm:ps-8 sm:first:border-s-0 sm:first:ps-0"
                >
                    <span
                        class="border-line bg-surface text-brand-600 shadow-card grid size-9 shrink-0 place-items-center rounded-md border"
                    >
                        <component
                            :is="proofIcons[index % proofIcons.length]"
                            class="size-[18px]"
                            aria-hidden="true"
                        />
                    </span>
                    {{ point }}
                </li>
            </ul>
        </div>
    </section>
</template>
