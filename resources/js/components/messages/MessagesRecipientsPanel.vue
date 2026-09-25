<script setup lang="ts">
import { Mail, Search } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import MessagesPager from '@/components/messages/MessagesPager.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import type {
    MessageConsentStatus,
    MessagePagination,
    MessageRecipient,
    MessageRecipientStatus,
} from '@/types';

type Props = {
    recipients: MessageRecipient[];
    pagination: MessagePagination;
    /** The server's current search term. */
    search: string;
    /** Whether the signed in user may press Send (ReminderPolicy::send). */
    canSend: boolean;
    /** True while a partial reload is in flight, for the skeleton rows. */
    loading?: boolean;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), { loading: false });

/** The ticked employees, by id, kept across pages (REM-02). */
const selected = defineModel<number[]>('selected', { required: true });

/** "Select all matching": the server resolves everyone behind the filters. */
const allMatching = defineModel<boolean>('allMatching', { required: true });

const emit = defineEmits<{
    search: [term: string];
    page: [page: number];
    /** Send to these employees now. */
    send: [ids: number[]];
}>();

const search = ref(props.search);

watch(
    () => props.search,
    (value) => {
        search.value = value;
    },
);

// Debounced so a keystroke does not repaint the page.
watchDebounced(
    search,
    (value) => {
        if (value !== props.search) {
            emit('search', value);
        }
    },
    { debounce: 300 },
);

const selectedSet = computed(() => new Set(selected.value));

const pageIds = computed(() => props.recipients.map((row) => row.id));

const pageAllSelected = computed(
    () =>
        pageIds.value.length > 0 &&
        pageIds.value.every((id) => selectedSet.value.has(id)),
);

const pageSomeSelected = computed(() =>
    pageIds.value.some((id) => selectedSet.value.has(id)),
);

function isSelected(id: number): boolean {
    return allMatching.value || selectedSet.value.has(id);
}

function toggle(id: number, checked: boolean | 'indeterminate'): void {
    allMatching.value = false;

    const next = new Set(selected.value);

    if (checked === true) {
        next.add(id);
    } else {
        next.delete(id);
    }

    selected.value = [...next];
}

function togglePage(checked: boolean | 'indeterminate'): void {
    allMatching.value = false;

    const next = new Set(selected.value);

    for (const id of pageIds.value) {
        if (checked === true) {
            next.add(id);
        } else {
            next.delete(id);
        }
    }

    selected.value = [...next];
}

function selectEveryone(): void {
    allMatching.value = true;
    selected.value = [...pageIds.value];
}

function clearSelection(): void {
    allMatching.value = false;
    selected.value = [];
}

const selectionSummary = computed(() => {
    if (allMatching.value) {
        return `All ${props.pagination.total} employees matching the filters are selected.`;
    }

    const count = selected.value.length;

    return count === 1
        ? '1 employee selected.'
        : `${count} employees selected.`;
});

const consentTone: Record<MessageConsentStatus, string> = {
    granted: 'bg-success-tint text-success-text',
    not_granted: 'bg-danger-tint text-danger-text',
};

const consentText: Record<MessageConsentStatus, string> = {
    granted: 'Granted',
    not_granted: 'Missing',
};

const statusTone: Record<MessageRecipientStatus, string> = {
    active: 'bg-success-tint text-success-text',
    in_progress: 'bg-warning-tint text-warning-text',
    inactive: 'bg-danger-tint text-danger-text',
    consent_pending: 'bg-brand-100/70 text-brand-700',
};

const statusText: Record<MessageRecipientStatus, string> = {
    active: 'Active',
    in_progress: 'In progress',
    inactive: 'Inactive',
    consent_pending: 'Consent pending',
};

const skeletonRows = [0, 1, 2, 3, 4];
</script>

