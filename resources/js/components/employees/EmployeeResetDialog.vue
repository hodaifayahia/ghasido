<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed } from 'vue';
import HotelsModal from '@/components/hotels/HotelsModal.vue';
import { Button } from '@/components/ui/button';
import { resetPassword } from '@/routes/employees';
import type { EmployeeRecord } from '@/types';

type Props = {
    employee: EmployeeRecord | null;
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });

const action = computed(() => resetPassword.form(props.employee?.id ?? 0));

function onSuccess(): void {
    open.value = false;
}
</script>

<template>
    <HotelsModal
        v-model:open="open"
        :title="
            $t('Reset password for :name', {
                name: employee?.name ?? $t('employee'),
            })
        "
        :description="
            $t(
                'A new password is generated and shown to you once. The current password stops working immediately.',
            )
        "
    >
        <Form
            v-if="employee"
            :key="employee.id"
            v-bind="action"
            :options="{ preserveScroll: true, preserveState: true }"
            class="mt-3 flex flex-col-reverse gap-2 md:flex-row md:justify-end"
            v-slot="{ processing }"
            @success="onSuccess"
        >
            <Button
                type="button"
                variant="outline"
                class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-10 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                data-test="cancel-reset-password-button"
                @click="open = false"
            >
                {{ $t('Cancel') }}
            </Button>
            <Button
                type="submit"
                :disabled="processing"
                class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97]"
                data-test="confirm-reset-password-button"
            >
                {{ $t('Reset password') }}
            </Button>
        </Form>
    </HotelsModal>
</template>
