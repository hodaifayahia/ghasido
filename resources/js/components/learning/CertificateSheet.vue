<script setup lang="ts">
import { Printer } from '@lucide/vue';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { cn } from '@/lib/utils';
import type { CertificateView } from '@/types';

/*
 * The print-ready certificate (CERT-01..03, CERT-07; spec 0003 Part E): the
 * client's logo, the type, the employee, course, hotel and department, the
 * issue date and the verification id. The PDF package is a pending
 * decision, so the browser's print is the download for now.
 */
type Props = {
    certificate: CertificateView;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const issued = computed(() =>
    new Date(props.certificate.issuedAt).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }),
);

function print(): void {
    window.print();
}
</script>

<template>
    <div :class="cn('flex min-w-0 flex-col gap-4', props.class)">
        <section
            class="border-brand-200 bg-surface shadow-card flex flex-col items-center gap-6 rounded-lg border-4 px-6 py-10 text-center md:px-12 md:py-14 print:border-2 print:shadow-none"
            aria-label="Certificate"
        >
            <AppLogo />
            <p
                class="text-ink-slate text-sm font-semibold tracking-[0.2em] uppercase"
            >
                Certificate of {{ certificate.typeLabel }}
            </p>
            <p class="text-ink-graphite text-lg">This certifies that</p>
            <p
                class="font-heading text-ink-royal text-[34px] leading-10 font-bold tracking-[-0.02em]"
            >
                {{ certificate.employee.name }}
            </p>
            <p class="text-ink-graphite max-w-xl text-lg leading-7">
                <template v-if="certificate.type === 'completion'">
                    has successfully completed
                </template>
                <template v-else>has taken part in</template>
                <strong class="text-ink font-semibold">
                    {{ certificate.course.title }}
                </strong>
                <template v-if="certificate.department">
                    for the {{ certificate.department }} department
                </template>
                <template v-if="certificate.hotel">
                    at {{ certificate.hotel }}
                </template>
            </p>
            <p class="text-ink-slate text-sm">Issued {{ issued }}</p>
            <p class="text-ink-faint font-mono text-xs">
                Verification ID {{ certificate.verificationId }}
            </p>
        </section>

        <div class="flex justify-end print:hidden">
            <button
                type="button"
                class="bg-brand-600 font-heading shadow-btn hover:bg-brand-700 focus-visible:ring-brand-600/40 inline-flex h-11 items-center gap-2 rounded-md px-5 text-sm font-semibold text-white focus-visible:ring-3 focus-visible:outline-none"
                data-test="print-certificate-button"
                @click="print"
            >
                <Printer class="size-5" aria-hidden="true" />
                Print or save as PDF
            </button>
        </div>
    </div>
</template>
