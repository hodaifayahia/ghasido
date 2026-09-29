<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Inbox } from '@lucide/vue';
import { ref } from 'vue';
import DeleteRowDialog from '@/components/common/DeleteRowDialog.vue';
import PanelCard from '@/components/common/PanelCard.vue';
import ContactMessageCard from '@/components/contact-messages/ContactMessageCard.vue';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { contactMessages } from '@/routes';
import { destroy as destroyMessage } from '@/routes/contact-messages';
import type { LandingContactMessage } from '@/types';

export type ContactMessagesFilter = 'new' | 'all';

export type ContactMessagesPage = {
    data: LandingContactMessage[];
    total: number;
    current_page: number;
    last_page: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
    messages: ContactMessagesPage;
    show: ContactMessagesFilter;
    counts: Record<ContactMessagesFilter, number>;
};

defineProps<Props>();

const filters: { value: ContactMessagesFilter; label: string }[] = [
    { value: 'new', label: tk('New') },
    { value: 'all', label: tk('All') },
];

function filterBy(value: ContactMessagesFilter): void {
    router.get(
        contactMessages.url({
            query: value === 'new' ? { show: 'new' } : {},
        }),
        {},
        { preserveScroll: true },
    );
}

const deleteOpen = ref(false);
const deleting = ref<LandingContactMessage | null>(null);

function remove(message: LandingContactMessage): void {
    deleting.value = message;
    deleteOpen.value = true;
}
</script>

<template>
    <PanelCard :title="$t('Contact requests')" title-id="contact-requests">
        <template #icon>
            <span
                class="bg-brand-50 text-brand-600 hidden size-8 shrink-0 place-items-center rounded-lg sm:grid"
            >
                <Inbox class="size-4" aria-hidden="true" />
            </span>
        </template>
        <template #actions>
            <div
                class="border-line bg-app flex shrink-0 rounded-md border p-0.5"
                role="group"
                :aria-label="$t('Filter contact requests')"
            >
                <button
                    v-for="filter in filters"
                    :key="filter.value"
                    type="button"
                    :aria-pressed="show === filter.value"
                    :data-test="`contact-requests-filter-${filter.value}`"
                    :class="
                        cn(
                            'focus-visible:ring-brand-600 min-h-9 rounded-[8px] px-3 text-[12px] font-semibold whitespace-nowrap focus-visible:ring-2 focus-visible:outline-none',
                            show === filter.value
                                ? 'bg-surface text-brand-700 shadow-card'
                                : 'text-ink-slate hover:text-brand-700',
                        )
                    "
                    @click="filterBy(filter.value)"
                >
                    {{ $t(filter.label) }} ({{ counts[filter.value] }})
                </button>
            </div>
        </template>

        <p
            v-if="messages.data.length === 0"
            class="text-ink-slate py-8 text-center text-[13px]"
        >
            {{
                show === 'new'
                    ? $t('No new contact requests. You are all caught up.')
                    : $t(
                          'No messages yet. Messages sent from the Contact Us page appear here.',
                      )
            }}
        </p>
        <ul v-else class="grid gap-3">
            <ContactMessageCard
                v-for="message in messages.data"
                :key="message.id"
                :message="message"
                @delete="remove"
            />
        </ul>

        <nav
            v-if="messages.last_page > 1"
            :aria-label="$t('Contact request pages')"
            class="border-line -mx-4 mt-4 -mb-4 flex flex-wrap items-center justify-center gap-1 border-t px-3 py-3"
        >
            <button
                v-for="link in messages.links"
                :key="link.label"
                type="button"
                :disabled="!link.url"
                :aria-current="link.active ? 'page' : undefined"
                :class="
                    link.active
                        ? 'bg-brand-600 text-white'
                        : 'border-line text-ink-slate hover:bg-app disabled:opacity-40'
                "
                class="min-w-8 rounded-md border px-2 py-1.5 text-[11px]"
                @click="
                    link.url &&
                    router.get(link.url, {}, { preserveScroll: true })
                "
            >
                <span v-if="link.label.includes('Previous')">{{
                    $t('Previous')
                }}</span>
                <span v-else-if="link.label.includes('Next')">{{
                    $t('Next')
                }}</span>
                <span v-else>{{
                    link.label.replace(/&laquo;|&raquo;/g, '')
                }}</span>
            </button>
        </nav>

        <DeleteRowDialog
            v-model:open="deleteOpen"
            :url="deleting ? destroyMessage.url(deleting.id) : null"
            :name="
                deleting
                    ? $t('The message from :name', { name: deleting.name })
                    : ''
            "
            :kind="$t('message')"
        />
    </PanelCard>
</template>
