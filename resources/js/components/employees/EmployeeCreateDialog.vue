<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Form } from '@inertiajs/vue3';
import { Eye, EyeOff, Info, Plus, UserPlus } from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref } from 'vue';
import EmployeesController from '@/actions/App/Http/Controllers/Admin/EmployeesController';
import { generatePassword } from '@/components/employees/employeeStatus';
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
import type { EmployeeCreateForm } from '@/types';

type Props = {
    createForm: EmployeeCreateForm;
};

const props = defineProps<Props>();
const open = ref(false);

// The selects and the checkbox are controlled so the department list can
// follow the hotel (SUB-01); their values join the DOM fields through the
// form's transform. The text fields stay uncontrolled (name attributes).
const hotel = ref(props.createForm.defaultHotel);
const department = ref(props.createForm.defaultDepartment);
const accountStatus = ref(props.createForm.defaultStatus);
const allowReminderEmails = ref(props.createForm.allowReminderEmails);
const password = ref('');
const showPassword = ref(false);

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
        department.value =
            props.createForm.departmentsByHotel[value]?.[0]?.value ?? '';
        return;
    }

    if (target === 'department') {
        department.value = value;
        return;
    }

    accountStatus.value = value;
}

function fillGeneratedPassword(): void {
    password.value = generatePassword(props.createForm.passwordLength);
    showPassword.value = true;
}

type FormData = Record<string, FormDataConvertible>;

function transform(data: FormData): FormData {
    return {
        ...data,
        password: password.value,
        hotel_id: hotel.value,
        department_id: department.value,
        status: accountStatus.value,
        allow_reminder_emails: allowReminderEmails.value,
    };
}

function onSuccess(): void {
    password.value = '';
    showPassword.value = false;
    accountStatus.value = props.createForm.defaultStatus;
    allowReminderEmails.value = props.createForm.allowReminderEmails;
    open.value = false;
}

const fieldClass =
    'border-line placeholder:text-ink-faint bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-9 rounded-md px-3 text-[12.5px] shadow-none focus-visible:ring-3';
</script>

