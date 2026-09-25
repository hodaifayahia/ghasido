<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import InputError from '@/components/InputError.vue';
import { cn } from '@/lib/utils';
import { update as updatePrices } from '@/routes/owner/prices';
import type { AiPriceRow } from '@/types';

/*
 * The price table behind every cost and the dollar credit (spec 0007, D8,
 * D9; moved here from Settings → AI usage). Stored per million units; shown
 * per million tokens or characters, per minute of audio and per image, so
 * the owner can copy a provider's price list as it is written.
 */
type Props = {
    prices: AiPriceRow[];
    units: string[];
    /** Models the accounts used with no price yet. */
    unpriced: { model: string; unit: string }[];
};

const props = defineProps<Props>();

type Scale = { label: string; hint: string; factor: number; split: boolean };

// factor: shown = stored per million × factor.
const scales: Record<string, Scale> = {
    tokens: {
        label: 'Tokens (text)',
        hint: 'per 1M tokens',
        factor: 1,
        split: true,
    },
    characters: {
        label: 'Characters (speech)',
        hint: 'per 1M characters',
        factor: 1,
        split: false,
    },
    seconds: {
        label: 'Audio time',
        hint: 'per minute',
        factor: 60 / 1_000_000,
        split: false,
    },
    images: {
        label: 'Images',
        hint: 'per image',
        factor: 1 / 1_000_000,
        split: false,
    },
};

function scaleOf(unit: string): Scale {
    return scales[unit] ?? { label: unit, hint: '', factor: 1, split: true };
}

function round(value: number): number {
    return Math.round(value * 1_000_000) / 1_000_000;
}

function toShown(rows: AiPriceRow[]): AiPriceRow[] {
    return rows.map((row) => ({
        ...row,
        input: round(row.input * scaleOf(row.unit).factor),
        output: round(row.output * scaleOf(row.unit).factor),
    }));
}

const rows = ref<AiPriceRow[]>(toShown(props.prices));
const saving = ref(false);
const errors = ref<Record<string, string>>({});

watch(
    () => props.prices,
    (prices) => {
        rows.value = toShown(prices);
    },
);

const missing = computed(() =>
    props.unpriced.filter(
        (item) => !rows.value.some((row) => row.model === item.model),
    ),
);

function addRow(model = '', unit = 'tokens'): void {
    rows.value.push({ model, unit, input: 0, output: 0 });
}

function removeRow(index: number): void {
    rows.value.splice(index, 1);
}

