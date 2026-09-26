<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    Bot,
    CalendarDays,
    Check,
    KeyRound,
    Mic,
    UserRound,
} from '@lucide/vue';
import { computed, watch } from 'vue';
import HotelsModal from '@/components/hotels/HotelsModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { store, update } from '@/routes/individuals';
import type {
    IndividualDefaults,
    IndividualOption,
    IndividualRow,
} from '@/types';

/**
 * Add or edit an individual subscriber: their account, access dates and
 * their own AI configuration (user request 2026-09-25).
 */
type Props = {
    individual: IndividualRow | null;
    departments: IndividualOption[];
    defaults: IndividualDefaults;
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });

const form = useForm({
    name: '',
    username: '',
    email: '',
    password: '',
    department_ids: [] as string[],
    status: 'active',
    starts_on: '',
    ends_on: '',
    price_dzd: '' as number | '',
    payment_reference: '',
    ai_enabled: true,
    voice_enabled: true,
    ai_points_allocated: 0 as number | '',
    ai_action_points: 0 as number | '',
    voice_points_per_10_minutes: 0 as number | '',
    daily_ai_turns: '' as number | '',
    notes: '',
});

const editing = computed(() => props.individual !== null);

function today(offsetDays = 0): string {
    const date = new Date();
    date.setDate(date.getDate() + offsetDays);

    return date.toISOString().slice(0, 10);
}

watch(
    () => [props.individual, open.value] as const,
    ([row, isOpen]) => {
        if (!isOpen) return;

        form.name = row?.name ?? '';
        form.username = row?.username ?? '';
        form.email = row?.email ?? '';
        form.password = '';
        form.department_ids = row
            ? [...row.departmentIds]
            : props.departments[0]
              ? [props.departments[0].value]
              : [];
        form.status = row?.status ?? 'active';
        form.starts_on = row ? (row.startsOn ?? '') : today();
        form.ends_on = row ? (row.endsOn ?? '') : today(30);
        form.price_dzd = row?.priceDzd ?? '';
        form.payment_reference = row?.paymentReference ?? '';
        form.ai_enabled = row?.aiEnabled ?? true;
        form.voice_enabled = row?.voiceEnabled ?? true;
        form.ai_points_allocated = row?.aiPoints ?? props.defaults.aiPoints;
        form.ai_action_points =
            row?.aiActionPoints ?? props.defaults.aiActionPoints;
        form.voice_points_per_10_minutes =
            row?.voicePointsPer10Minutes ??
            props.defaults.voicePointsPer10Minutes;
        form.daily_ai_turns = row?.dailyAiTurns ?? '';
        form.notes = row?.notes ?? '';
        form.clearErrors();
    },
    { immediate: true },
);

/**
 * Tick or untick a department. The order they were ticked in is kept: the
 * first one is the learner's main department.
 */
function toggleDepartment(value: string): void {
    form.department_ids = form.department_ids.includes(value)
        ? form.department_ids.filter((id) => id !== value)
        : [...form.department_ids, value];
}

const departmentError = computed(
    () =>
        form.errors.department_ids ??
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('department_ids.'),
        )?.[1],
);

function submit(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };

    if (props.individual !== null) {
        form.patch(update.url(props.individual.id), options);
    } else {
        form.post(store.url(), options);
    }
}

/** Twelve characters from a readable alphabet, shown so it can be shared. */
function generatePassword(): void {
    const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    const values = new Uint32Array(12);
    crypto.getRandomValues(values);
    form.password = Array.from(
        values,
        (value) => alphabet[value % alphabet.length],
    ).join('');
}

const labelClass = 'text-brand-900 text-[12px] font-semibold tracking-[0.02em]';
const inputClass =
    'border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 w-full rounded-sm border px-3 text-[13px] shadow-none focus-visible:ring-3 focus-visible:outline-none';
const sectionClass = 'border-line grid gap-3 rounded-md border p-3';
const headingClass =
    'text-brand-800 flex items-center gap-2 text-[12.5px] font-semibold';
