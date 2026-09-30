<script setup lang="ts">
import { Check, X } from '@lucide/vue';
import { computed } from 'vue';
import ProgressBar from '@/components/data/ProgressBar.vue';
import {
    progressTone,
    statusText,
    statusTone,
} from '@/components/employees/employeeStatus';
import HotelsModal from '@/components/hotels/HotelsModal.vue';
import { useI18n } from '@/composables/useI18n';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { EmployeeRecord } from '@/types';

type Props = {
    employee: EmployeeRecord | null;
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });
const { t } = useI18n();

type Row = { label: string; value: string };

function score(value: number | null): string {
    return value === null ? t('Not taken') : `${value}%`;
}

const rows = computed<Row[]>(() => {
    const employee = props.employee;

    if (employee === null) {
        return [];
    }

    return [
        { label: tk('Username'), value: employee.username },
        { label: tk('Hotel'), value: employee.hotel },
        { label: tk('Department'), value: employee.department },
        {
            label: tk('Level'),
            value: employee.level ?? t('Level not chosen'),
        },
        { label: tk('Email'), value: employee.email },
        { label: tk('Pre-test'), value: score(employee.preTestScore) },
        { label: tk('Post-test'), value: score(employee.postTestScore) },
        { label: tk('Last login'), value: employee.lastLogin },
        { label: tk('Last activity'), value: employee.lastActivity },
        { label: tk('Training started'), value: employee.trainingStarted },
        { label: tk('Training completed'), value: employee.trainingCompleted },
        {
            label: tk('Participant code'),
            value: employee.participantCode ?? '-',
        },
    ];
});
</script>

<template>
    <HotelsModal
        v-model:open="open"
        :title="employee?.name ?? $t('Employee')"
        :description="
            $t('Progress, test results, activity and consent for this account.')
        "
    >
        <div v-if="employee" class="mt-2 grid gap-4">
            <div class="flex flex-wrap items-center gap-3">
                <span
                    :class="
                        cn(
                            'rounded-pill inline-flex min-h-6 items-center justify-center px-3 text-[11.5px] font-semibold',
                            statusTone[employee.status],
                        )
                    "
                >
                    {{ $t(statusText[employee.status]) }}
                </span>
                <span class="text-ink-slate text-[12.5px]">
                    {{
                        employee.accountStatus === 'active'
                            ? $t('Account active')
                            : $t('Account inactive')
                    }}
                </span>
            </div>

            <div>
                <div
                    class="text-brand-900 mb-1.5 flex items-center justify-between text-[12.5px] font-semibold"
                >
                    <span>{{ $t('Progress') }}</span>
                    <span>
                        {{ employee.progress }}% ·
                        {{
                            $t(':completed of :total lessons', {
                                completed: employee.lessonsCompleted,
                                total: employee.lessonsTotal,
                            })
                        }}
                    </span>
                </div>
                <ProgressBar
                    :value="employee.progress"
                    :tone="progressTone[employee.status]"
                    :label="$t(':name progress', { name: employee.name })"
                    class="h-2"
                />
            </div>

            <dl class="grid gap-x-6 gap-y-2 text-[13px] sm:grid-cols-2">
                <div
                    v-for="row in rows"
                    :key="row.label"
                    class="border-line flex items-baseline justify-between gap-3 border-b pb-1.5"
                >
                    <dt class="text-ink-slate shrink-0">{{ $t(row.label) }}</dt>
                    <dd class="text-brand-900 truncate text-end font-medium">
                        {{ row.value }}
                    </dd>
                </div>
                <div
                    class="border-line flex items-center justify-between gap-3 border-b pb-1.5"
                >
                    <dt class="text-ink-slate shrink-0">
                        {{ $t('Reminder emails') }}
                    </dt>
                    <dd
                        :class="
                            cn(
                                'flex items-center gap-1.5 font-medium',
                                employee.emailConsent
                                    ? 'text-success-text'
                                    : 'text-danger-text',
                            )
                        "
                    >
                        <component
                            :is="employee.emailConsent ? Check : X"
                            class="size-4"
                            aria-hidden="true"
                        />
                        {{
                            employee.emailConsent
                                ? $t('Consented')
                                : $t('No consent')
                        }}
                    </dd>
                </div>
            </dl>
        </div>
    </HotelsModal>
</template>
