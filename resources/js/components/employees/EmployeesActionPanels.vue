<script setup lang="ts">
import { Download, FileSpreadsheet, FileText, Mail, Users } from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { EmployeeSelectOption } from '@/types';

type Props = {
    bulkActions: EmployeeSelectOption[];
};

const props = defineProps<Props>();

const action = ref(props.bulkActions[0]?.value ?? 'select-action');

function onSelect(value: AcceptableValue): void {
    if (typeof value === 'string') {
        action.value = value;
    }
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
                    Bulk Actions
                </h2>
            </header>

            <div class="mt-3.5 flex flex-col gap-2 sm:flex-row">
                <Select :model-value="action" @update:model-value="onSelect">
                    <SelectTrigger
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
                    class="bg-brand-100 text-surface hover:bg-brand-200 h-9 min-w-[100px] rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                >
                    Apply
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
                        Export Employees
                    </h2>
                    <p class="text-ink-slate mt-0.5 text-[12px] leading-4">
                        Download the employee list with progress data.
                    </p>
                </div>
            </header>

            <div class="mt-3.5 grid gap-2 sm:grid-cols-2">
                <button
                    type="button"
                    class="border-line hover:bg-excel-tint bg-surface text-excel inline-flex min-h-10 items-center justify-center gap-2 rounded-md border px-4 text-[12.5px] font-semibold"
                >
                    <span
                        :class="
                            cn(
                                'bg-excel text-surface grid size-5 place-items-center rounded-[4px]',
                            )
                        "
                    >
                        <FileSpreadsheet class="size-3.5" aria-hidden="true" />
                    </span>
                    Export to Excel
                </button>

                <button
                    type="button"
                    class="border-line hover:bg-brand-50 bg-surface text-brand-700 inline-flex min-h-10 items-center justify-center gap-2 rounded-md border px-4 text-[12.5px] font-semibold"
                >
                    <span
                        class="bg-brand-600 text-surface grid size-5 place-items-center rounded-[4px]"
                    >
                        <FileText class="size-3.5" aria-hidden="true" />
                    </span>
                    Export to CSV
                </button>
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
                        Send Reminder
                    </h2>
                    <p class="text-ink-slate mt-0.5 text-[12px] leading-4">
                        Send a training reminder to selected employees.
                    </p>
                </div>
            </header>

            <Button
                type="button"
                variant="outline"
                class="border-brand-200 text-brand-700 hover:bg-brand-50 bg-brand-50/45 mt-3.5 min-h-10 w-full rounded-md text-[12.5px] font-semibold shadow-none"
            >
                Send Reminder
            </Button>
        </article>
    </section>
</template>