</script>

<template>
    <HotelsModal
        v-model:open="open"
        :title="
            editing ? `Edit ${individual?.name}` : 'Add individual subscriber'
        "
        description="A learner who uses GHASIDO on their own, without a hotel. Everything below is their own configuration."
        class="sm:max-w-[680px]"
    >
        <form class="mt-2 grid gap-3" @submit.prevent="submit">
            <!-- Account -->
            <section :class="sectionClass" aria-labelledby="individual-account">
                <h3 id="individual-account" :class="headingClass">
                    <UserRound class="size-4" aria-hidden="true" />
                    Account
                </h3>

                <div class="grid gap-1.5">
                    <Label for="individual-name" :class="labelClass"
                        >Full name</Label
                    >
                    <Input
                        id="individual-name"
                        v-model="form.name"
                        required
                        maxlength="120"
                        autocomplete="name"
                        :class="inputClass"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid min-w-0 gap-1.5">
                        <Label for="individual-username" :class="labelClass"
                            >Username</Label
                        >
                        <Input
                            id="individual-username"
                            v-model="form.username"
                            required
                            minlength="3"
                            maxlength="40"
                            autocomplete="off"
                            spellcheck="false"
                            :class="inputClass"
                        />
                        <InputError :message="form.errors.username" />
                    </div>
                    <div class="grid min-w-0 gap-1.5">
                        <Label for="individual-email" :class="labelClass"
                            >Email
                            <span class="text-ink-slate font-normal"
                                >(optional)</span
                            ></Label
                        >
                        <Input
                            id="individual-email"
                            v-model="form.email"
                            type="email"
                            maxlength="255"
                            autocomplete="off"
                            :class="inputClass"
                        />
                        <InputError :message="form.errors.email" />
                    </div>
                </div>

                <div class="grid gap-1.5">
                    <Label for="individual-password" :class="labelClass">
                        {{ editing ? 'New password' : 'Initial password' }}
                    </Label>
                    <div class="flex gap-2">
                        <Input
                            id="individual-password"
                            v-model="form.password"
                            type="text"
                            :required="!editing"
                            minlength="8"
                            maxlength="72"
                            autocomplete="new-password"
                            spellcheck="false"
                            :class="inputClass"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-10 shrink-0 gap-1.5 rounded-md px-3 text-[12px] font-semibold shadow-none"
                            @click="generatePassword"
                        >
                            <KeyRound class="size-4" aria-hidden="true" />
                            Generate
                        </Button>
                    </div>
                    <p class="text-ink-slate text-[11px] leading-4">
                        {{
                            editing
                                ? 'Leave blank to keep the current password.'
                                : 'At least 8 characters. Share it with the learner securely.'
                        }}
                    </p>
                    <InputError :message="form.errors.password" />
                </div>

                <fieldset class="grid min-w-0 gap-1.5">
                    <legend :class="labelClass">
                        Departments to study
                        <span class="text-ink-slate font-normal"
                            >(one or more)</span
                        >
                    </legend>
                    <div
                        class="mt-1.5 flex flex-wrap gap-2"
                        data-test="individual-departments"
                    >
                        <button
                            v-for="department in departments"
                            :key="department.value"
                            type="button"
                            :aria-pressed="
                                form.department_ids.includes(department.value)
                            "
                            :class="
                                cn(
                                    'focus-visible:ring-brand-600/40 inline-flex min-h-10 items-center gap-2 rounded-md border px-3 text-[12.5px] font-semibold transition-colors focus-visible:ring-2 focus-visible:outline-none',
                                    form.department_ids.includes(
                                        department.value,
                                    )
                                        ? 'border-brand-600 bg-brand-50 text-brand-700'
                                        : 'border-line bg-surface text-ink-slate hover:bg-app-alt',
                                )
                            "
                            @click="toggleDepartment(department.value)"
                        >
                            <span
                                :class="
                                    cn(
                                        'grid size-4 shrink-0 place-items-center rounded-[4px] border',
                                        form.department_ids.includes(
                                            department.value,
                                        )
                                            ? 'border-brand-600 bg-brand-600 text-surface'
                                            : 'border-line-strong bg-surface',
                                    )
                                "
                                aria-hidden="true"
                            >
                                <Check
                                    v-if="
                                        form.department_ids.includes(
                                            department.value,
                                        )
                                    "
                                    class="size-3"
                                />
                            </span>
                            {{ department.label }}
                            <span
                                v-if="
                                    form.department_ids[0] ===
                                        department.value &&
                                    form.department_ids.length > 1
                                "
                                class="bg-brand-600 text-surface rounded-pill px-1.5 py-px text-[10px]"
                                >Main</span
                            >
                        </button>
                    </div>
                    <p class="text-ink-slate text-[11px] leading-4">
                        With more than one, the learner switches between them
                        with "Training in" at the top of their pages. The first
                        one ticked is their main department.
                    </p>
                    <InputError :message="departmentError" />
                </fieldset>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid min-w-0 gap-1.5">
                        <Label for="individual-status" :class="labelClass"
                            >Account status</Label
                        >
                        <select
                            id="individual-status"
                            v-model="form.status"
                            :class="inputClass"
                        >
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <InputError :message="form.errors.status" />
                    </div>
                </div>
            </section>

            <!-- Access period and payment -->
            <section :class="sectionClass" aria-labelledby="individual-access">
                <h3 id="individual-access" :class="headingClass">
                    <CalendarDays class="size-4" aria-hidden="true" />
                    Access period &amp; payment
                </h3>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid min-w-0 gap-1.5">
                        <Label for="individual-starts" :class="labelClass"
                            >Starts on</Label
                        >
                        <input
                            id="individual-starts"
                            v-model="form.starts_on"
                            type="date"
                            :class="inputClass"
                        />
                        <InputError :message="form.errors.starts_on" />
                    </div>
                    <div class="grid min-w-0 gap-1.5">
                        <Label for="individual-ends" :class="labelClass"
                            >Ends on
                            <span class="text-ink-slate font-normal"
                                >(empty = no end)</span
                            ></Label
                        >
                        <input
                            id="individual-ends"
                            v-model="form.ends_on"
                            type="date"
                            :class="inputClass"
                        />
                        <InputError :message="form.errors.ends_on" />
                    </div>
                    <div class="grid min-w-0 gap-1.5">
                        <Label for="individual-price" :class="labelClass"
                            >Price paid (DZD)
                            <span class="text-ink-slate font-normal"
                                >(optional)</span
                            ></Label
                        >
                        <input
                            id="individual-price"
                            v-model.number="form.price_dzd"
                            type="number"
                            min="0"
                            step="1"
                            :class="inputClass"
                        />
                        <InputError :message="form.errors.price_dzd" />
                    </div>
                    <div class="grid min-w-0 gap-1.5">
                        <Label for="individual-reference" :class="labelClass"
                            >Payment reference
                            <span class="text-ink-slate font-normal"
                                >(optional)</span
                            ></Label
                        >
                        <Input
                            id="individual-reference"
                            v-model="form.payment_reference"
                            maxlength="120"
                            :class="inputClass"
                        />
                        <InputError :message="form.errors.payment_reference" />
                    </div>
                </div>
            </section>

            <!-- AI configuration -->
            <section :class="sectionClass" aria-labelledby="individual-ai">
                <h3 id="individual-ai" :class="headingClass">
                    <Bot class="size-4" aria-hidden="true" />
                    AI configuration
                </h3>

                <div class="grid gap-2 sm:grid-cols-2">
                    <label
                        class="border-line hover:bg-app-alt flex min-h-11 cursor-pointer items-center gap-3 rounded-md border px-3 py-2"
                    >
                        <input
                            v-model="form.ai_enabled"
                            type="checkbox"
                            class="accent-brand-600 size-4"
                            data-test="individual-ai-enabled"
                        />
                        <span class="grid">
                            <span
                                class="text-brand-900 text-[13px] font-semibold"
                                >AI practice</span
                            >
                            <span class="text-ink-slate text-[11.5px]"
                                >Role-play, AI feedback and coaching</span
                            >
                        </span>
                    </label>
                    <label
                        class="border-line hover:bg-app-alt flex min-h-11 cursor-pointer items-center gap-3 rounded-md border px-3 py-2"
                    >
                        <input
                            v-model="form.voice_enabled"
                            type="checkbox"
                            :disabled="!form.ai_enabled"
                            class="accent-brand-600 size-4"
                        />
                        <span class="grid">
                            <span
                                class="text-brand-900 flex items-center gap-1 text-[13px] font-semibold"
                                ><Mic class="size-3.5" aria-hidden="true" />
                                Voice calls</span
                            >
                            <span class="text-ink-slate text-[11.5px]"
                                >Live spoken role-play with the AI guest</span
                            >
                        </span>
                    </label>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid min-w-0 gap-1.5">
                        <Label for="individual-points" :class="labelClass"
                            >AI points per month</Label
                        >
                        <input
                            id="individual-points"
                            v-model.number="form.ai_points_allocated"
                            type="number"
                            min="0"
                            step="1"
                            required
                            :class="inputClass"
                        />
                        <InputError
                            :message="form.errors.ai_points_allocated"
                        />
                    </div>
                    <div class="grid min-w-0 gap-1.5">
                        <Label for="individual-turns" :class="labelClass"
                            >AI turns per day
                            <span class="text-ink-slate font-normal"
                                >(empty = {{ defaults.dailyAiTurns }})</span
                            ></Label
                        >
                        <input
                            id="individual-turns"
                            v-model.number="form.daily_ai_turns"
                            type="number"
                            min="1"
                            step="1"
                            :placeholder="String(defaults.dailyAiTurns)"
                            :class="inputClass"
                        />
                        <InputError :message="form.errors.daily_ai_turns" />
                    </div>
                    <div class="grid min-w-0 gap-1.5">
                        <Label for="individual-action-cost" :class="labelClass"
                            >Points per AI action</Label
                        >
                        <input
                            id="individual-action-cost"
                            v-model.number="form.ai_action_points"
                            type="number"
                            min="0"
                            step="1"
                            required
                            :class="inputClass"
                        />
                        <InputError :message="form.errors.ai_action_points" />
                    </div>
                    <div class="grid min-w-0 gap-1.5">
                        <Label for="individual-voice-cost" :class="labelClass"
                            >Points per 10 min of voice</Label
                        >
                        <input
                            id="individual-voice-cost"
                            v-model.number="form.voice_points_per_10_minutes"
                            type="number"
                            min="0"
                            step="1"
                            required
                            :class="inputClass"
                        />
                        <InputError
                            :message="form.errors.voice_points_per_10_minutes"
                        />
                    </div>
                </div>
            </section>

            <div class="grid gap-1.5">
                <Label for="individual-notes" :class="labelClass"
                    >Notes
                    <span class="text-ink-slate font-normal"
                        >(optional)</span
                    ></Label
                >
                <textarea
                    id="individual-notes"
                    v-model="form.notes"
                    rows="2"
                    maxlength="1000"
                    class="border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 w-full resize-none rounded-sm border px-3 py-2 text-[13px] focus-visible:ring-3 focus-visible:outline-none"
                />
                <InputError :message="form.errors.notes" />
            </div>

            <div
                class="flex flex-col-reverse justify-end gap-2 pt-1 sm:flex-row"
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
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97]"
                    :disabled="form.processing"
                    data-test="save-individual-button"
                >
                    {{ editing ? 'Save changes' : 'Add subscriber' }}
                </Button>
            </div>
        </form>
    </HotelsModal>
</template>
