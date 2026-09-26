<script setup lang="ts">
import { Eye, EyeOff } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { useI18n } from '@/composables/useI18n';
import { cn } from '@/lib/utils';

/*
 * The 🌐 Show Meaning toggle (CTRL-01..04), drawn as desginphotos/employ/
 * photo_2 draws it: a 345×62 grid-tint box with a 24px eye, the Arabic
 * "إظهار المعنى" (15px Cairo) over "Show Meaning" (13px). `sm` is the
 * 224×44 variant under an example sentence. The parent owns the state
 * (useShowMeaning) and never renders this inside a test (CTRL-04).
 */
type Props = {
    shown: boolean;
    disabled?: boolean;
    size?: 'md' | 'sm';
    /** Id of the panel this button controls, for aria-controls. */
    controls?: string;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
    size: 'md',
    controls: undefined,
});

const emit = defineEmits<{ toggle: [] }>();

// The button already carries the Arabic label; in the Arabic interface the
// English second line would only repeat it (I18N-02).
const { locale } = useI18n();
</script>

<template>
    <button
        type="button"
        :disabled="disabled"
        :aria-expanded="shown"
        :aria-controls="controls"
        :class="
            cn(
                'bg-tint-grid ease-brand hover:bg-line focus-visible:ring-brand-600/40 flex items-center justify-center gap-4 rounded-md transition-colors duration-150 focus-visible:ring-3 focus-visible:outline-none active:scale-[.98] disabled:cursor-not-allowed disabled:opacity-50 motion-reduce:transition-none',
                size === 'md'
                    ? 'min-h-[62px] w-full px-6 py-2'
                    : 'min-h-11 px-5 py-1.5',
                props.class,
            )
        "
        @click="emit('toggle')"
    >
        <component
            :is="shown ? EyeOff : Eye"
            :class="
                cn(
                    'text-ink-graphite shrink-0',
                    size === 'md' ? 'size-6' : 'size-5',
                )
            "
            aria-hidden="true"
        />
        <span class="flex flex-col items-center leading-tight">
            <span
                lang="ar"
                dir="rtl"
                :class="
                    cn(
                        'font-arabic text-ink font-semibold',
                        size === 'md' ? 'text-[15px]' : 'text-[13px]',
                    )
                "
            >
                {{ shown ? 'إخفاء المعنى' : 'إظهار المعنى' }}
            </span>
            <span
                v-if="locale === 'en'"
                :class="
                    cn(
                        'text-ink-slate',
                        size === 'md' ? 'text-[13px]' : 'text-xs',
                    )
                "
            >
                {{ shown ? $t('Hide Meaning') : $t('Show Meaning') }}
            </span>
        </span>
    </button>
</template>
