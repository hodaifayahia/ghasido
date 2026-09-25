<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Star } from '@lucide/vue';
import LearnerEmptyState from '@/components/learning/LearnerEmptyState.vue';
import PhrasebookEntryCard from '@/components/learning/PhrasebookEntryCard.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import { lessons } from '@/routes/learn';
import type { PhrasebookEntry } from '@/types';

/*
 * My Phrasebook (PHRASE-01..05; spec 0003 Part E). No client mockup covers
 * this page yet: design-system cards, refined by the learn-support lane.
 */
type Props = {
    items: PhrasebookEntry[];
};

defineProps<Props>();
</script>

<template>
    <Head title="My Phrasebook" />
    <h1 class="sr-only">My Phrasebook</h1>

    <div class="flex min-w-0 flex-col gap-4 px-4 pt-5 pb-8 md:px-6">
        <PageHeader
            title="My Phrasebook"
            :description="`${items.length} saved ${items.length === 1 ? 'phrase' : 'phrases'}`"
        />

        <LearnerEmptyState
            v-if="items.length === 0"
            :icon="Star"
            text="Tap the star on any word or expression in a lesson to keep it here."
            :action="{ label: 'Go to My Lessons', href: lessons() }"
        />

        <div v-else class="grid min-w-0 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <PhrasebookEntryCard
                v-for="entry in items"
                :key="entry.id"
                :entry="entry"
            />
        </div>
    </div>
</template>
