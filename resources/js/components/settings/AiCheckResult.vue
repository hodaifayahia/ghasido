<script setup lang="ts">
import {
    CircleAlert,
    CircleCheck,
    CircleDashed,
    LoaderCircle,
} from '@lucide/vue';
import { computed } from 'vue';
import type { AiCheckState } from '@/types';

/*
 * The last connection test of one capability, as icon + words, never colour
 * alone (ACC-02); a failure shows the provider's own error text.
 */
type Props = {
    check: AiCheckState | null;
};

const props = defineProps<Props>();

const when = computed((): string => {
    const at = props.check?.checked_at;

    return at ? new Date(at).toLocaleString() : '';
});
</script>

<template>
    <div role="status" aria-live="polite" class="min-w-0 text-sm">
        <p v-if="!check" class="text-ink-muted flex items-center gap-2">
            <CircleDashed class="size-4 shrink-0" aria-hidden="true" />
            Not tested yet
        </p>

        <p
            v-else-if="check.status === 'pending' || check.status === 'running'"
            class="text-brand-700 flex items-center gap-2"
        >
            <LoaderCircle
                class="size-4 shrink-0 animate-spin motion-reduce:animate-none"
                aria-hidden="true"
            />
            {{ check.status === 'pending' ? 'Queued…' : 'Testing…' }}
        </p>

        <div v-else-if="check.status === 'ok'" class="grid min-w-0 gap-0.5">
            <p class="text-success-text flex items-center gap-2 font-semibold">
                <CircleCheck class="size-4 shrink-0" aria-hidden="true" />
                Working · {{ check.latency_ms ?? '?' }} ms
            </p>
            <p class="text-ink-muted ps-6 text-xs wrap-anywhere">
                {{ check.provider }} · {{ check.model }} — {{ check.detail }}
            </p>
            <p class="text-ink-faint ps-6 text-xs">{{ when }}</p>
        </div>

        <div v-else class="grid min-w-0 gap-0.5">
            <p class="text-danger-text flex items-center gap-2 font-semibold">
                <CircleAlert class="size-4 shrink-0" aria-hidden="true" />
                Failed<template v-if="check.latency_ms !== null">
                    · {{ check.latency_ms }} ms</template
                >
            </p>
            <p
                class="text-ink ps-6 font-mono text-xs wrap-anywhere whitespace-pre-wrap"
            >
                {{ check.error }}
            </p>
            <p class="text-ink-faint ps-6 text-xs">
                {{ check.provider }} · {{ check.model }} · {{ when }}
            </p>
        </div>
    </div>
</template>
