<script setup lang="ts">
import { Bell, Clock, FileText, RotateCcw, Send } from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { reactive, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type {
    MessageFilterValues,
    MessageFilters,
    MessageSelectOption,
} from '@/types';

type Props = {
    filters: MessageFilters;
    /** Whether the signed in user may press Send (ReminderPolicy::send). */
    canSend: boolean;
    /** How many employees are selected, for the button label. */
    selectedCount: number;
    /** The counts on the Templates, Rules and Log buttons. */
    templatesCount: number;
    rulesCount: number;
    logTotal: number;
    class?: HTMLAttributes['class'];
};

type FilterKey = 'hotel' | 'department' | 'consent' | 'activity';

const props = defineProps<Props>();

const emit = defineEmits<{
    /** A filter changed: the page reloads the recipients (REM-02). */
    filter: [values: MessageFilterValues];
    /** Send Group Reminder: the page opens the send dialog. */
    send: [];
    /** The three management modals next to Send (REM-03, REM-04, REM-06). */
    openTemplates: [];
    openRules: [];
    openLog: [];
}>();

type ManageButton = {
    key: 'templates' | 'rules' | 'log';
    label: string;
    count: () => number;
    icon: typeof FileText;
    iconClass: string;
    test: string;
    open: () => void;
};

const manageButtons: ManageButton[] = [
    {
        key: 'templates',
        label: tk('Reminder Templates'),
        count: () => props.templatesCount,
        icon: FileText,
        iconClass: 'text-brand-600',
        test: 'open-templates-button',
        open: () => emit('openTemplates'),
    },
    {
        key: 'rules',
        label: tk('Automation Rules'),
        count: () => props.rulesCount,
        icon: Bell,
        iconClass: 'text-ai',
        test: 'open-rules-button',
        open: () => emit('openRules'),
    },
    {
        key: 'log',
        label: tk('Reminder Log'),
        count: () => props.logTotal,
        icon: Clock,
        iconClass: 'text-warning',
        test: 'open-full-log-button',
        open: () => emit('openLog'),
    },
];

const values = reactive<Record<FilterKey, string>>({
    hotel: props.filters.hotel,
    department: props.filters.department,
    consent: props.filters.consent,
    activity: props.filters.activity,
});

// The server is the source of truth for the filters; keep the controls in
// step when it answers (a Reset, a back button, a shared link).
watch(
    () => props.filters,
    (filters) => {
        values.hotel = filters.hotel;
        values.department = filters.department;
        values.consent = filters.consent;
        values.activity = filters.activity;
    },
    { deep: true },
);

const filterFields: Array<{
    key: FilterKey;
    label: string;
    ariaLabel: string;
    options: () => MessageSelectOption[];
}> = [
    {
        key: 'hotel',
        label: tk('Hotel'),
        ariaLabel: tk('Filter by hotel'),
        options: () => props.filters.hotels,
    },
    {
        key: 'department',
        label: tk('Department'),
        ariaLabel: tk('Filter by department'),
        options: () => props.filters.departments,
    },
    {
        key: 'consent',
        label: tk('Consent'),
        ariaLabel: tk('Filter by consent'),
        options: () => props.filters.consents,
    },
    {
        key: 'activity',
        label: tk('Activity'),
        ariaLabel: tk('Filter by activity'),
        options: () => props.filters.activities,
    },
];

function current(): MessageFilterValues {
    return {
        hotel: values.hotel,
        department: values.department,
        consent: values.consent,
        activity: values.activity,
        search: props.filters.search,
    };
}

function onSelect(target: FilterKey, value: AcceptableValue): void {
    if (typeof value !== 'string') {
        return;
    }

    values[target] = value;
    emit('filter', current());
}

/**
 * Back to the screen's defaults: every hotel the user may see, every
 * department, consent granted, inactive employees, no search.
 */
function resetFilters(): void {
    values.hotel = props.filters.hotels[0]?.value ?? 'all-hotels';
    values.department = 'all-departments';
    values.consent = 'consent-granted';
    values.activity = props.filters.activities[0]?.value ?? 'inactive-5-days';

    emit('filter', { ...current(), search: '' });
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
                    {{ $t(field.label) }}
                </p>
                <Select
                    :model-value="values[field.key]"
                    @update:model-value="onSelect(field.key, $event)"
                >
                    <SelectTrigger
                        :aria-label="$t(field.ariaLabel)"
                        :data-test="`messages-${field.key}-filter`"
                        class="text-ink-indigo h-6 border-0 px-0 py-0 text-[12.5px] font-medium shadow-none focus-visible:ring-0"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in field.options()"
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
                data-test="reset-messages-filters-button"
                @click="resetFilters"
            >
                <RotateCcw class="size-4" aria-hidden="true" />
                {{ $t('Reset Filters') }}
            </Button>
        </div>

        <!-- Templates, rules and the log open as modals beside Send (REM-03, REM-04, REM-06). -->
        <div
            class="grid grid-cols-3 gap-2 md:flex md:flex-wrap md:items-center md:justify-end"
        >
            <Button
                v-for="button in manageButtons"
                :key="button.key"
                type="button"
                variant="outline"
                class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-auto min-h-11 min-w-0 flex-wrap content-start justify-start gap-x-2 gap-y-1 rounded-md px-2.5 py-2 text-start text-[12.5px] leading-4 font-semibold whitespace-normal shadow-none md:h-10 md:min-h-0 md:flex-nowrap md:justify-center md:px-3 md:py-0 md:whitespace-nowrap"
                :data-test="button.test"
                @click="button.open()"
            >
                <component
                    :is="button.icon"
                    :class="cn('size-4 shrink-0', button.iconClass)"
                    aria-hidden="true"
                />
                <!-- Phone: icon and count on the first line, the label wraps below. -->
                <span
                    class="order-last min-w-0 basis-full md:order-none md:basis-auto"
                    >{{ $t(button.label) }}</span
                >
                <span
                    class="bg-brand-50 text-brand-700 rounded-pill ms-auto inline-flex min-w-5 shrink-0 items-center justify-center px-1.5 text-[11px] leading-5 font-semibold md:ms-0"
                >
                    {{ button.count() }}
                </span>
            </Button>

            <Button
                v-if="canSend"
                type="button"
                class="bg-brand-600 shadow-btn hover:bg-brand-700 col-span-3 h-11 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97] md:h-10"
                data-test="send-group-reminder-button"
                @click="emit('send')"
            >
                <Send class="size-4" aria-hidden="true" />
                {{
                    selectedCount > 0
                        ? $t('Send Reminder (:count)', {
                              count: selectedCount,
                          })
                        : $t('Send Group Reminder')
                }}
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
