<script setup lang="ts">
import { Eye, Gauge, Turtle } from '@lucide/vue';
import { useI18n } from '@/composables/useI18n';
import { cn } from '@/lib/utils';

/*
 * The Normal Speed / Slower Speed / Show Meaning trio under a prompt
 * (photo_8, photo_10): the first two pick the playback clip (CTRL-05), the
 * third reveals the Arabic (CTRL-02) and is disabled when there is none.
 */
type Props = {
    speed: 'normal' | 'slow';
    meaningShown?: boolean;
    meaningEnabled?: boolean;
};

withDefaults(defineProps<Props>(), {
    meaningShown: false,
    meaningEnabled: true,
});

const emit = defineEmits<{
    'update:speed': [value: 'normal' | 'slow'];
    toggleMeaning: [];
}>();

// The button already carries the Arabic label; the Arabic interface does
// not repeat it in English (I18N-02).
const { locale } = useI18n();

const base =
    'ease-brand focus-visible:ring-brand-600/40 flex h-14 items-center justify-center gap-2 rounded-md text-base font-semibold transition-colors focus-visible:ring-3 focus-visible:outline-none active:scale-[.98] motion-reduce:transition-none';
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-3">
        <button
            type="button"
            :aria-pressed="speed === 'normal'"
            :class="
                cn(
                    base,
                    speed === 'normal'
                        ? 'bg-brand-50 text-brand-700'
                        : 'bg-app-alt text-ink-slate hover:bg-brand-50',
                )
            "
            @click="emit('update:speed', 'normal')"
        >
            <Gauge class="size-5" aria-hidden="true" />
            {{ $t('Normal Speed') }}
        </button>

        <button
            type="button"
            :aria-pressed="speed === 'slow'"
            :class="
                cn(
                    base,
                    speed === 'slow'
                        ? 'bg-success-tint text-success-text'
                        : 'bg-app-alt text-ink-slate hover:bg-success-tint',
                )
            "
            @click="emit('update:speed', 'slow')"
        >
            <Turtle class="size-5 fill-current" aria-hidden="true" />
            {{ $t('Slower Speed') }}
        </button>

        <button
            type="button"
            :disabled="!meaningEnabled"
            :aria-pressed="meaningShown"
            :class="
                cn(
                    base,
                    meaningShown
                        ? 'bg-brand-100 text-brand-700'
                        : 'bg-tint-grid text-ink-slate hover:bg-brand-50',
                    !meaningEnabled && 'cursor-not-allowed opacity-40',
                )
            "
            @click="emit('toggleMeaning')"
        >
            <Eye class="size-5" aria-hidden="true" />
            <span class="flex flex-col items-center leading-tight">
                <span class="font-arabic text-sm" dir="rtl" lang="ar">
                    إظهار المعنى
                </span>
                <span v-if="locale === 'en'" class="text-xs">{{
                    $t('Show Meaning')
                }}</span>
            </span>
        </button>
    </div>
</template>
