<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Check, Circle } from '@lucide/vue';
import { computed } from 'vue';
import CertificateSheet from '@/components/learning/CertificateSheet.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import { useI18n } from '@/composables/useI18n';
import type {
    CertificateEligibility,
    CertificateView,
    JourneyState,
} from '@/types';

/*
 * Certificate (JOURNEY-05, CERT-02..04; spec 0003 Part E): the sheet once
 * it is issued, otherwise the steps still to take, each with an icon and
 * words (ACC-02). No client mockup covers this page yet.
 */
type Props = {
    certificate: CertificateView | null;
    eligibility: CertificateEligibility;
    journey: JourneyState;
};

const props = defineProps<Props>();

const { t } = useI18n();

// With no published Pre-test for the learner's department the lessons are
// open from the start (JOURNEY-01), so there is no Pre-test step to list.
const hasPreTestStep =
    props.eligibility.preTestSubmitted || !props.journey.lessonsUnlocked;

const checklist = computed(() => [
    ...(hasPreTestStep
        ? [
              {
                  label: t('Take the Pre-test'),
                  done: () => props.eligibility.preTestSubmitted,
              },
          ]
        : []),
    {
        label: t('Complete every lesson (:completed of :total)', {
            completed: props.eligibility.lessonsCompleted,
            total: props.eligibility.lessonsTotal,
        }),
        done: () =>
            props.eligibility.lessonsTotal > 0 &&
            props.eligibility.lessonsCompleted >=
                props.eligibility.lessonsTotal,
    },
    {
        label: t('Take the Post-test'),
        done: () => props.eligibility.postTestSubmitted,
    },
]);
</script>

<template>
    <Head :title="$t('Certificate')" />
    <h1 class="sr-only">{{ $t('Certificate') }}</h1>

    <div class="flex min-w-0 flex-col gap-4 px-4 pt-5 pb-8 md:px-6">
        <PageHeader
            :title="$t('Certificate')"
            :description="
                certificate
                    ? $t('Issued for :course', {
                          course: certificate.course.title,
                      })
                    : $t(
                          'Your certificate is issued when your training is complete.',
                      )
            "
        />

        <CertificateSheet v-if="certificate" :certificate="certificate" />

        <section
            v-else
            class="border-line bg-surface shadow-card flex min-w-0 flex-col gap-3 rounded-lg border p-5"
            :aria-label="$t('What is left to do')"
        >
            <ul class="flex list-none flex-col gap-3">
                <li
                    v-for="item in checklist"
                    :key="item.label"
                    class="flex min-h-11 items-center gap-3"
                >
                    <span
                        :class="[
                            'grid size-8 shrink-0 place-items-center rounded-full',
                            item.done()
                                ? 'bg-success-tint text-success-text'
                                : 'bg-tint-grid text-ink-slate',
                        ]"
                    >
                        <Check
                            v-if="item.done()"
                            class="size-4 stroke-[3]"
                            aria-hidden="true"
                        />
                        <Circle v-else class="size-4" aria-hidden="true" />
                    </span>
                    <span class="text-ink text-base">
                        {{ item.label }}
                        <span class="text-ink-slate text-sm">
                            — {{ item.done() ? $t('done') : $t('to do') }}
                        </span>
                    </span>
                </li>
            </ul>
        </section>
    </div>
</template>
