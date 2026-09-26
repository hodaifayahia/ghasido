<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Form } from '@inertiajs/vue3';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref, watch } from 'vue';
import HotelsModal from '@/components/hotels/HotelsModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { update } from '@/routes/employees';
import type { EmployeeCreateForm, EmployeeRecord } from '@/types';

type Props = {
    /** The employee being edited (null while nothing is selected). */
    employee: EmployeeRecord | null;
    /** The hotels and their seat-quota departments (SUB-01). */
    createForm: EmployeeCreateForm;
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    saved: [];
}>();

// Controlled selects and checkbox, joined to the DOM fields through the
// form's transform; the text fields are uncontrolled (name attributes).
const hotel = ref('');
const department = ref('');
const accountStatus = ref('active');
const allowReminderEmails = ref(false);

watch(
    () => [props.employee, open.value] as const,
    ([employee]) => {
        if (employee === null) {
            return;
        }

        hotel.value = employee.hotelId === null ? '' : String(employee.hotelId);
        department.value =
            employee.departmentId === null ? '' : String(employee.departmentId);
        accountStatus.value = employee.accountStatus;
        allowReminderEmails.value = employee.emailConsent;
    },
    { immediate: true },
);

const departments = computed(
    () => props.createForm.departmentsByHotel[hotel.value] ?? [],
);

function onSelect(
    target: 'hotel' | 'department' | 'accountStatus',
    value: AcceptableValue,
): void {
    if (typeof value !== 'string') {
        return;
    }

    if (target === 'hotel') {
        hotel.value = value;

        if (
            !departments.value.some(
                (option) => option.value === department.value,
            )
        ) {
            department.value = departments.value[0]?.value ?? '';
        }

        return;
    }

    if (target === 'department') {
        department.value = value;
        return;
    }

    accountStatus.value = value;
}

// The Wayfinder form variant carries method spoofing for PATCH itself, so
// the form never builds a `_method` field by hand (AGENTS.md §5).
const action = computed(() => update.form(props.employee?.id ?? 0));

type FormData = Record<string, FormDataConvertible>;

function transform(data: FormData): FormData {
    return {
        ...data,
        hotel_id: hotel.value,
        department_id: department.value,
        status: accountStatus.value,
        allow_reminder_emails: allowReminderEmails.value,
    };
}

function onSuccess(): void {
    open.value = false;
    emit('saved');
}

const fieldClass =
    'border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 rounded-sm text-[13px] shadow-none focus-visible:ring-3';

const labelClass = 'text-brand-900 text-[12px] font-semibold tracking-[0.02em]';

const triggerClass =
    'border-line text-ink bg-surface h-10 rounded-sm px-3 text-[13px] shadow-none';
</script>

