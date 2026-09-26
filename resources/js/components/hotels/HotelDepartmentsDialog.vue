<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref, watch } from 'vue';
import HotelsModal from '@/components/hotels/HotelsModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useCan } from '@/composables/useCan';
import { destroy, store } from '@/routes/hotels/departments';
import type { HotelOverview } from '@/types';

/**
 * Hotel row action "Departments" (SUB-01, ORG-02): the departments this hotel
 * trains in. Add one from the catalogue (shared or the hotel's own) or create
 * a new one for the hotel, with its seat quota; remove one the hotel no
 * longer runs. Removing keeps every employee and record (DATA-10); the
 * server refuses while the department still has active employees here.
 */
type Props = {
    /** The selected hotel's overview: linked quotas + the catalogue. */
    overview: HotelOverview | null;
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });

const { can } = useCan();
const canCreate = computed(() => can('departments.manage'));

const NEW = 'new';
const choice = ref<string>('');
const newName = ref('');
const seats = ref(0);
const errors = ref<Record<string, string>>({});
const processing = ref(false);
const confirmRemove = ref<number | null>(null);

const linked = computed(() => props.overview?.quotas ?? []);

/** Catalogue departments the hotel does not run yet. */
const available = computed(() =>
    (props.overview?.seatCatalogue ?? []).filter(
        (entry) =>
            !linked.value.some(
                (row) => row.departmentId === entry.departmentId,
            ),
    ),
);

watch(open, (isOpen) => {
    if (isOpen) {
        reset();
    }
});

function reset(): void {
    choice.value = available.value[0]
        ? String(available.value[0].departmentId)
        : canCreate.value
          ? NEW
          : '';
    newName.value = '';
    seats.value = 0;
    errors.value = {};
    confirmRemove.value = null;
}

function onChoice(value: AcceptableValue): void {
    if (typeof value === 'string') {
        choice.value = value;
    }
}

