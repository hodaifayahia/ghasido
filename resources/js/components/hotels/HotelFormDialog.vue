<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed } from 'vue';
import HotelsModal from '@/components/hotels/HotelsModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/hotels';
import type { HotelRecord } from '@/types';

type Props = {
    /** The hotel being edited, or null to create one (spec 0002, AC-12). */
    hotel: HotelRecord | null;
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    saved: [];
}>();

const editing = computed(() => props.hotel !== null);

// The Wayfinder form variant carries method spoofing for PATCH itself, so
// the form never builds a `_method` field by hand (AGENTS.md §5).
const action = computed(() =>
    props.hotel === null ? store.form() : update.form(props.hotel.id),
);

const fields = [
    { name: 'name', label: 'Hotel name', type: 'text', span: 2 },
    { name: 'city', label: 'City', type: 'text', span: 1 },
    { name: 'manager_name', label: 'Manager name', type: 'text', span: 1 },
    {
        name: 'manager_email',
        label: 'Manager email',
        type: 'email',
        span: 2,
    },
    {
        name: 'contract_starts_on',
        label: 'Contract start',
        type: 'date',
        span: 1,
    },
    {
        name: 'contract_ends_on',
        label: 'Contract end',
        type: 'date',
        span: 1,
    },
] as const;

type FieldName = (typeof fields)[number]['name'];

function defaultValue(name: FieldName): string {
    const hotel = props.hotel;

    if (hotel === null) {
        return '';
    }

    const values: Record<FieldName, string> = {
        name: hotel.name,
        city: hotel.city,
        manager_name: hotel.manager,
        manager_email: hotel.email,
        contract_starts_on: hotel.contractStartsOn ?? '',
        contract_ends_on: hotel.contractEndsOn ?? '',
    };

    return values[name];
}

function onSuccess(): void {
    open.value = false;
    emit('saved');
}
</script>

<template>
    <HotelsModal
        v-model:open="open"
        :title="editing ? `Edit ${hotel?.name ?? 'hotel'}` : 'Add Hotel'"
        :description="
            editing
                ? 'Update the hotel\'s contact details and planned contract dates.'
                : 'A new hotel waits for your approval before anyone can sign in.'
        "
    >
        <Form
            :key="hotel?.id ?? 'create'"
            v-bind="action"
            :options="{ preserveScroll: true, preserveState: true }"
            reset-on-success
            class="mt-2 grid gap-4"
            v-slot="{ errors, processing }"
            @success="onSuccess"
        >
            <div class="grid gap-4 md:grid-cols-2">
                <div
                    v-for="field in fields"
                    :key="field.name"
                    :class="[
                        'grid gap-1.5',
                        field.span === 2 && 'md:col-span-2',
                    ]"
                >
                    <Label
                        :for="`hotel-${field.name}`"
                        class="text-brand-900 text-[12px] font-semibold tracking-[0.02em]"
                    >
                        {{ field.label }}
                    </Label>
                    <Input
                        :id="`hotel-${field.name}`"
                        :name="field.name"
                        :type="field.type"
                        :default-value="defaultValue(field.name)"
                        required
                        :aria-invalid="errors[field.name] ? true : undefined"
                        :data-test="`hotel-${field.name}-input`"
                        class="border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 rounded-sm text-[13px] shadow-none focus-visible:ring-3"
                    />
                    <InputError :message="errors[field.name]" />
                </div>
            </div>

            <div
                class="mt-1 flex flex-col-reverse gap-2 md:flex-row md:justify-end"
            >
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-10 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                    data-test="cancel-hotel-button"
                    @click="open = false"
                >
                    Cancel
                </Button>
                <Button
                    type="submit"
                    :disabled="processing"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97]"
                    data-test="save-hotel-button"
                >
                    {{ editing ? 'Save changes' : 'Create hotel' }}
                </Button>
            </div>
        </Form>
    </HotelsModal>
</template>
