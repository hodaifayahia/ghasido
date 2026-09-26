<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { GraduationCap } from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { computed } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { update } from '@/routes/learn/training-department';

/*
 * The manager's training department switcher (client decision 2026-09-23).
 * Reads the shared `trainingContext`, so it renders only for a manager who
 * has chosen a department; for a real employee the prop is null and this is
 * nothing. Changing the select re-scopes the manager's whole learner area.
 */
const page = usePage();

const context = computed(() => page.props.trainingContext);

const currentValue = computed((): string =>
    context.value?.currentDepartmentId != null
        ? String(context.value.currentDepartmentId)
        : '',
);

function onChange(value: AcceptableValue): void {
    if (typeof value !== 'string') {
        return;
    }

    const id = Number(value);

    if (!Number.isFinite(id) || id === context.value?.currentDepartmentId) {
        return;
    }

    router.post(
        update().url,
        { department_id: id },
        { preserveScroll: true, preserveState: false },
    );
}
</script>

<template>
    <div
        v-if="context && context.currentDepartmentId !== null"
        class="border-line bg-surface shadow-card flex items-center gap-3 rounded-lg border px-4 py-2.5"
    >
        <span
            class="bg-brand-50 text-brand-600 grid size-8 shrink-0 place-items-center rounded-lg"
        >
            <GraduationCap class="size-4" aria-hidden="true" />
        </span>
        <span class="text-ink-slate text-[13px] font-medium">
            {{ $t('Training in') }}
        </span>
        <Select :model-value="currentValue" @update:model-value="onChange">
            <SelectTrigger
                class="border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-9 w-[200px] rounded-sm text-[13px] shadow-none focus-visible:ring-3"
                data-test="training-department-switcher"
            >
                <SelectValue :placeholder="$t('Choose a department')" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem
                    v-for="department in context.departments"
                    :key="department.id"
                    :value="String(department.id)"
                >
                    {{ department.name }}
                </SelectItem>
            </SelectContent>
        </Select>
    </div>
</template>
