<script setup lang="ts">
import { Check, Flame } from '@lucide/vue';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { useI18n } from '@/composables/useI18n';
import { intlLocale } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { StreakSummary } from '@/types';

/*
 * "Your streak" (spec 0005 §3.2): days in a row with some learning, and the
 * last seven days as a strip. Built on the Home side-card recipe (brand-50
 * header band, gold icon, cobalt title; HomeGoodToKnowCard). Every day is
 * marked by an icon and a label as well as colour (ACC-02).
 */
type Props = {
    streak: StreakSummary;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const { t } = useI18n();

// The short weekday in the interface language ("Mon", "الاثنين").
function weekday(date: string, fallback: string): string {
    const day = new Date(`${date}T00:00:00`);

    return Number.isNaN(day.getTime())
        ? fallback
        : day.toLocaleDateString(intlLocale(), { weekday: 'short' });
}

const message = computed((): string => {
    const { current, activeToday } = props.streak;

    if (current === 0) {
        return t('Finish one step today to start your streak.');
    }

    if (!activeToday) {
        return t('Practise today to keep your streak going.');
    }

    return current === 1
        ? t('Great start. Come back tomorrow to build it.')
        : t('You practised today. Keep it going!');
});
</script>

<template>
    <section
        :class="
            cn(
                'border-line bg-surface shadow-card overflow-hidden rounded-lg border',
                props.class,
            )
        "
        :aria-label="$t('Your streak')"
        data-test="streak-card"
    >
        <header class="bg-brand-50 flex h-[38px] items-center gap-3 ps-5 pe-4">
            <Flame
                class="text-gold size-[22px] shrink-0 fill-current stroke-[1.75]"
                aria-hidden="true"
            />
            <h2
                class="font-heading text-ink-cobalt text-[17px] leading-6 font-semibold"
            >
                {{ $t('Your streak') }}
            </h2>
            <span
                v-if="streak.best > streak.current"
                class="text-ink-slate ms-auto text-[12px]"
            >
                {{ $tc('Best: :count day|Best: :count days', streak.best) }}
            </span>
        </header>

        <div class="flex flex-col gap-3 px-5 pt-3 pb-4">
            <div class="flex items-center gap-3">
                <span
                    class="font-heading text-ink-cobalt text-[34px] leading-none font-bold"
                    >{{ streak.current }}</span
                >
                <div class="min-w-0">
                    <p class="text-ink text-[15px] leading-5 font-semibold">
                        {{ $tc('day in a row|days in a row', streak.current) }}
                    </p>
                    <p class="text-ink-slate text-[13px] leading-5">
                        {{ message }}
                    </p>
                </div>
            </div>

            <ol
                class="grid grid-cols-7 gap-1.5"
                :aria-label="$t('The last 7 days')"
            >
                <li
                    v-for="day in streak.week"
                    :key="day.date"
                    class="flex flex-col items-center gap-1"
                >
                    <span
                        :class="
                            cn(
                                'grid size-8 place-items-center rounded-full border text-[11px] font-semibold',
                                day.active
                                    ? 'bg-success border-success text-white'
                                    : 'border-line bg-app text-ink-faint',
                                day.today &&
                                    'ring-brand-600/25 ring-2 ring-offset-1',
                            )
                        "
                    >
                        <Check
                            v-if="day.active"
                            class="size-4 stroke-[3]"
                            aria-hidden="true"
                        />
                    </span>
                    <span
                        :class="
                            cn(
                                'text-[11px] leading-none',
                                day.today
                                    ? 'text-brand-700 font-semibold'
                                    : 'text-ink-slate',
                            )
                        "
                    >
                        {{
                            day.today
                                ? $t('Today')
                                : weekday(day.date, day.label)
                        }}
                    </span>
                    <span class="sr-only">{{
                        day.active ? $t('practised') : $t('no practice')
                    }}</span>
                </li>
            </ol>
        </div>
    </section>
</template>