function add(): void {
    if (props.overview === null || choice.value === '') {
        return;
    }

    processing.value = true;
    errors.value = {};

    router.post(
        store.url(props.overview.id),
        choice.value === NEW
            ? { name: newName.value, allowed_seats: seats.value }
            : {
                  department_id: Number(choice.value),
                  allowed_seats: seats.value,
              },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => reset(),
            onError: (bag) => {
                errors.value = bag;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

function remove(departmentId: number): void {
    if (props.overview === null) {
        return;
    }

    processing.value = true;
    errors.value = {};

    router.delete(
        destroy.url({
            hotel: props.overview.id,
            department: departmentId,
        }),
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                confirmRemove.value = null;
            },
            onError: (bag) => {
                errors.value = bag;
                confirmRemove.value = null;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <HotelsModal
        v-model:open="open"
        :title="`Departments at ${overview?.name ?? 'hotel'}`"
        description="The departments this hotel trains in. Removing one keeps every employee and training record."
        class="sm:max-w-[620px]"
    >
        <div class="mt-2 grid gap-4">
            <p
                v-if="errors.department"
                class="text-danger-text bg-danger-tint rounded-md px-3 py-2 text-[12.5px]"
                role="alert"
            >
                {{ errors.department }}
            </p>

            <p
                v-if="linked.length === 0"
                class="text-ink-muted rounded-md border border-dashed px-3 py-6 text-center text-[13px]"
            >
                This hotel has no department yet. Add the first one below.
            </p>

            <ul
                v-else
                class="divide-line/80 divide-y"
                data-test="hotel-departments-list"
            >
                <li
                    v-for="row in linked"
                    :key="row.departmentId"
                    class="flex flex-wrap items-center gap-x-3 gap-y-2 py-2.5"
                >
                    <div class="min-w-0 flex-1">
                        <p
                            class="text-brand-900 truncate text-[13px] font-semibold"
                        >
                            {{ row.department }}
                        </p>
                        <p class="text-ink-slate text-[11.5px]">
                            {{ row.usedSeats }}/{{ row.totalSeats }} seats used
                        </p>
                    </div>

                    <div
                        v-if="confirmRemove === row.departmentId"
                        class="flex items-center gap-2"
                    >
                        <span class="text-ink-slate text-[12px]">
                            Remove {{ row.department }}?
                        </span>
                        <Button
                            type="button"
                            variant="outline"
                            class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-11 rounded-md px-3 text-[12px] font-semibold shadow-none md:h-9"
                            @click="confirmRemove = null"
                        >
                            Keep
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="processing"
                            class="border-danger text-danger-text hover:bg-danger-tint bg-surface h-11 rounded-md px-3 text-[12px] font-semibold shadow-none md:h-9"
                            :data-test="`confirm-remove-department-${row.departmentId}`"
                            @click="remove(row.departmentId)"
                        >
                            Remove
                        </Button>
                    </div>
                    <Button
                        v-else
                        type="button"
                        variant="outline"
                        class="border-line text-danger-text hover:bg-danger-tint bg-surface h-11 gap-1.5 rounded-md px-3 text-[12px] font-semibold shadow-none md:h-9"
                        :aria-label="`Remove ${row.department} from ${overview?.name ?? 'the hotel'}`"
                        :data-test="`remove-department-${row.departmentId}`"
                        @click="confirmRemove = row.departmentId"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                        Remove
                    </Button>
                </li>
            </ul>

            <form
                v-if="available.length > 0 || canCreate"
                class="border-line/80 grid gap-3 border-t pt-4"
                @submit.prevent="add"
            >
                <p class="text-brand-900 text-[13px] font-semibold">
                    Add a department
                </p>

                <div
                    class="grid gap-3 md:grid-cols-[minmax(0,1fr)_120px] md:items-end"
                >
                    <div class="grid gap-1.5">
                        <label
                            for="hotel-department-choice"
                            class="text-ink-slate text-[12px] font-semibold"
                        >
                            Department
                        </label>
                        <Select
                            :model-value="choice"
                            @update:model-value="onChoice"
                        >
                            <SelectTrigger
                                id="hotel-department-choice"
                                class="border-line text-ink bg-surface h-11 w-full rounded-md px-3 text-[13px] shadow-none md:h-10"
                                data-test="hotel-department-choice"
                            >
                                <SelectValue
                                    placeholder="Choose a department"
                                />
                            </SelectTrigger>
                            <SelectContent class="border-line shadow-pop">
                                <SelectItem
                                    v-for="entry in available"
                                    :key="entry.departmentId"
                                    :value="String(entry.departmentId)"
                                    class="text-[13px]"
                                >
                                    {{ entry.department }}
                                </SelectItem>
                                <SelectItem
                                    v-if="canCreate"
                                    :value="NEW"
                                    class="text-brand-700 text-[13px] font-semibold"
                                >
                                    + New department for this hotel
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.department_id" />
                    </div>

                    <div class="grid gap-1.5">
                        <label
                            for="hotel-department-seats"
                            class="text-ink-slate text-[12px] font-semibold"
                        >
                            Seats
                        </label>
                        <input
                            id="hotel-department-seats"
                            v-model.number="seats"
                            type="number"
                            min="0"
                            max="10000"
                            step="1"
                            required
                            class="border-line text-brand-900 bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-11 w-full rounded-sm border px-3 text-end text-[13px] font-semibold focus-visible:ring-3 focus-visible:outline-none md:h-10"
                            data-test="hotel-department-seats"
                        />
                        <InputError :message="errors.allowed_seats" />
                    </div>
                </div>

                <div v-if="choice === NEW" class="grid gap-1.5">
                    <label
                        for="hotel-department-name"
                        class="text-ink-slate text-[12px] font-semibold"
                    >
                        New department name
                    </label>
                    <Input
                        id="hotel-department-name"
                        v-model="newName"
                        maxlength="120"
                        placeholder="e.g. Spa & Wellness"
                        class="border-line h-11 rounded-md text-[13px] md:h-10"
                        data-test="hotel-department-name"
                    />
                    <InputError :message="errors.name ?? errors.slug" />
                </div>

                <div class="flex justify-end">
                    <Button
                        type="submit"
                        :disabled="
                            processing ||
                            choice === '' ||
                            (choice === NEW && newName.trim() === '')
                        "
                        class="bg-brand-600 shadow-btn hover:bg-brand-700 h-11 gap-1.5 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97] md:h-10"
                        data-test="add-hotel-department-button"
                    >
                        <Plus class="size-4" aria-hidden="true" />
                        Add department
                    </Button>
                </div>
            </form>
        </div>
    </HotelsModal>
</template>