<template>
    <section
        :aria-busy="loading || undefined"
        :class="
            cn(
                'border-line bg-surface shadow-card rounded-lg border p-3',
                props.class,
            )
        "
    >
        <div class="flex min-h-8 items-center justify-between gap-3">
            <div>
                <h2 class="font-heading text-brand-800 text-base font-semibold">
                    Reminder Recipients
                </h2>
                <p class="text-ink-slate mt-0.5 text-[12px] leading-4">
                    Review consent, inactivity and send status before messaging.
                </p>
            </div>
        </div>

        <div class="relative mt-3 min-w-0">
            <Search
                aria-hidden="true"
                class="text-ink-faint absolute start-3 top-1/2 size-4 -translate-y-1/2"
            />
            <Input
                v-model="search"
                type="search"
                placeholder="Search by employee, hotel or department..."
                aria-label="Search recipients"
                data-test="messages-search-input"
                class="border-line placeholder:text-ink-faint bg-surface h-9 rounded-md ps-9 pe-3 text-[12.5px] shadow-none"
            />
        </div>

        <div
            v-if="canSend && (selected.length > 0 || allMatching)"
            class="bg-brand-50 text-brand-900 mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-md px-3 py-2 text-[12px] leading-4"
            role="status"
        >
            <span class="font-semibold">{{ selectionSummary }}</span>
            <button
                v-if="!allMatching && pagination.total > selected.length"
                type="button"
                class="text-brand-600 font-semibold underline-offset-2 hover:underline"
                data-test="select-all-matching-button"
                @click="selectEveryone"
            >
                Select all {{ pagination.total }} matching
            </button>
            <button
                type="button"
                class="text-ink-slate ms-auto font-medium underline-offset-2 hover:underline"
                data-test="clear-selection-button"
                @click="clearSelection"
            >
                Clear
            </button>
        </div>

        <div class="border-line/80 mt-3 overflow-hidden rounded-lg border">
            <div class="hidden overflow-x-auto md:block">
                <table
                    class="min-w-full table-fixed border-collapse text-start"
                >
                    <thead class="bg-tint-header">
                        <tr
                            class="text-brand-900 text-[12px] leading-4 font-semibold"
                        >
                            <th class="w-9 py-2 ps-3 pe-2 text-start">
                                <Checkbox
                                    :model-value="
                                        pageAllSelected
                                            ? true
                                            : pageSomeSelected
                                              ? 'indeterminate'
                                              : false
                                    "
                                    :disabled="
                                        !canSend || recipients.length === 0
                                    "
                                    aria-label="Select every employee on this page"
                                    data-test="select-page-checkbox"
                                    @update:model-value="togglePage"
                                />
                            </th>
                            <th class="w-[168px] px-2 py-2 text-start">
                                Employee
                            </th>
                            <th class="w-[126px] px-2 py-2 text-start">
                                Hotel
                            </th>
                            <th class="w-[100px] px-2 py-2 text-start">
                                Department
                            </th>
                            <th class="w-[104px] px-2 py-2 text-start">
                                Last Activity
                            </th>
                            <th class="w-[94px] px-2 py-2 text-start">
                                Consent
                            </th>
                            <th class="w-[112px] px-2 py-2 text-start">
                                Status
                            </th>
                            <th class="w-[112px] px-2 py-2 text-start">
                                Action
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-surface text-ink text-[12.5px]">
                        <template v-if="loading && recipients.length === 0">
                            <tr
                                v-for="row in skeletonRows"
                                :key="row"
                                class="border-line/80 border-t"
                            >
                                <td colspan="8" class="px-3 py-3">
                                    <Skeleton
                                        class="bg-tint-track h-5 w-full"
                                    />
                                </td>
                            </tr>
                        </template>

                        <tr
                            v-else-if="recipients.length === 0"
                            class="border-line/80 border-t"
                        >
                            <td colspan="8" class="px-3 py-10 text-center">
                                <p
                                    class="font-heading text-brand-900 text-[15px] font-semibold"
                                >
                                    No employees match these filters
                                </p>
                                <p class="text-ink-slate mt-1 text-[13px]">
                                    Try another search, or reset the filters.
                                </p>
                            </td>
                        </tr>

                        <tr
                            v-for="recipient in recipients"
                            :key="recipient.id"
                            :class="
                                cn(
                                    'border-line/80 hover:bg-brand-50/35 border-t',
                                    isSelected(recipient.id) &&
                                        'bg-brand-50/50',
                                )
                            "
                        >
                            <td class="py-[7px] ps-3 pe-2 align-middle">
                                <Checkbox
                                    :model-value="isSelected(recipient.id)"
                                    :disabled="!canSend"
                                    :aria-label="`Select ${recipient.name}`"
                                    :data-test="`select-recipient-${recipient.id}`"
                                    @update:model-value="
                                        toggle(recipient.id, $event)
                                    "
                                />
                            </td>
                            <td class="px-2 py-[7px] align-middle">
                                <div class="min-w-0">
                                    <span
                                        class="text-brand-900 block truncate font-medium"
                                    >
                                        {{ recipient.name }}
                                    </span>
                                    <span
                                        class="text-ink-slate block truncate text-[11.5px]"
                                    >
                                        {{ recipient.inactivityLabel }}
                                    </span>
                                </div>
                            </td>
                            <td
                                class="text-ink-muted px-2 py-[7px] align-middle"
                            >
                                {{ recipient.hotel }}
                            </td>
                            <td
                                class="text-ink-muted px-2 py-[7px] align-middle"
                            >
                                {{ recipient.department }}
                            </td>
                            <td
                                class="text-ink-muted px-2 py-[7px] align-middle"
                            >
                                {{ recipient.lastActivity }}
                            </td>
                            <td class="px-2 py-[7px] align-middle">
                                <span
                                    :class="
                                        cn(
                                            'rounded-pill inline-flex min-h-5 min-w-[72px] items-center justify-center px-2 text-[10.5px] font-semibold whitespace-nowrap',
                                            consentTone[
                                                recipient.consentStatus
                                            ],
                                        )
                                    "
                                >
                                    {{ consentText[recipient.consentStatus] }}
                                </span>
                            </td>
                            <td class="px-2 py-[7px] align-middle">
                                <span
                                    :class="
                                        cn(
                                            'rounded-pill inline-flex min-h-5 min-w-[90px] items-center justify-center px-2 text-[10.5px] font-semibold whitespace-nowrap',
                                            statusTone[recipient.status],
                                        )
                                    "
                                >
                                    {{ statusText[recipient.status] }}
                                </span>
                            </td>
                            <td class="px-2 py-[7px] align-middle">
                                <button
                                    type="button"
                                    :disabled="!canSend"
                                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 inline-flex h-8 min-w-[96px] items-center justify-center gap-1.5 rounded-md border px-3 text-[11.5px] font-semibold focus-visible:ring-3 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60"
                                    :aria-label="`Send reminder to ${recipient.name}`"
                                    :data-test="`send-to-${recipient.id}-button`"
                                    @click="emit('send', [recipient.id])"
                                >
                                    <Mail class="size-3.5" aria-hidden="true" />
                                    Send
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <ul class="divide-line divide-y md:hidden">
                <li
                    v-if="recipients.length === 0"
                    class="bg-surface px-4 py-10 text-center"
                >
                    <p
                        class="font-heading text-brand-900 text-[15px] font-semibold"
                    >
                        No employees match these filters
                    </p>
                    <p class="text-ink-slate mt-1 text-[13px]">
                        Try another search, or reset the filters.
                    </p>
                </li>
                <li
                    v-for="recipient in recipients"
                    :key="recipient.id"
                    class="bg-surface p-4"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p
                                class="font-heading text-brand-800 text-[15px] leading-5 font-semibold"
                            >
                                {{ recipient.name }}
                            </p>
                            <p
                                class="text-ink-muted mt-0.5 text-[13px] leading-5"
                            >
                                {{ recipient.hotel }}
                            </p>
                        </div>
                        <Checkbox
                            :model-value="isSelected(recipient.id)"
                            :disabled="!canSend"
                            :aria-label="`Select ${recipient.name}`"
                            class="size-6"
                            @update:model-value="toggle(recipient.id, $event)"
                        />
                    </div>

                    <div
                        class="text-ink-muted mt-3 grid gap-2 text-[13px] leading-5"
                    >
                        <p>
                            <span class="text-brand-900 font-medium"
                                >Department:</span
                            >
                            {{ recipient.department }}
                        </p>
                        <p>
                            <span class="text-brand-900 font-medium"
                                >Last Activity:</span
                            >
                            {{ recipient.lastActivity }}
                        </p>
                        <p>
                            <span class="text-brand-900 font-medium"
                                >Inactivity:</span
                            >
                            {{ recipient.inactivityLabel }}
                        </p>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <span
                            :class="
                                cn(
                                    'rounded-pill inline-flex min-h-6 items-center px-2.5 text-[11px] font-semibold',
                                    consentTone[recipient.consentStatus],
                                )
                            "
                        >
                            Consent {{ consentText[recipient.consentStatus] }}
                        </span>
                        <span
                            :class="
                                cn(
                                    'rounded-pill inline-flex min-h-6 items-center px-2.5 text-[11px] font-semibold',
                                    statusTone[recipient.status],
                                )
                            "
                        >
                            {{ statusText[recipient.status] }}
                        </span>
                    </div>

                    <button
                        v-if="canSend"
                        type="button"
                        class="border-line text-brand-700 hover:bg-brand-50 bg-surface mt-3 inline-flex h-11 w-full items-center justify-center gap-2 rounded-md border text-[12.5px] font-semibold"
                        :aria-label="`Send reminder to ${recipient.name}`"
                        @click="emit('send', [recipient.id])"
                    >
                        <Mail class="size-4" aria-hidden="true" />
                        Send Reminder
                    </button>
                </li>
            </ul>
        </div>

        <MessagesPager
            :pagination="pagination"
            noun="employees"
            label="Recipients pagination"
            class="mt-2.5"
            @page="emit('page', $event)"
        />
    </section>
</template>