<template>
    <Button
        type="button"
        class="bg-brand-600 text-surface shadow-btn hover:bg-brand-700 h-9 gap-2 rounded-md px-3.5 text-[12.5px] font-semibold active:scale-[.97]"
        data-test="open-create-employee-button"
        @click="open = true"
    >
        <UserPlus class="size-4" aria-hidden="true" />
        {{ $t('Add New Employee') }}
    </Button>

    <!-- Account creation stays server-authorized and quota-checked (SUB-02). -->
    <HotelsModal
        v-model:open="open"
        :title="$t('Add New Employee')"
        :description="
            $t(
                'Create an employee account and assign it to a hotel and department.',
            )
        "
        class="sm:max-w-[600px]"
    >
        <Form
            v-bind="EmployeesController.store.form()"
            :transform="transform"
            :options="{ preserveScroll: true }"
            reset-on-success
            class="mt-3 grid gap-3 sm:grid-cols-2"
            v-slot="{ errors, processing }"
            @success="onSuccess"
        >
            <div class="space-y-1 sm:col-span-2">
                <Label
                    for="employee-full-name"
                    class="text-brand-900 text-[12.5px] font-semibold"
                >
                    {{ $t('Full Name') }} <span class="text-danger">*</span>
                </Label>
                <Input
                    id="employee-full-name"
                    name="name"
                    :placeholder="$t('Enter full name')"
                    autocomplete="off"
                    required
                    :aria-invalid="errors.name ? true : undefined"
                    data-test="employee-name-input"
                    :class="fieldClass"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="space-y-1">
                <Label
                    for="employee-username"
                    class="text-brand-900 text-[12.5px] font-semibold"
                >
                    {{ $t('Username') }} <span class="text-danger">*</span>
                </Label>
                <Input
                    id="employee-username"
                    name="username"
                    :placeholder="$t('Enter username')"
                    autocomplete="off"
                    autocapitalize="none"
                    spellcheck="false"
                    required
                    :aria-invalid="errors.username ? true : undefined"
                    data-test="employee-username-input"
                    :class="fieldClass"
                />
                <InputError :message="errors.username" />
            </div>

            <div class="space-y-1">
                <div class="flex items-center justify-between gap-2">
                    <Label
                        for="employee-password"
                        class="text-brand-900 text-[12.5px] font-semibold"
                    >
                        {{ $t('Password') }} <span class="text-danger">*</span>
                    </Label>
                    <button
                        type="button"
                        class="border-brand-200 text-brand-600 hover:bg-brand-50 bg-brand-50 focus-visible:border-brand-600 focus-visible:ring-brand-600/15 inline-flex h-6.5 items-center rounded-md border px-2.5 text-[11px] font-semibold focus-visible:ring-3 focus-visible:outline-none"
                        data-test="generate-password-button"
                        @click="fillGeneratedPassword"
                    >
                        {{ $t('Generate') }}
                    </button>
                </div>
                <div class="relative">
                    <Input
                        id="employee-password"
                        v-model="password"
                        :type="showPassword ? 'text' : 'password'"
                        :placeholder="$t('Enter password')"
                        autocomplete="new-password"
                        required
                        :aria-invalid="errors.password ? true : undefined"
                        data-test="employee-password-input"
                        :class="[fieldClass, 'pe-10']"
                    />
                    <button
                        type="button"
                        class="text-ink-faint hover:text-brand-700 absolute end-3 top-1/2 -translate-y-1/2"
                        :aria-label="
                            showPassword
                                ? $t('Hide password')
                                : $t('Show password')
                        "
                        :aria-pressed="showPassword"
                        @click="showPassword = !showPassword"
                    >
                        <component
                            :is="showPassword ? EyeOff : Eye"
                            class="size-4"
                            aria-hidden="true"
                        />
                    </button>
                </div>
                <InputError :message="errors.password" />
            </div>

            <div class="space-y-1">
                <Label
                    for="employee-hotel"
                    class="text-brand-900 text-[12.5px] font-semibold"
                >
                    {{ $t('Hotel') }} <span class="text-danger">*</span>
                </Label>
                <Select
                    :model-value="hotel"
                    @update:model-value="onSelect('hotel', $event)"
                >
                    <SelectTrigger
                        id="employee-hotel"
                        data-test="employee-hotel-select"
                        class="border-line text-ink bg-surface h-9 rounded-md px-3 text-[12.5px] shadow-none"
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

            <div class="space-y-1">
                <Label
                    for="employee-department"
                    class="text-brand-900 text-[12.5px] font-semibold"
                >
                    {{ $t('Department') }} <span class="text-danger">*</span>
                </Label>
                <Select
                    :model-value="department"
                    @update:model-value="onSelect('department', $event)"
                >
                    <SelectTrigger
                        id="employee-department"
                        data-test="employee-department-select"
                        class="border-line text-ink bg-surface h-9 rounded-md px-3 text-[12.5px] shadow-none"
                    >
                        <SelectValue :placeholder="$t('Select department')" />
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

            <div class="space-y-1 sm:col-span-2">
                <Label
                    for="employee-email"
                    class="text-brand-900 text-[12.5px] font-semibold"
                >
                    {{ $t('Email Address') }}
                </Label>
                <Input
                    id="employee-email"
                    name="email"
                    type="email"
                    :placeholder="$t('Enter email (optional)')"
                    autocomplete="off"
                    :aria-invalid="errors.email ? true : undefined"
                    data-test="employee-email-input"
                    :class="fieldClass"
                />
                <InputError :message="errors.email" />
            </div>

            <label
                class="text-brand-900 flex items-start gap-2.5 rounded-md py-0.5 text-[12px] leading-4.5 sm:col-span-2"
            >
                <Checkbox
                    :model-value="allowReminderEmails"
                    class="mt-0.5"
                    data-test="employee-consent-checkbox"
                    @update:model-value="allowReminderEmails = $event === true"
                />
                <span class="flex min-w-0 items-center gap-1.5">
                    <span>{{ $t('Allow training reminder emails') }}</span>
                    <Info
                        class="text-ink-faint size-3.5 shrink-0"
                        aria-hidden="true"
                    />
                </span>
            </label>

            <div class="space-y-1 sm:col-span-2">
                <Label
                    for="employee-status"
                    class="text-brand-900 text-[12.5px] font-semibold"
                >
                    {{ $t('Account Status') }}
                </Label>
                <Select
                    :model-value="accountStatus"
                    @update:model-value="onSelect('accountStatus', $event)"
                >
                    <SelectTrigger
                        id="employee-status"
                        data-test="employee-status-select"
                        class="border-line text-ink bg-surface h-9 rounded-md px-3 text-[12.5px] shadow-none"
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

            <Button
                type="submit"
                :disabled="processing"
                class="bg-brand-600 text-surface shadow-btn hover:bg-brand-700 mt-0.5 h-10 w-full rounded-md text-[13px] font-semibold active:scale-[.97] sm:col-span-2"
                data-test="create-employee-button"
            >
                <Plus class="size-4.5" aria-hidden="true" />
                {{ $t('Create Employee') }}
            </Button>
        </Form>
    </HotelsModal>
</template>
