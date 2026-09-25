<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Clock } from '@lucide/vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import ScriptAccent from '@/components/shell/ScriptAccent.vue';

/*
 * Where a user who is not the platform owner lands on /dashboard, until their
 * own journey is built (spec 0001, AC-7). The admin figures are never in this
 * page's payload, so there is nothing here to hide.
 *
 * The page name has no area prefix, so app.ts falls through to AppLayout and
 * the user gets the shell with the sidebar filtered to what they may open.
 *
 * Copy comes from the server so it goes through __() (I18N-02).
 */
type Props = {
    title: string;
    body: string;
};

defineProps<Props>();
</script>

<template>
    <Head :title="title" />

    <div class="flex min-w-0 flex-col gap-3 px-4 pt-5 pb-8 md:px-6">
        <PageHeader :title="title" class="mb-1">
            <template #accent>
                <ScriptAccent />
            </template>
        </PageHeader>

        <section
            class="border-line bg-surface shadow-card flex min-w-0 flex-col items-center gap-4 rounded-lg border px-6 py-14 text-center"
        >
            <div
                class="bg-brand-50 text-brand-600 grid size-14 shrink-0 place-items-center rounded-xl"
            >
                <Clock :size="28" :stroke-width="1.75" aria-hidden="true" />
            </div>

            <p class="text-ink-slate max-w-md text-base leading-7">
                {{ body }}
            </p>
        </section>
    </div>
</template>
