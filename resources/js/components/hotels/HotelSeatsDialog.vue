<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import ProgressBar from '@/components/data/ProgressBar.vue';
import {
    capacityOf,
    capacityTone,
    progressTone,
    quotaText,
    seatPercent,
} from '@/components/hotels/hotelStatus';
import HotelsModal from '@/components/hotels/HotelsModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { seatQuotas } from '@/routes/hotels';
import { cn } from '@/lib/utils';
import type { HotelOverview } from '@/types';

type Props = {
    /** The selected hotel's overview, which carries the seat catalogue. */
    overview: HotelOverview | null;
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    saved: [];
}>();

// Controlled values so the Available / Full / Over quota pill follows what is
// typed before the save (spec 0002, contracts and seats child, step 9).
const allowed = ref<Record<number, number>>({});

watch(
    () => props.overview,
    (overview) => {
        allowed.value = Object.fromEntries(
            (overview?.seatCatalogue ?? []).map((entry) => [
                entry.departmentId,
                entry.allowedSeats ?? 0,
            ]),
        );
    },
    { immediate: true },
);

const rows = computed(() =>
    (props.overview?.seatCatalogue ?? []).map((entry) => {
        const total = allowed.value[entry.departmentId] ?? 0;
        const state = capacityOf(entry.usedSeats, total);

        return { ...entry, total, state };
    }),
);

const action = computed(() => seatQuotas.form(props.overview?.id ?? 0));

const totalAllowed = computed(() =>
    rows.value.reduce((sum, row) => sum + row.total, 0),
);

const totalUsed = computed(() =>
    rows.value.reduce((sum, row) => sum + row.usedSeats, 0),
);

function onSuccess(): void {
    open.value = false;
    emit('saved');
}
</script>

<template>
    <HotelsModal
        v-model:open="open"
        :title="
            $t('Manage seats for :name', {
                name: overview?.name ?? $t('hotel'),
            })
        "
        :description="
            $t(
                'Allowed employee seats per department. Lowering a quota below its current usage keeps every account and only blocks the next one.',
            )
        "
        class="sm:max-w-[620px]"
    >
        <Form
            :key="overview?.id ?? 0"
            v-bind="action"
            :options="{ preserveScroll: true, preserveState: true }"
            class="mt-2 grid gap-3"
            v-slot="{ errors, processing }"
            @success="onSuccess"
        >
            <p
                v-if="rows.length === 0"
                class="text-ink-muted rounded-md border border-dashed px-3 py-6 text-center text-[13px]"
            >
                {{ $t('No departments are available to this hotel yet.') }}
            </p>

            <ul v-else class="divide-line/80 divide-y">
                <li
                    v-for="(row, index) in rows"
                    :key="row.departmentId"
                    class="grid grid-cols-[1fr_auto] items-center gap-x-3 gap-y-2 py-2.5 md:grid-cols-[minmax(0,1fr)_120px_96px]"
                >
                    <input
                        type="hidden"
                        :name="`quotas[${index}][department_id]`"
                        :value="row.departmentId"
                    />

                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p
                                class="text-brand-900 truncate text-[13px] font-semibold"
                            >
                                {{ row.department }}
                            </p>
                            <span
                                :class="
                                    cn(
                                        'rounded-pill inline-flex min-h-5 items-center justify-center px-2 text-[10.5px] font-semibold whitespace-nowrap',
                                        capacityTone[row.state],
                                    )
                                "
                            >
                                {{ $t(quotaText[row.state]) }}
                            </span>
                        </div>
                        <div class="mt-1.5 flex items-center gap-2">
                            <ProgressBar
                                :value="seatPercent(row.usedSeats, row.total)"
                                :tone="progressTone[row.state]"
                                :label="
                                    $t(':name seats used', {
                                        name: row.department,
                                    })
                                "
                                class="h-[6px] w-full max-w-[180px]"
                            />
                            <span
                                class="text-ink-slate text-[11.5px] whitespace-nowrap"
                            >
                                {{
                                    $t(':count used', { count: row.usedSeats })
                                }}
                            </span>
                        </div>
                    </div>

                    <label
                        class="col-start-2 md:col-start-2 md:justify-self-end"
                    >
                        <span class="sr-only">
                            {{
                                $t('Allowed seats for :name', {
                                    name: row.department,
                                })
                            }}
                        </span>
                        <input
                            v-model.number="allowed[row.departmentId]"
                            type="number"
                            min="0"
                            max="10000"
                            step="1"
                            required
                            :name="`quotas[${index}][allowed_seats]`"
                            :data-test="`seat-quota-${row.departmentId}-input`"
                            class="border-line text-brand-900 bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 w-24 rounded-sm border px-3 text-end text-[13px] font-semibold focus-visible:ring-3 focus-visible:outline-none"
                        />
                    </label>

                    <p
                        class="text-ink-muted col-span-2 text-[11.5px] md:col-span-1 md:col-start-3 md:text-end"
                    >
                        {{
                            $t(':used/:total seats', {
                                used: row.usedSeats,
                                total: row.total,
                            })
                        }}
                    </p>

                    <InputError
                        class="col-span-full"
                        :message="errors[`quotas.${index}.allowed_seats`]"
                    />
                </li>
            </ul>

            <InputError :message="errors.quotas" />

            <div
                class="border-line/80 mt-1 flex flex-col-reverse gap-2 border-t pt-3 md:flex-row md:items-center md:justify-between"
            >
                <p class="text-ink-slate text-[12.5px]">
                    {{
                        $t(
                            ':used of :total seats used across :departments departments',
                            {
                                used: totalUsed,
                                total: totalAllowed,
                                departments: rows.length,
                            },
                        )
                    }}
                </p>
                <div class="flex flex-col-reverse gap-2 md:flex-row">
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
                        :disabled="processing || rows.length === 0"
                        class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97]"
                        data-test="save-seat-quotas-button"
                    >
                        {{ $t('Save seats') }}
                    </Button>
                </div>
            </div>
        </Form>
    </HotelsModal>
</template>