function save(): void {
    saving.value = true;
    router.put(
        updatePrices.url(),
        {
            prices: rows.value.map((row) => {
                const scale = scaleOf(row.unit);

                return {
                    model: row.model.trim(),
                    unit: row.unit,
                    input: row.input / scale.factor,
                    output: scale.split ? row.output / scale.factor : 0,
                };
            }),
        },
        {
            preserveScroll: true,
            onError: (bag) => {
                errors.value = bag;
            },
            onSuccess: () => {
                errors.value = {};
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

const fieldClass =
    'border-line text-ink bg-surface placeholder:text-ink-faint focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 w-full min-w-0 rounded-sm border px-2.5 text-[13px] focus-visible:ring-3 focus-visible:outline-none';
const grid =
    'sm:grid-cols-[minmax(0,2fr)_minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1fr)_40px]';
</script>

<template>
    <PanelCard title="Prices" title-id="owner-prices">
        <template #actions>
            <button
                type="button"
                class="border-line text-brand-700 hover:bg-brand-50 bg-surface focus-visible:ring-brand-600/15 inline-flex h-10 items-center gap-1.5 rounded-md border px-3 text-[12.5px] font-semibold focus-visible:ring-3 focus-visible:outline-none"
                @click="addRow()"
            >
                <Plus class="size-4" aria-hidden="true" />
                Add model
            </button>
        </template>

        <p class="text-ink-slate mb-3 text-[12.5px] leading-5">
            What each provider charges you, in dollars. These prices turn usage
            into the spend taken off each account's credit, and the Super
            Admin's AI usage page shows costs at them. Use the model id as the
            app records it; end it with * to cover every model that starts the
            same way (for example aura-2-*). Usage recorded before a price
            existed is costed at today's price.
        </p>

        <div
            v-if="missing.length > 0"
            class="bg-warning-tint text-warning-text mb-3 flex flex-wrap items-center gap-2 rounded-md px-3 py-2 text-[12.5px]"
            data-test="owner-unpriced-models"
        >
            <span>Used with no price yet:</span>
            <button
                v-for="item in missing"
                :key="item.model"
                type="button"
                class="border-warning/40 bg-surface text-warning-text hover:bg-warning-tint focus-visible:ring-warning/25 inline-flex h-8 items-center gap-1 rounded-md border px-2 font-mono text-[12px] font-semibold focus-visible:ring-3 focus-visible:outline-none"
                :aria-label="`Add a price for ${item.model}`"
                @click="addRow(item.model, item.unit)"
            >
                <Plus class="size-3.5" aria-hidden="true" />
                {{ item.model }}
            </button>
        </div>

        <div class="grid gap-2" data-test="owner-price-rows">
            <div
                v-if="rows.length > 0"
                :class="
                    cn(
                        'text-ink/75 hidden gap-2 text-[12px] font-medium sm:grid',
                        grid,
                    )
                "
                aria-hidden="true"
            >
                <span>Model id</span>
                <span>Counted in</span>
                <span>Price</span>
                <span>Output price</span>
                <span />
            </div>

            <div
                v-for="(row, index) in rows"
                :key="index"
                :class="cn('grid gap-2 sm:items-start', grid)"
            >
                <div>
                    <label :for="`owner-price-model-${index}`" class="sr-only"
                        >Model id</label
                    >
                    <input
                        :id="`owner-price-model-${index}`"
                        v-model="row.model"
                        :class="cn(fieldClass, 'font-mono')"
                        placeholder="Model id"
                        maxlength="150"
                    />
                    <InputError :message="errors[`prices.${index}.model`]" />
                </div>
                <div>
                    <label :for="`owner-price-unit-${index}`" class="sr-only"
                        >Counted in</label
                    >
                    <select
                        :id="`owner-price-unit-${index}`"
                        v-model="row.unit"
                        :class="fieldClass"
                    >
                        <option v-for="unit in units" :key="unit" :value="unit">
                            {{ scaleOf(unit).label }}
                        </option>
                    </select>
                    <InputError :message="errors[`prices.${index}.unit`]" />
                </div>
                <div>
                    <label :for="`owner-price-in-${index}`" class="sr-only"
                        >Price {{ scaleOf(row.unit).hint }}</label
                    >
                    <div class="relative">
                        <input
                            :id="`owner-price-in-${index}`"
                            v-model.number="row.input"
                            type="number"
                            min="0"
                            step="any"
                            :class="cn(fieldClass, 'pe-24')"
                            placeholder="0"
                        />
                        <span
                            class="text-ink-faint pointer-events-none absolute inset-y-0 end-2.5 flex items-center text-[11px]"
                            >{{
                                scaleOf(row.unit).split
                                    ? 'in, 1M'
                                    : scaleOf(row.unit).hint
                            }}</span
                        >
                    </div>
                    <InputError :message="errors[`prices.${index}.input`]" />
                </div>
                <div>
                    <template v-if="scaleOf(row.unit).split">
                        <label :for="`owner-price-out-${index}`" class="sr-only"
                            >Output price per 1M tokens</label
                        >
                        <div class="relative">
                            <input
                                :id="`owner-price-out-${index}`"
                                v-model.number="row.output"
                                type="number"
                                min="0"
                                step="any"
                                :class="cn(fieldClass, 'pe-16')"
                                placeholder="0"
                            />
                            <span
                                class="text-ink-faint pointer-events-none absolute inset-y-0 end-2.5 flex items-center text-[11px]"
                                >out, 1M</span
                            >
                        </div>
                        <InputError
                            :message="errors[`prices.${index}.output`]"
                        />
                    </template>
                    <p
                        v-else
                        class="text-ink-faint hidden h-10 items-center text-[12px] sm:flex"
                    >
                        One price
                    </p>
                </div>
                <button
                    type="button"
                    class="text-danger hover:bg-danger-tint focus-visible:ring-danger/20 grid size-10 place-items-center rounded-md focus-visible:ring-3 focus-visible:outline-none"
                    :aria-label="`Remove the price for ${row.model || 'this model'}`"
                    @click="removeRow(index)"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                </button>
            </div>

            <p v-if="rows.length === 0" class="text-ink-slate text-[13px]">
                No prices yet. Until a model has one, its usage counts as $0
                against the dollar credit.
            </p>
        </div>

        <div class="mt-4 flex justify-end">
            <button
                type="button"
                :disabled="saving"
                class="bg-brand-600 shadow-btn hover:bg-brand-700 focus-visible:ring-brand-600/15 inline-flex h-10 items-center rounded-md px-5 text-[13px] font-semibold text-white focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] disabled:opacity-60"
                data-test="owner-save-prices-button"
                @click="save"
            >
                Save prices
            </button>
        </div>
    </PanelCard>
</template>
