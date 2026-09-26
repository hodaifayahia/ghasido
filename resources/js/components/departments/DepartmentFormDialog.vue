<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref, watch } from 'vue';
import HotelsModal from '@/components/hotels/HotelsModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store, update } from '@/routes/departments';
import { tk } from '@/lib/i18n';
import type {
    DepartmentRecord,
    DepartmentSelectOption,
    DepartmentStatus,
} from '@/types';

type Props = {
    /** The department being edited, or null to create one. */
    department: DepartmentRecord | null;
    /** Hotels a new department may be scoped to (empty for a manager). */
    hotelOptions: DepartmentSelectOption[];
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    saved: [];
}>();

const editing = computed(() => props.department !== null);

// The Wayfinder form variant carries method spoofing for PATCH itself, so
// the form never builds a `_method` field by hand (AGENTS.md §5).
const action = computed(() =>
    props.department === null ? store.form() : update.form(props.department.id),
);

const statusOptions: Array<{ value: DepartmentStatus; label: string }> = [
    { value: 'active', label: tk('Active') },
    { value: 'review', label: tk('In Review') },
    { value: 'draft', label: tk('Draft') },
];

// The three selects are controlled so the hotel field can follow the scope;
// each posts through the hidden native select reka-ui renders inside a form.
const scope = ref<'shared' | 'hotel'>('shared');
const hotelId = ref('');
const status = ref<DepartmentStatus>('active');

watch(
    () => [props.department, open.value] as const,
    ([department]) => {
        scope.value = department?.scope ?? 'shared';
        hotelId.value =
            department?.hotelId !== null && department?.hotelId !== undefined
                ? String(department.hotelId)
                : (props.hotelOptions[0]?.value ?? '');
        status.value = department?.status ?? 'active';
    },
    { immediate: true },
);

function onScope(value: AcceptableValue): void {
    if (value === 'shared' || value === 'hotel') {
        scope.value = value;
    }
}

function onHotel(value: AcceptableValue): void {
    if (typeof value === 'string') {
        hotelId.value = value;
    }
}

function onStatus(value: AcceptableValue): void {
    if (value === 'active' || value === 'review' || value === 'draft') {
        status.value = value;
    }
}

function onSuccess(): void {
    open.value = false;
    emit('saved');
}

const labelClass = 'text-brand-900 text-[12px] font-semibold tracking-[0.02em]';
const inputClass =
    'border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 rounded-sm text-[13px] shadow-none focus-visible:ring-3';
const triggerClass =
    'border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 w-full rounded-sm px-3 text-[13px] shadow-none focus-visible:ring-3';
</script>

<template>
    <HotelsModal
        v-model:open="open"
        :title="
            editing
                ? $t('Edit :name', {
                      name: department?.name ?? $t('department'),
                  })
                : $t('Add Department')
        "
        :description="
            editing
                ? $t(
                      'Update the name, focus line and editorial status. The scope is fixed once a department exists.',
                  )
                : $t(
                      'A shared department is available to every hotel; a hotel-specific one belongs to that hotel only.',
                  )
        "
    >
        <Form
            :key="department?.id ?? 'create'"
            v-bind="action"
            :options="{ preserveScroll: true, preserveState: true }"
            reset-on-success
            class="mt-2 grid gap-4"
            v-slot="{ errors, processing }"
            @success="onSuccess"
        >
            <div class="grid gap-1.5">
                <Label for="department-name" :class="labelClass">
                    {{ $t('Department name') }}
                </Label>
                <Input
                    id="department-name"
                    name="name"
                    type="text"
                    :default-value="department?.name ?? ''"
                    required
                    maxlength="120"
                    :aria-invalid="
                        errors.name || errors.slug ? true : undefined
                    "
                    data-test="department-name-input"
                    :class="inputClass"
                />
                <InputError :message="errors.name ?? errors.slug" />
            </div>

            <div class="grid gap-1.5">
                <Label for="department-focus" :class="labelClass">
                    {{ $t('Focus (one line)') }}
                </Label>
                <Input
                    id="department-focus"
                    name="focus"
                    type="text"
                    :default-value="department?.focus ?? ''"
                    maxlength="255"
                    :placeholder="
                        $t('Guest arrival, greeting and check-in language.')
                    "
                    :aria-invalid="errors.focus ? true : undefined"
                    data-test="department-focus-input"
                    :class="inputClass"
                />
                <InputError :message="errors.focus" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div v-if="!editing" class="grid gap-1.5">
                    <Label for="department-scope" :class="labelClass">
                        {{ $t('Scope') }}
                    </Label>
                    <Select
                        name="scope"
                        :model-value="scope"
                        required
                        @update:model-value="onScope"
                    >
                        <SelectTrigger
                            id="department-scope"
                            data-test="department-scope-select"
                            :class="triggerClass"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent class="border-line shadow-pop">
                            <SelectItem value="shared" class="text-[13px]">
                                {{ $t('Shared Across Hotels') }}
                            </SelectItem>
                            <SelectItem
                                value="hotel"
                                class="text-[13px]"
                                :disabled="hotelOptions.length === 0"
                            >
                                {{ $t('Hotel Specific') }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.scope" />
                </div>

                <div v-else class="grid gap-1.5">
                    <Label :class="labelClass">{{ $t('Scope') }}</Label>
                    <p
                        class="border-line bg-tint-header text-ink-slate flex h-10 items-center rounded-sm border px-3 text-[13px]"
                        data-test="department-scope-locked"
                    >
                        {{ department?.scopeLabel }}
                    </p>
                </div>

                <div class="grid gap-1.5">
                    <Label for="department-status" :class="labelClass">
                        {{ $t('Status') }}
                    </Label>
                    <Select
                        name="status"
                        :model-value="status"
                        required
                        @update:model-value="onStatus"
                    >
                        <SelectTrigger
                            id="department-status"
                            data-test="department-status-select"
                            :class="triggerClass"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent class="border-line shadow-pop">
                            <SelectItem
                                v-for="option in statusOptions"
                                :key="option.value"
                                :value="option.value"
                                class="text-[13px]"
                            >
                                {{ $t(option.label) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.status" />
                </div>
            </div>

            <div v-if="!editing && scope === 'hotel'" class="grid gap-1.5">
                <Label for="department-hotel" :class="labelClass">
                    {{ $t('Hotel') }}
                </Label>
                <Select
                    name="hotel_id"
                    :model-value="hotelId"
                    required
                    @update:model-value="onHotel"
                >
                    <SelectTrigger
                        id="department-hotel"
                        data-test="department-hotel-select"
                        :class="triggerClass"
                    >
                        <SelectValue :placeholder="$t('Choose a hotel')" />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in hotelOptions"
                            :key="option.value"
                            :value="option.value"
                            class="text-[13px]"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.hotel_id" />
            </div>

            <div
                class="mt-1 flex flex-col-reverse gap-2 md:flex-row md:justify-end"
            >
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-10 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                    data-test="cancel-department-button"
                    @click="open = false"
                >
                    {{ $t('Cancel') }}
                </Button>
                <Button
                    type="submit"
                    :disabled="processing"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97]"
                    data-test="save-department-button"
                >
                    {{ editing ? $t('Save changes') : $t('Create department') }}
                </Button>
            </div>
        </Form>
    </HotelsModal>
</template>
