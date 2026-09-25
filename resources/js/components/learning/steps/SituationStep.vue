<script setup lang="ts">
import { Check, MessageSquare, Users } from '@lucide/vue';
import { computed } from 'vue';
import type { Component } from 'vue';
import { cn } from '@/lib/utils';
import type { LessonSummary, ObjectiveIcon, StepBlockOf } from '@/types';

/*
 * Step 1, "Situation (Intro)" (LESSON-06; spec 0003 B.10, G.3), measured on
 * desginphotos/employ/photo_1 at 1280×853, 12px under the heading row:
 *   left  the cover photo, 715×440 at x 30 / y 257, ~10px radius;
 *   right a white card 483×440 at x 766, 1px hairline + soft shadow,
 *         16px padding, its text a further 6px in:
 *         title 30px Poppins Bold (cap top y 281), intro 16px on 25px
 *         lines (y 330), three objective rows of 60px round chips 68px
 *         apart (y 393) with 18px text 23px after the chip, then the
 *         quote box 451×77 (y 603) in the sampled sky tint, serif italic
 *         18px centred.
 * Objectives come from the block settings (icon + text) and fall back to
 * the lesson row's plain list; the cover and copy come from the lesson row.
 */
type Props = {
    lesson: LessonSummary;
    block: StepBlockOf<'situation'>;
};

const props = defineProps<Props>();

type Objective = { icon: ObjectiveIcon; text: string };

const fallbackIcons: ObjectiveIcon[] = ['chat', 'people', 'check'];

const objectives = computed((): Objective[] => {
    const own = props.block.settings.objectives ?? [];

    if (own.length > 0) {
        return own;
    }

    return props.lesson.objectives.map((text, index) => ({
        icon: fallbackIcons[index % fallbackIcons.length] ?? 'chat',
        text,
    }));
});

const chip: Record<
    ObjectiveIcon,
    { component: Component; class: string; icon: string }
> = {
    chat: {
        component: MessageSquare,
        class: 'bg-brand-100',
        icon: 'text-brand-600 fill-brand-200 size-[26px] stroke-[2]',
    },
    people: {
        component: Users,
        class: 'bg-success-tint',
        icon: 'text-success fill-current size-[26px] stroke-[1.5]',
    },
    check: {
        component: Check,
        class: 'bg-gold-tint',
        icon: 'text-warning size-7 stroke-[3]',
    },
};
</script>

<template>
    <div class="mt-3 grid gap-4 md:grid-cols-[715fr_483fr] md:gap-[21px]">
        <figure class="bg-app-alt h-60 overflow-hidden rounded-md md:h-[440px]">
            <img
                v-if="lesson.cover"
                :src="lesson.cover.url"
                :alt="lesson.cover.alt ?? lesson.title"
                decoding="async"
                draggable="false"
                class="size-full object-cover"
            />
        </figure>

        <section
            class="border-line bg-surface shadow-card flex min-h-[440px] flex-col rounded-lg border p-4 pt-[18px]"
            :aria-label="lesson.title"
        >
            <div class="px-[6px]">
                <h2
                    class="font-heading text-ink-night text-[30px] leading-9 font-bold tracking-[-0.02em]"
                >
                    {{ lesson.title }}
                </h2>
                <p
                    v-if="lesson.introduction"
                    class="text-ink-graphite mt-[7px] text-base leading-[25px]"
                >
                    {{ lesson.introduction }}
                </p>

                <ul class="mt-[23px] flex list-none flex-col gap-2">
                    <li
                        v-for="objective in objectives"
                        :key="objective.text"
                        class="flex h-[60px] items-center"
                    >
                        <span
                            :class="
                                cn(
                                    'grid size-[60px] shrink-0 place-items-center rounded-full',
                                    chip[objective.icon].class,
                                )
                            "
                        >
                            <component
                                :is="chip[objective.icon].component"
                                :class="chip[objective.icon].icon"
                                aria-hidden="true"
                            />
                        </span>
                        <span
                            class="text-ink-graphite ms-[23px] text-[17px] leading-6"
                        >
                            {{ objective.text }}
                        </span>
                    </li>
                </ul>
            </div>

            <blockquote
                v-if="block.settings.quote"
                class="bg-tint-quote font-quote text-ink-graphite mt-auto flex min-h-[77px] items-center justify-center rounded-md px-6 py-[13px] text-center text-lg leading-[25px] italic"
            >
                {{ block.settings.quote }}
            </blockquote>
        </section>
    </div>
</template>
