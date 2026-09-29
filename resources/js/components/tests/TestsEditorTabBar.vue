<script setup lang="ts">
import { ClipboardList, Eye, Settings2 } from '@lucide/vue';
import { nextTick, ref } from 'vue';
import type { Component } from 'vue';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { TestEditorTab } from '@/types';

/*
 * Questions / Settings / Preview inside the Create / Edit Test builder
 * (client request 2026-09-29). A WAI-ARIA tablist: arrow keys, Home and End
 * move between tabs; only the active tab is in the tab order.
 */
type Props = {
    /** Tabs holding a validation error from the last save. */
    flagged?: TestEditorTab[];
};

withDefaults(defineProps<Props>(), { flagged: () => [] });

const active = defineModel<TestEditorTab>({ required: true });

const tabs: { key: TestEditorTab; label: string; icon: Component }[] = [
    { key: 'questions', label: tk('Questions'), icon: ClipboardList },
    { key: 'settings', label: tk('Settings'), icon: Settings2 },
    { key: 'preview', label: tk('Preview'), icon: Eye },
];

const buttons = ref<HTMLButtonElement[]>([]);

function focusTab(index: number): void {
    const count = tabs.length;
    const next = tabs[(index + count) % count];

    if (next === undefined) {
        return;
    }

    active.value = next.key;
    void nextTick(() => {
        buttons.value.find((el) => el.dataset.tab === next.key)?.focus();
    });
}

function onKeydown(event: KeyboardEvent, index: number): void {
    // In RTL the visual order flips, so the arrow keys follow it.
    const rtl = document.documentElement.dir === 'rtl';
    const forward = rtl ? 'ArrowLeft' : 'ArrowRight';
    const back = rtl ? 'ArrowRight' : 'ArrowLeft';

    if (event.key === forward) {
        focusTab(index + 1);
    } else if (event.key === back) {
        focusTab(index - 1);
    } else if (event.key === 'Home') {
        focusTab(0);
    } else if (event.key === 'End') {
        focusTab(tabs.length - 1);
    } else {
        return;
    }

    event.preventDefault();
}
</script>

<template>
    <div
        class="bg-surface border-line inline-flex max-w-full gap-0.5 rounded-md border p-1"
        role="tablist"
        :aria-label="$t('Test builder')"
    >
        <button
            v-for="(tab, index) in tabs"
            :id="`test-editor-tab-${tab.key}`"
            :key="tab.key"
            ref="buttons"
            type="button"
            role="tab"
            :data-tab="tab.key"
            :aria-selected="active === tab.key"
            :aria-controls="`test-editor-panel-${tab.key}`"
            :tabindex="active === tab.key ? 0 : -1"
            :data-test="`test-editor-tab-${tab.key}`"
            :class="
                cn(
                    'focus-visible:ring-brand-600/15 relative inline-flex min-h-11 items-center gap-1.5 rounded px-3 text-[12px] font-semibold whitespace-nowrap transition-colors duration-150 focus-visible:ring-3 focus-visible:outline-none md:min-h-9',
                    active === tab.key
                        ? 'bg-brand-100/70 text-brand-700'
                        : 'text-ink-slate hover:bg-brand-50',
                )
            "
            @click="active = tab.key"
            @keydown="onKeydown($event, index)"
        >
            <component :is="tab.icon" class="size-4" aria-hidden="true" />
            {{ $t(tab.label) }}
            <span
                v-if="flagged.includes(tab.key)"
                class="bg-danger size-1.5 rounded-full"
                :title="$t('Needs attention')"
            />
            <span v-if="flagged.includes(tab.key)" class="sr-only">
                {{ $t('Needs attention') }}
            </span>
        </button>
    </div>
</template>
