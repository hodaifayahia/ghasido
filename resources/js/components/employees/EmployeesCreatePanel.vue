<script setup lang="ts">
import { Eye, Info, Plus, UserPlus } from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { ref } from 'vue';
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

const fullName = ref('');
const username = ref('');
const password = ref('');
const email = ref('');
const hotel = ref(props.createForm.defaultHotel);
const department = ref(props.createForm.defaultDepartment);
const accountStatus = ref(props.createForm.defaultStatus);
const allowReminderEmails = ref(props.createForm.allowReminderEmails);

function onSelect(
    target: 'hotel' | 'department' | 'accountStatus',
    value: AcceptableValue,
): void {
    if (typeof value !== 'string') {
        return;
    }

    if (target === 'hotel') {
        hotel.value = value;
        return;
    }

    if (target === 'department') {
        department.value = value;
        return;
    }

    accountStatus.value = value;
}

function fillGeneratedPassword(): void {
    password.value = 'Guesvia2026!';
}
</script>

<template>
    <section
        aria-label="Create employee"
        class="border-line bg-surface shadow-card rounded-lg border px-4 pt-3 pb-3.5"
    >
        <header class="flex items-center gap-2.5">
            <div
                class="bg-brand-100 text-brand-600 grid size-9 place-items-center rounded-full"
            >
                <UserPlus class="size-5 stroke-[2.1]" aria-hidden="true" />
            </div>
            <h2 class="font-heading text-brand-700 text-[15px] font-semibold">
                Add New Employee
            </h2>
        </header>

        <form class="mt-3.5 space-y-3" @submit.prevent>
            <div class="space-y-1">
                <Label
                    for="employee-full-name"
                    class="text-brand-900 text-[12.5px] font-semibold"
                >
                    Full Name <span class="text-danger">*</span>
                </Label>
                <Input
                    id="employee-full-name"
                    v-model="fullName"
                    placeholder="Enter full name"
                    class="border-line placeholder:text-ink-faint bg-surface h-9 rounded-md px-3 text-[12.5px] shadow-none"
                />
            </div>

            <div class="space-y-1">
                <Label
                    for="employee-username"
                    class="text-brand-900 text-[12.5px] font-semibold"
                >
                    Username <span class="text-danger">*</span>
                </Label>
                <Input
                    id="employee-username"
                    v-model="username"
                    placeholder="Enter username"
                    class="border-line placeholder:text-ink-faint bg-surface h-9 rounded-md px-3 text-[12.5px] shadow-none"
                />
            </div>

            <div class="space-y-1">
                <div class="flex items-center justify-between gap-2">
                    <Label
                        for="employee-password"
                        class="text-brand-900 text-[12.5px] font-semibold"
                    >
                        Password <span class="text-danger">*</span>
                    </Label>
                    <button
                        type="button"
                        class="border-brand-200 text-brand-600 hover:bg-brand-50 bg-brand-50 inline-flex h-6.5 items-center rounded-md border px-2.5 text-[11px] font-semibold"
                        @click="fillGeneratedPassword"
                    >
                        Generate
                    </button>
                </div>
                <div class="relative">
                    <Input
                        id="employee-password"
                        v-model="password"
                        type="password"
                        placeholder="Enter password"
                        class="border-line placeholder:text-ink-faint bg-surface h-9 rounded-md px-3 pe-10 text-[12.5px] shadow-none"
                    />
                    <button
                        type="button"
                        class="text-ink-faint absolute end-3 top-1/2 -translate-y-1/2"
                        aria-label="Preview password"
                    >
                        <Eye class="size-4" aria-hidden="true" />
                    </button>
                </div>
            </div>

            <div class="space-y-1">
                <Label class="text-brand-900 text-[12.5px] font-semibold">
                    Hotel <span class="text-danger">*</span>
                </Label>
                <Select
                    :model-value="hotel"
                    @update:model-value="onSelect('hotel', $event)"
                >
                    <SelectTrigger
                        class="border-line text-ink bg-surface h-9 rounded-md px-3 text-[12.5px] shadow-none"
                    >
                        <SelectValue />
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
            </div>

            <div class="space-y-1">
                <Label class="text-brand-900 text-[12.5px] font-semibold">
                    Department <span class="text-danger">*</span>
                </Label>
                <Select
                    :model-value="department"
                    @update:model-value="onSelect('department', $event)"
                >
                    <SelectTrigger
                        class="border-line text-ink bg-surface h-9 rounded-md px-3 text-[12.5px] shadow-none"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in createForm.departments"
                            :key="option.value"
                            :value="option.value"
                            class="text-[13px]"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="space-y-1">
                <Label
                    for="employee-email"
                    class="text-brand-900 text-[12.5px] font-semibold"
                >
                    Email Address
                </Label>
                <Input
                    id="employee-email"
                    v-model="email"
                    type="email"
                    placeholder="Enter email (optional)"
                    class="border-line placeholder:text-ink-faint bg-surface h-9 rounded-md px-3 text-[12.5px] shadow-none"
                />
            </div>

            <label
                class="text-brand-900 flex items-start gap-2.5 rounded-md py-0.5 text-[12px] leading-4.5"
            >
                <Checkbox
                    :model-value="allowReminderEmails"
                    class="mt-0.5"
                    @update:model-value="allowReminderEmails = $event === true"
                />
                <span class="flex min-w-0 items-center gap-1.5">
                    <span>Allow training reminder emails</span>
                    <Info
                        class="text-ink-faint size-3.5 shrink-0"
                        aria-hidden="true"
                    />
                </span>
            </label>

            <div class="space-y-1">
                <Label class="text-brand-900 text-[12.5px] font-semibold">
                    Account Status
                </Label>
                <Select
                    :model-value="accountStatus"
                    @update:model-value="onSelect('accountStatus', $event)"
                >
                    <SelectTrigger
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
            </div>

            <Button
                type="submit"
                class="bg-brand-600 text-surface shadow-btn hover:bg-brand-700 mt-0.5 h-10 w-full rounded-md text-[13px] font-semibold"
            >
                <Plus class="size-4.5" aria-hidden="true" />
                Create Employee
            </Button>
        </form>
    </section>
</template>