<template>
    <HotelsModal
        v-model:open="open"
        :title="$t('Edit :name', { name: employee?.name ?? $t('employee') })"
        :description="
            $t(
                'Update the account details. Leave the password blank to keep the current one.',
            )
        "
    >
        <Form
            v-if="employee"
            :key="employee.id"
            v-bind="action"
            :transform="transform"
            :options="{ preserveScroll: true, preserveState: true }"
            class="mt-2 grid gap-4"
            v-slot="{ errors, processing }"
            @success="onSuccess"
        >
            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-1.5 md:col-span-2">
                    <Label
                        :for="`edit-name-${employee.id}`"
                        :class="labelClass"
                    >
                        {{ $t('Full name') }}
                    </Label>
                    <Input
                        :id="`edit-name-${employee.id}`"
                        name="name"
                        :default-value="employee.name"
                        required
                        :aria-invalid="errors.name ? true : undefined"
                        data-test="edit-employee-name-input"
                        :class="fieldClass"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-1.5">
                    <Label
                        :for="`edit-username-${employee.id}`"
                        :class="labelClass"
                    >
                        {{ $t('Username') }}
                    </Label>
                    <Input
                        :id="`edit-username-${employee.id}`"
                        name="username"
                        :default-value="employee.username"
                        autocapitalize="none"
                        spellcheck="false"
                        required
                        :aria-invalid="errors.username ? true : undefined"
                        data-test="edit-employee-username-input"
                        :class="fieldClass"
                    />
                    <InputError :message="errors.username" />
                </div>

                <div class="grid gap-1.5">
                    <Label
                        :for="`edit-password-${employee.id}`"
                        :class="labelClass"
                    >
                        {{ $t('New password (optional)') }}
                    </Label>
                    <Input
                        :id="`edit-password-${employee.id}`"
                        name="password"
                        type="password"
                        autocomplete="new-password"
                        :placeholder="$t('Keep current')"
                        :aria-invalid="errors.password ? true : undefined"
                        data-test="edit-employee-password-input"
                        :class="fieldClass"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-1.5">
                    <Label
                        :for="`edit-hotel-${employee.id}`"
                        :class="labelClass"
                    >
                        {{ $t('Hotel') }}
                    </Label>
                    <Select
                        :model-value="hotel"
                        @update:model-value="onSelect('hotel', $event)"
                    >
                        <SelectTrigger
                            :id="`edit-hotel-${employee.id}`"
                            data-test="edit-employee-hotel-select"
                            :class="triggerClass"
                        >
                            <SelectValue :placeholder="$t('Select hotel')" />
                        </SelectTrigger>
                        <SelectContent class="border-line shadow-pop">
                            <SelectItem
                                v-for="option in createForm.hotels"
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

                <div class="grid gap-1.5">
                    <Label
                        :for="`edit-department-${employee.id}`"
                        :class="labelClass"
                    >
                        {{ $t('Department') }}
                    </Label>
                    <Select
                        :model-value="department"
                        @update:model-value="onSelect('department', $event)"
                    >
                        <SelectTrigger
                            :id="`edit-department-${employee.id}`"
                            data-test="edit-employee-department-select"
                            :class="triggerClass"
                        >
                            <SelectValue
                                :placeholder="$t('Select department')"
                            />
                        </SelectTrigger>
                        <SelectContent class="border-line shadow-pop">
                            <SelectItem
                                v-for="option in departments"
                                :key="option.value"
                                :value="option.value"
                                class="text-[13px]"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.department_id" />
                </div>

                <div class="grid gap-1.5 md:col-span-2">
                    <Label
                        :for="`edit-email-${employee.id}`"
                        :class="labelClass"
                    >
                        {{ $t('Email address (optional)') }}
                    </Label>
                    <Input
                        :id="`edit-email-${employee.id}`"
                        name="email"
                        type="email"
                        :default-value="employee.emailAddress ?? ''"
                        :aria-invalid="errors.email ? true : undefined"
                        data-test="edit-employee-email-input"
                        :class="fieldClass"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-1.5">
                    <Label
                        :for="`edit-status-${employee.id}`"
                        :class="labelClass"
                    >
                        {{ $t('Account status') }}
                    </Label>
                    <Select
                        :model-value="accountStatus"
                        @update:model-value="onSelect('accountStatus', $event)"
                    >
                        <SelectTrigger
                            :id="`edit-status-${employee.id}`"
                            data-test="edit-employee-status-select"
                            :class="triggerClass"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent class="border-line shadow-pop">
                            <SelectItem
                                v-for="option in createForm.statuses"
                                :key="option.value"
                                :value="option.value"
                                class="text-[13px]"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.status" />
                </div>

                <label
                    class="text-brand-900 flex items-center gap-2.5 self-end pb-2.5 text-[12.5px] leading-4.5"
                >
                    <Checkbox
                        :model-value="allowReminderEmails"
                        data-test="edit-employee-consent-checkbox"
                        @update:model-value="
                            allowReminderEmails = $event === true
                        "
                    />
                    {{ $t('Allow training reminder emails') }}
                </label>
            </div>

            <div
                class="mt-1 flex flex-col-reverse gap-2 md:flex-row md:justify-end"
            >
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-10 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                    data-test="cancel-edit-employee-button"
                    @click="open = false"
                >
                    {{ $t('Cancel') }}
                </Button>
                <Button
                    type="submit"
                    :disabled="processing"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97]"
                    data-test="save-employee-button"
                >
                    {{ $t('Save changes') }}
                </Button>
            </div>
        </Form>
    </HotelsModal>
</template>
