<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed } from 'vue';
import HotelsModal from '@/components/hotels/HotelsModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { archive, reject } from '@/routes/hotels';
import type { HotelRecord } from '@/types';

type Props = {
    hotel: HotelRecord | null;
    /** Reject a pending hotel (reason required) or archive one (optional). */
    mode: 'reject' | 'archive';
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    saved: [];
}>();

const copy = computed(() =>
    props.mode === 'reject'
        ? {
              title: `Reject ${props.hotel?.name ?? 'hotel'}`,
              description:
                  'The hotel is archived with your reason. Nothing is deleted, and the reason is kept in the audit log.',
              label: 'Reason for rejecting',
              button: 'Reject hotel',
              required: true,
          }
        : {
              title: `Archive ${props.hotel?.name ?? 'hotel'}`,
              description:
                  'Access stops for everyone at this hotel. Every account, answer and record is kept.',
              label: 'Reason (optional)',
              button: 'Archive hotel',
              required: false,
          },
);

const action = computed(() => {
    const id = props.hotel?.id ?? 0;

    return props.mode === 'reject' ? reject.form(id) : archive.form(id);
});

function onSuccess(): void {
    open.value = false;
    emit('saved');
}
</script>

<template>
    <HotelsModal
        v-model:open="open"
        :title="copy.title"
        :description="copy.description"
    >
        <Form
            :key="`${mode}-${hotel?.id ?? 0}`"
            v-bind="action"
            :options="{ preserveScroll: true, preserveState: true }"
            reset-on-success
            class="mt-2 grid gap-4"
            v-slot="{ errors, processing }"
            @success="onSuccess"
        >
            <div class="grid gap-1.5">
                <Label
                    for="hotel-reason"
                    class="text-brand-900 text-[12px] font-semibold tracking-[0.02em]"
                >
                    {{ copy.label }}
                </Label>
                <textarea
                    id="hotel-reason"
                    name="reason"
                    rows="3"
                    :required="copy.required"
                    maxlength="500"
                    :aria-invalid="errors.reason ? true : undefined"
                    data-test="hotel-reason-input"
                    class="border-line text-ink bg-surface placeholder:text-ink-faint focus-visible:border-brand-600 focus-visible:ring-brand-600/15 w-full rounded-sm border px-3 py-2 text-[13px] focus-visible:ring-3 focus-visible:outline-none"
                    placeholder="A short note for the audit log"
                />
                <InputError :message="errors.reason" />
            </div>

            <div
                class="mt-1 flex flex-col-reverse gap-2 md:flex-row md:justify-end"
            >
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-10 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                    @click="open = false"
                >
                    Cancel
                </Button>
                <Button
                    type="submit"
                    variant="outline"
                    :disabled="processing"
                    class="border-danger text-danger-text hover:bg-danger-tint bg-surface h-10 rounded-md px-4 text-[12.5px] font-semibold shadow-none active:scale-[.97]"
                    :data-test="`confirm-${mode}-hotel-button`"
                >
                    {{ copy.button }}
                </Button>
            </div>
        </Form>
    </HotelsModal>
</template>
