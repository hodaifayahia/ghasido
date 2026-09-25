<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import type { MessagePagination } from '@/types';

type Props = {
    pagination: MessagePagination;
    /** What is being counted, e.g. "employees" or "reminders". */
    noun: string;
    label: string;
    /** Compact: previous/next only, for the sidebar log card. */
    compact?: boolean;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), { compact: false });

const emit = defineEmits<{
    page: [page: number];
}>();
</script>

<template>
    <div
        :class="
            cn(
                'text-ink-muted flex flex-col gap-2 text-[12.5px] leading-5 md:flex-row md:items-center md:justify-between',
                compact &&
                    'flex-row items-center justify-between text-[11.5px]',
                props.class,
            )
        "
    >
        <p>
            Showing {{ pagination.from }}-{{ pagination.to }} of
            {{ pagination.total }} {{ noun }}
        </p>

        <nav :aria-label="label" class="flex flex-wrap items-center gap-1.5">
            <button
                type="button"
                :disabled="pagination.currentPage <= 1"
                :aria-label="`Previous page of ${noun}`"
                :class="
                    cn(
                        'text-ink-muted hover:bg-brand-50 inline-flex min-h-8 items-center gap-1 rounded-md px-2 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:bg-transparent',
                        compact &&
                            'border-line bg-surface size-7 min-h-0 justify-center border px-0',
                    )
                "
                @click="emit('page', pagination.currentPage - 1)"
            >
                <ChevronLeft class="size-3.5" aria-hidden="true" />
                <template v-if="!compact">Previous</template>
            </button>

            <template v-if="!compact">
                <template v-for="page in pagination.pages" :key="String(page)">
                    <span
                        v-if="page === 'ellipsis'"
                        class="text-ink-muted inline-flex min-w-8 justify-center px-1"
                    >
                        ...
                    </span>
                    <button
                        v-else
                        type="button"
                        :aria-current="
                            page === pagination.currentPage ? 'page' : undefined
                        "
                        :class="
                            cn(
                                'inline-flex size-8 items-center justify-center rounded-md border text-[12.5px] font-semibold',
                                page === pagination.currentPage
                                    ? 'border-brand-600 bg-brand-600 text-surface shadow-btn'
                                    : 'border-line text-brand-800 hover:bg-brand-50 bg-surface',
                            )
                        "
                        @click="emit('page', page)"
                    >
                        {{ page }}
                    </button>
                </template>
            </template>

            <button
                type="button"
                :disabled="pagination.currentPage >= pagination.lastPage"
                :aria-label="`Next page of ${noun}`"
                :class="
                    cn(
                        'border-line text-brand-700 hover:bg-brand-50 bg-surface inline-flex min-h-8 items-center gap-1 rounded-md border px-2.5 text-[12.5px] font-semibold disabled:cursor-not-allowed disabled:opacity-60',
                        compact && 'size-7 min-h-0 justify-center px-0',
                    )
                "
                @click="emit('page', pagination.currentPage + 1)"
            >
                <template v-if="!compact">Next</template>
                <ChevronRight class="size-3.5" aria-hidden="true" />
            </button>
        </nav>
    </div>
</template>
