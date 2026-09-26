<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed } from 'vue';
import HotelsModal from '@/components/hotels/HotelsModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { contract } from '@/routes/hotels';
import type { HotelRecord } from '@/types';

type Props = {
    hotel: HotelRecord | null;
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    saved: [];
}>();

const action = computed(() => contract.form(props.hotel?.id ?? 0));

const currentEnd = computed(() => props.hotel?.contractEndsOn ?? '');

function onSuccess(): void {
    open.value = false;
    emit('saved');
}
</script>

<template>
    <HotelsModal
        v-model:open="open"
        :title="
            $t('Extend contract for :name', {
                name: hotel?.name ?? $t('hotel'),
            })
        "
        :description="
            $t(
                'Move the contract end date. An ended contract comes back to active on its own once the date is in the future.',
            )
        "
    >
        <Form
            :key="hotel?.id ?? 0"
            v-bind="action"
            :options="{ preserveScroll: true, preserveState: true }"
            class="mt-2 grid gap-4"
            v-slot="{ errors, processing }"
            @success="onSuccess"
        >
            <div class="grid gap-1.5">
                <Label
                    for="hotel-contract-ends-on"
                    class="text-brand-900 text-[12px] font-semibold tracking-[0.02em]"
                >
                    {{ $t('New contract end') }}
                </Label>
                <Input
                    id="hotel-contract-ends-on"
                    name="contract_ends_on"
                    type="date"
                    required
                    :default-value="currentEnd"
                    :min="hotel?.contractStartsOn ?? undefined"
                    :aria-invalid="errors.contract_ends_on ? true : undefined"
                    data-test="hotel-contract-ends-on-input"
                    class="border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 rounded-sm text-[13px] shadow-none focus-visible:ring-3"
                />
                <p class="text-ink-slate text-[12px]">
                    {{
                        $t('Currently ends :date.', {
                            date: hotel?.contractEnd ?? '—',
                        })
                    }}
                </p>
                <InputError :message="errors.contract_ends_on" />
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
                    {{ $t('Cancel') }}
                </Button>
                <Button
                    type="submit"
                    :disabled="processing"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97]"
                    data-test="confirm-extend-contract-button"
                >
                    {{ $t('Extend contract') }}
                </Button>
            </div>
        </Form>
    </HotelsModal>
</template>
