<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { JourneyState } from '@/types';

/*
 * The four-stage journey stepper on Home (JOURNEY-01..05; spec 0003 Part E),
 * measured on desginphotos/employ/photo_20 at 1280×853: four columns
 * 138px apart starting 8px after the content edge (circle centres x 293,
 * 431, 569, 707; circle centre y 117), the active circle 38px brand-600
 * with a white number, the others 28px in the sampled step grey; labels
 * 14px right under the row, active brand-600 semibold, the rest ink-slate;
 * a 2px connector joins the circles. Done stages get the brand ring + check.
 */
type Props = {
    journey: JourneyState;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const stages = [
    tk('Pre-test'),
    tk('Training'),
    tk('Post-test'),
    tk('Certificate'),
];

// The first stage the learner has not finished is the active one.
const active = computed((): number => {
    const j = props.journey;

    if (!j.lessonsUnlocked) {
        return 1;
    }

    if (!j.postTestUnlocked) {
        return 2;
    }

    if (!j.certificateAvailable) {
        return 3;
    }

    return 4;
});
</script>

<template>
    <ol
        :aria-label="$t('Your training journey')"
        :class="
            cn(
                'relative z-10 grid w-full max-w-[484px] list-none grid-cols-4',
                props.class,
            )
        "
    >
        <li
            aria-hidden="true"
            class="bg-tint-connector absolute inset-x-[69px] top-[18px] h-0.5"
        />
        <li
            v-for="(stage, index) in stages"
            :key="stage"
            class="relative flex flex-col items-center"
            :aria-current="index + 1 === active ? 'step' : undefined"
        >
            <span class="grid h-[38px] place-items-center">
                <span
                    :class="
                        cn(
                            'grid place-items-center rounded-full leading-none font-semibold',
                            index + 1 === active
                                ? 'bg-brand-600 size-[38px] text-[17px] text-white'
                                : index + 1 < active
                                  ? 'border-brand-600 bg-surface text-brand-600 size-7 border-2'
                                  : 'bg-step-idle size-7 text-[14px] text-white',
                        )
                    "
                >
                    <Check
                        v-if="index + 1 < active"
                        class="size-3.5 stroke-[3]"
                        aria-hidden="true"
                    />
                    <template v-else>{{ index + 1 }}</template>
                </span>
            </span>
            <span
                :class="
                    cn(
                        'text-xs leading-4 whitespace-nowrap md:text-sm',
                        index + 1 === active
                            ? 'text-brand-600 font-semibold'
                            : 'text-ink-slate font-medium',
                    )
                "
            >
                {{ $t(stage) }}
                <span v-if="index + 1 < active" class="sr-only">{{
                    $t(', done')
                }}</span>
            </span>
        </li>
    </ol>
</template>
