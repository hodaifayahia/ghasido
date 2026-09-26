<script setup lang="ts">
import { Download, FileSpreadsheet, FileText, Mail, Users } from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref } from 'vue';
import { useCan } from '@/composables/useCan';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { exportMethod } from '@/routes/employees';
import { cn } from '@/lib/utils';
import type {
    EmployeeBulkAction,
    EmployeeFilters,
    EmployeeSelectOption,
} from '@/types';

type Props = {
    bulkActions: EmployeeSelectOption[];
    /** The current filters, so an export is exactly the list on screen. */
    filters: EmployeeFilters;
    /** The checked row ids. */
    selected: number[];
    busy?: boolean;
};

const props = withDefaults(defineProps<Props>(), { busy: false });

const emit = defineEmits<{
    bulk: [action: EmployeeBulkAction];
    remind: [];
}>();

const { can } = useCan();
const canManage = can('employees.manage');

const action = ref(props.bulkActions[0]?.value ?? 'select-action');

function onSelect(value: AcceptableValue): void {
    if (typeof value === 'string') {
        action.value = value;
    }
}

function isBulkAction(value: string): value is EmployeeBulkAction {
    return value === 'activate' || value === 'deactivate' || value === 'remind';
}

const canApply = computed(
    () =>
        !props.busy && props.selected.length > 0 && isBulkAction(action.value),
);

function apply(): void {
    if (isBulkAction(action.value) && canApply.value) {
        emit('bulk', action.value);
    }
}

// The export carries the filters as the page shows them (REP-03).
function exportUrl(format: 'xlsx' | 'csv'): string {
    const query: Record<string, string> = { format };

    if (props.filters.search !== '') {
        query.search = props.filters.search;
    }
    if (props.filters.hotel !== 'all-hotels') {
        query.hotel = props.filters.hotel;
    }
    if (props.filters.department !== 'all-departments') {
        query.department = props.filters.department;
    }
    if (props.filters.status !== 'all-statuses') {
        query.status = props.filters.status;
    }

    return exportMethod.url({ query });
}
</script>

<template>
    <section class="grid gap-3 xl:grid-cols-[314fr_309fr_259fr]">
        <article
            class="border-line bg-surface shadow-card rounded-lg border px-4 pt-3 pb-3.5"
        >
            <header class="flex items-center gap-2.5">
                <div
                    class="bg-ai/12 text-ai grid size-8 place-items-center rounded-full"
                >
                    <Users
                        class="size-4.5 fill-current stroke-[1.8]"
                        aria-hidden="true"
                    />
                </div>
                <h2
                    class="font-heading text-brand-800 text-[15px] font-semibold"
                >
                    {{ $t('Bulk Actions') }}
                </h2>
            </header>

            <div class="mt-3.5 flex flex-col gap-2 sm:flex-row">
                <Select
                    :model-value="action"
                    :disabled="!canManage"
                    @update:model-value="onSelect"
                >
                    <SelectTrigger
                        :aria-label="$t('Bulk action')"
                        data-test="employees-bulk-action"
                        class="border-line text-ink bg-surface h-9 flex-1 rounded-md px-3 text-[12.5px] shadow-none"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in bulkActions"
                            :key="option.value"
                            :value="option.value"
                            class="text-[13px]"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Button
                    type="button"
                    :disabled="!canManage || !canApply"
                    :class="
                        cn(
                            'text-surface h-9 min-w-[100px] rounded-md px-4 text-[12.5px] font-semibold shadow-none',
                            canApply
                                ? 'bg-brand-600 shadow-btn hover:bg-brand-700 active:scale-[.97]'
                                : 'bg-brand-100 hover:bg-brand-100 disabled:opacity-100',
                        )
                    "
                    data-test="apply-bulk-action-button"
                    @click="apply"
                >
                    {{ $t('Apply') }}
                </Button>
            </div>
        </article>

        <article
            class="border-line bg-surface shadow-card rounded-lg border px-4 pt-3 pb-3.5"
        >
            <header class="flex items-center gap-2.5">
                <div
                    class="bg-brand-100 text-brand-600 grid size-8 place-items-center rounded-full"
                >
                    <Download
                        class="size-4.5 stroke-[2.2]"
                        aria-hidden="true"
                    />
                </div>
                <div>
                    <h2
                        class="font-heading text-brand-800 text-[15px] font-semibold"
                    >
                        {{ $t('Export Employees') }}
                    </h2>
                    <p class="text-ink-slate mt-0.5 text-[12px] leading-4">
                        {{
                            $t('Download the employee list with progress data.')
                        }}
                    </p>
                </div>
            </header>

            <div class="mt-3.5 grid gap-2 sm:grid-cols-2">
                <a
                    :href="exportUrl('xlsx')"
                    download
                    data-test="export-employees-excel-link"
                    class="border-line hover:bg-excel-tint bg-surface text-excel focus-visible:border-brand-600 focus-visible:ring-brand-600/15 inline-flex min-h-10 items-center justify-center gap-2 rounded-md border px-4 text-[12.5px] font-semibold focus-visible:ring-3 focus-visible:outline-none"
                >
                    <span
                        class="bg-excel text-surface grid size-5 place-items-center rounded-[4px]"
                    >
                        <FileSpreadsheet class="size-3.5" aria-hidden="true" />
                    </span>
                    {{ $t('Export to Excel') }}
                </a>

                <a
                    :href="exportUrl('csv')"
                    download
                    data-test="export-employees-csv-link"
                    class="border-line hover:bg-brand-50 bg-surface text-brand-700 focus-visible:border-brand-600 focus-visible:ring-brand-600/15 inline-flex min-h-10 items-center justify-center gap-2 rounded-md border px-4 text-[12.5px] font-semibold focus-visible:ring-3 focus-visible:outline-none"
                >
                    <span
                        class="bg-brand-600 text-surface grid size-5 place-items-center rounded-[4px]"
                    >
                        <FileText class="size-3.5" aria-hidden="true" />
                    </span>
                    {{ $t('Export to CSV') }}
                </a>
            </div>
        </article>

        <article
            class="border-line bg-surface shadow-card rounded-lg border px-4 pt-3 pb-3.5"
        >
            <header class="flex items-center gap-2.5">
                <div
                    class="bg-brand-100 text-brand-600 grid size-8 place-items-center rounded-full"
                >
                    <Mail class="size-4.5" aria-hidden="true" />
                </div>
                <div>
                    <h2
                        class="font-heading text-brand-800 text-[15px] font-semibold"
                    >
                        {{ $t('Send Reminder') }}
                    </h2>
                    <p class="text-ink-slate mt-0.5 text-[12px] leading-4">
                        {{
                            $t(
                                'Send a training reminder to selected employees.',
                            )
                        }}
                    </p>
                </div>
            </header>

            <Button
                type="button"
                variant="outline"
                :disabled="!canManage || busy || selected.length === 0"
                :title="
                    selected.length === 0
                        ? $t('Select employees in the table first')
                        : $tc(
                              'Send a reminder to :count selected employee|Send a reminder to :count selected employees',
                              selected.length,
                          )
                "
                class="border-brand-200 text-brand-700 hover:bg-brand-50 bg-brand-50/45 mt-3.5 min-h-10 w-full rounded-md text-[12.5px] font-semibold shadow-none disabled:opacity-100"
                data-test="send-reminder-button"
                @click="emit('remind')"
            >
                {{ $t('Send Reminder') }}
            </Button>
        </article>
    </section>
</template>
