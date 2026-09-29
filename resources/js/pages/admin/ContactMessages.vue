<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ContactMessagesPanel from '@/components/contact-messages/ContactMessagesPanel.vue';
import type {
    ContactMessagesFilter,
    ContactMessagesPage,
} from '@/components/contact-messages/ContactMessagesPanel.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import ScriptAccent from '@/components/shell/ScriptAccent.vue';
import { tk } from '@/lib/i18n';
import { contactMessages, dashboard } from '@/routes';

/**
 * Contact Requests (user request 2026-09-26): everyone who wrote from the
 * public Contact Us page, with the details they left. Each message is also
 * emailed to the business email set in Website Management.
 */
type Props = {
    messages: ContactMessagesPage;
    show: ContactMessagesFilter;
    counts: Record<ContactMessagesFilter, number>;
};

defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: tk('Dashboard'), href: dashboard() },
            { title: tk('Contact Requests'), href: contactMessages() },
        ],
    },
});
</script>

<template>
    <Head :title="$t('Contact Requests')" />

    <div class="flex min-w-0 flex-col gap-2.5 px-4 pt-5 pb-5 md:px-6">
        <PageHeader
            :title="$t('Contact Requests')"
            :description="
                $t(
                    'Everyone who wrote to you from the Contact Us page, with the details they left.',
                )
            "
            class="mb-1"
        >
            <template #accent>
                <ScriptAccent />
            </template>
        </PageHeader>

        <ContactMessagesPanel
            :messages="messages"
            :show="show"
            :counts="counts"
        />
    </div>
</template>
