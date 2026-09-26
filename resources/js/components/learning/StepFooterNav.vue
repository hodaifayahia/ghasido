<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import type { RouteFormDefinition } from '@/wayfinder';

/*
 * Previous / Next row under a step (LESSON-03, LESSON-04; spec 0003 H.2),
 * measured on desginphotos/employ/photo_1 at 1280×853, 31px under the cards:
 *   left  "Back to Home"  210×56 at x 30 / y 728, line-tint fill, 14px radius,
 *         22px arrow at x 63, 18px semibold label at x 93;
 *   right "Start Lesson"  335×56 ending at x 1250, brand-600 fill, white
 *         20px semibold label centred with a 24px arrow 15px after it.
 * "Next" posts the step's completion (PROG-03) through the Wayfinder form
 * variant the page passes; a plain link is used when nothing is recorded.
 */
type Props = {
    prev?: { label: string; href: string } | null;
    next?: {
        label: string;
        href?: string;
        form?: RouteFormDefinition<'post'>;
    } | null;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const prevClass =
    'bg-line text-ink ease-brand hover:bg-line-strong/60 focus-visible:ring-brand-600/40 flex h-14 w-full items-center gap-2 rounded-lg ps-6 pe-5 text-lg leading-none font-semibold transition-colors duration-150 focus-visible:ring-3 focus-visible:outline-none active:scale-[.98] md:w-[210px] md:ps-[27px]';
const nextClass =
    'bg-brand-600 ease-brand hover:bg-brand-700 focus-visible:ring-brand-600/40 flex h-14 w-full items-center justify-center gap-[15px] rounded-lg px-6 text-xl leading-none font-semibold text-white md:ps-12 transition-colors duration-150 focus-visible:ring-3 focus-visible:outline-none active:scale-[.98] md:w-[335px]';
</script>

<template>
    <nav
        :aria-label="$t('Step navigation')"
        :class="
            cn(
                'mt-6 flex flex-col-reverse gap-3 md:mt-[31px] md:flex-row md:items-center md:justify-between',
                props.class,
            )
        "
    >
        <Link
            v-if="prev"
            :href="prev.href"
            :class="prevClass"
            data-test="step-previous-link"
        >
            <ArrowLeft
                class="size-[22px] shrink-0 stroke-[2.25]"
                aria-hidden="true"
            />
            {{ prev.label }}
        </Link>
        <span v-else aria-hidden="true" />

        <Form
            v-if="next?.form"
            v-bind="next.form"
            class="contents"
            v-slot="{ processing }"
        >
            <button
                type="submit"
                :disabled="processing"
                :class="cn(nextClass, 'disabled:opacity-70')"
                data-test="step-next-button"
            >
                {{ next.label }}
                <ArrowRight
                    class="size-6 shrink-0 stroke-[2.25]"
                    aria-hidden="true"
                />
            </button>
        </Form>
        <Link
            v-else-if="next?.href"
            :href="next.href"
            :class="nextClass"
            data-test="step-next-link"
        >
            {{ next.label }}
            <ArrowRight
                class="size-6 shrink-0 stroke-[2.25]"
                aria-hidden="true"
            />
        </Link>
    </nav>
</template>
