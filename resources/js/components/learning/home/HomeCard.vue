<script setup lang="ts">
import { Clock, List, Lock, Target } from '@lucide/vue';
import type { Component, HTMLAttributes } from 'vue';
import SolidBarsIcon from '@/components/icons/SolidBarsIcon.vue';
import { cn } from '@/lib/utils';
import type { PreTestFactIcon } from '@/types';
import MeaningText from '@/components/learning/meaning/MeaningText.vue';

/*
 * The big white card on Home (JOURNEY-01, JOURNEY-02; spec 0003 Part E),
 * measured on desginphotos/employ/photo_20 at 1280×853: 484px wide from
 * x 216, y 186, 16px padding with the copy a further 16px in (x 248):
 *   eyebrow 12px semibold, +0.18em, cap top y 204;
 *   heading 34px Poppins Bold on 40px lines (cap tops y 243 / 283);
 *   paragraphs 18px on 25px lines (y 334), 6px apart;
 *   hairline at y 444; fact rows 50px apart (label 15px semibold, cap top
 *   y 466; detail 14px; 22px brand icon 33px before the text);
 *   primary button 451×50 at y 731 spanning the padding; the secondary
 *   link 14px underlined, centred, cap top y 796.
 * The pre-test intro and the "continue" variant both render through it.
 */
type Fact = { icon: PreTestFactIcon; label: string; text: string };

type Props = {
    eyebrow: string;
    heading: string;
    paragraphs: string[];
    /** A phrase inside a paragraph to set in bold, as the mockup does. */
    emphasis?: string | null;
    facts: Fact[];
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), { emphasis: null });

const icons: Record<PreTestFactIcon, { component: Component; class: string }> =
    {
        clock: { component: Clock, class: 'stroke-[2.25]' },
        list: { component: List, class: 'stroke-[2.5]' },
        target: { component: Target, class: 'stroke-[2.25]' },
        lock: { component: Lock, class: 'stroke-[2.25] [&>rect]:fill-current' },
        chart: { component: SolidBarsIcon, class: '' },
    };

/** Split a paragraph around the emphasised phrase, when it holds it. */
function parts(paragraph: string): { text: string; bold: boolean }[] {
    const phrase = props.emphasis;

    if (!phrase || !paragraph.includes(phrase)) {
        return [{ text: paragraph, bold: false }];
    }

    const [before, ...rest] = paragraph.split(phrase);

    return [
        { text: before ?? '', bold: false },
        { text: phrase, bold: true },
        { text: rest.join(phrase), bold: false },
    ].filter((part) => part.text !== '');
}
</script>

<template>
    <section
        :class="
            cn(
                'border-line bg-surface shadow-card flex min-w-0 flex-col rounded-lg border p-4 pt-[15px] pb-[14px]',
                props.class,
            )
        "
        :aria-label="heading"
    >
        <div class="px-4">
            <p
                class="text-ink-slate text-xs leading-4 font-semibold tracking-[0.18em] uppercase"
            >
                {{ eyebrow }}
            </p>
            <MeaningText
                as="h2"
                :text="heading"
                class="font-heading text-ink-cobalt text-[35px] leading-[41px] font-bold tracking-[-0.02em]"
                wrapper-class="mt-[11px]"
            />
            <MeaningText
                v-for="(paragraph, index) in paragraphs"
                :key="index"
                :text="paragraph"
                class="text-ink-dusk text-[17.5px] leading-[25px]"
                :wrapper-class="index === 0 ? 'mt-[11px]' : 'mt-[9px]'"
            >
                <template v-for="(part, i) in parts(paragraph)" :key="i">
                    <strong v-if="part.bold" class="font-semibold">{{
                        part.text
                    }}</strong>
                    <template v-else>{{ part.text }}</template>
                </template>
            </MeaningText>

            <hr class="border-line mt-2.5" />

            <ul class="mt-[17px] flex list-none flex-col gap-[11px]">
                <li
                    v-for="fact in facts"
                    :key="fact.label"
                    class="flex gap-[33px]"
                >
                    <span
                        class="text-brand-900 grid size-6 shrink-0 place-items-center pt-0.5"
                    >
                        <component
                            :is="icons[fact.icon]?.component ?? Clock"
                            :class="cn('size-[22px]', icons[fact.icon]?.class)"
                            aria-hidden="true"
                        />
                    </span>
                    <span class="min-w-0">
                        <span
                            class="text-ink-dusk block text-sm leading-5 font-semibold"
                        >
                            {{ fact.label }}
                        </span>
                        <MeaningText
                            as="span"
                            :text="fact.text"
                            class="text-ink-slate block text-[12.5px] leading-[18px]"
                        />
                    </span>
                </li>
            </ul>
        </div>

        <div class="mt-[19px]">
            <slot name="primary" />
        </div>
        <div class="mt-[11px] text-center">
            <slot name="secondary" />
        </div>
    </section>
</template>
