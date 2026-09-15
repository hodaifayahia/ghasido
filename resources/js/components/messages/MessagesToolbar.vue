<script setup lang="ts">
import { RotateCcw, Send } from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { reactive } from 'vue';
import type { HTMLAttributes } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { notifyComingSoon } from '@/lib/comingSoon';
import { cn } from '@/lib/utils';
import type { MessageFilters, MessageSelectOption } from '@/types';

type Props = {
    filters: MessageFilters;
    class?: HTMLAttributes['class'];
};

type FilterKey = 'hotel' | 'department' | 'consent' | 'activity';

const props = defineProps<Props>();

const values = reactive<Record<FilterKey, string>>({
    hotel: props.filters.hotel,
    department: props.filters.department,
    consent: props.filters.consent,
    activity: props.filters.activity,
});

const filterFields: Array<{
    key: FilterKey;
    label: string;
    options: MessageSelectOption[];
}> = [
    { key: 'hotel', label: 'Hotel', options: props.filters.hotels },
    {
        key: 'department',
        label: 'Department',
        options: props.filters.departments,
    },
    { key: 'consent', label: 'Consent', options: props.filters.consents },
    { key: 'activity', label: 'Activity', options: props.filters.activities },
];

function onSelect(target: FilterKey, value: AcceptableValue): void {
    if (typeof value === 'string') {
        values[target] = value;
    }
}

function resetFilters(): void {
    values.hotel = props.filters.hotel;
    values.department = props.filters.department;
    values.consent = props.filters.consent;
    values.activity = props.filters.activity;
}
</script>

<template>
    <div :class="cn('flex min-w-0 flex-col gap-2.5', props.class)">
        <div class="messages-filters-grid grid gap-2">
            <div
                v-for="field in filterFields"
                :key="field.key"
                class="border-line bg-surface shadow-card rounded-md border px-3 pt-[7px] pb-[5px]"
            >
                <p class="text-brand-900 text-[11px] leading-4 font-semibold">
                    {{ field.label }}
                </p>
                <Select
                    :model-value="values[field.key]"
                    @update:model-value="onSelect(field.key, $event)"
                >
                    <SelectTrigger
                        class="text-ink-indigo h-6 border-0 px-0 py-0 text-[12.5px] font-medium shadow-none focus-visible:ring-0"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in field.options"
                            :key="option.value"
                            :value="option.value"
                            class="text-[13px]"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <Button
                type="button"
                variant="outline"
                class="border-line text-brand-700 hover:bg-brand-50 shadow-card h-[52px] gap-2 rounded-md px-4 text-[12.5px] font-semibold"
                @click="resetFilters"
            >
                <RotateCcw class="size-4" aria-hidden="true" />
                Reset Filters
            </Button>
        </div>

        <div class="flex justify-end">
            <Button
                type="button"
                class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md px-4 text-[12.5px] font-semibold text-white"
                @click="notifyComingSoon('Send group reminder')"
            >
                <Send class="size-4" aria-hidden="true" />
                Send Group Reminder
            </Button>
        </div>
    </div>
</template>

<style scoped>
@media (min-width: 1280px) {
    .messages-filters-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr)) 176px;
    }
}
</style>
