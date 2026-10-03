<script setup lang="ts">
import {
    Download,
    Languages,
    Pencil,
    Sparkles,
    Square,
    Trash2,
    Upload,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { exportMethod } from '@/routes/interface-languages';
import type { InterfaceLanguageRow } from '@/types';

/*
 * One added interface language (client request 2026-10-03): how much is
 * translated, the switch that puts it in the language menu, AI translation,
 * hand editing, JSON export and import, and delete.
 */
type Props = {
    language: InterfaceLanguageRow;
    total: number;
    generating: boolean;
    editing: boolean;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    toggle: [enabled: boolean];
    generate: [];
    stop: [];
    edit: [];
    import: [file: File];
    remove: [];
}>();

const percent = computed(() =>
    props.total === 0
        ? 0
        : Math.min(
              100,
              Math.round((props.language.translated / props.total) * 100),
          ),
);
const complete = computed(() => props.language.translated >= props.total);
const fileInput = ref<HTMLInputElement | null>(null);

function onFile(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (file !== undefined) {
        emit('import', file);
    }

    (event.target as HTMLInputElement).value = '';
}
</script>

<template>
    <article
        :class="
            cn(
                'border-line bg-surface shadow-card grid min-w-0 gap-3 rounded-lg border p-4',
                editing && 'border-brand-300',
            )
        "
        :data-test="`interface-language-${language.code}`"
    >
        <header class="flex min-w-0 flex-wrap items-center gap-3">
            <span
                class="bg-brand-100 text-brand-700 grid size-9 shrink-0 place-items-center rounded-full"
            >
                <Languages class="size-4" aria-hidden="true" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-brand-900 truncate text-[14px] font-semibold">
                    <span :dir="language.dir">{{ language.native }}</span>
                    <span class="text-ink-slate font-medium">
                        · {{ language.name }} · {{ language.code }}</span
                    >
                </p>
                <p class="text-ink-slate text-[12px]">
                    {{
                        $t(':done of :total lines translated', {
                            done: language.translated,
                            total: total,
                        })
                    }}
                </p>
            </div>
            <label
                class="text-brand-900 flex min-h-11 cursor-pointer items-center gap-2 text-[12.5px] font-semibold"
            >
                <input
                    type="checkbox"
                    class="accent-brand-600 size-4"
                    :checked="language.enabled"
                    :data-test="`interface-language-${language.code}-enabled`"
                    @change="
                        emit(
                            'toggle',
                            ($event.target as HTMLInputElement).checked,
                        )
                    "
                />
                {{ $t('In the language menu') }}
            </label>
        </header>

        <div
            class="bg-tint-track h-2 overflow-hidden rounded-full"
            role="progressbar"
            :aria-valuenow="percent"
            aria-valuemin="0"
            aria-valuemax="100"
        >
            <div
                :class="
                    cn(
                        'h-full rounded-full transition-[width] duration-700 ease-out motion-reduce:transition-none',
                        complete ? 'bg-success' : 'bg-brand-600',
                    )
                "
                :style="{ width: `${percent}%` }"
            />
        </div>

        <p
            v-if="generating"
            class="text-brand-700 text-[12.5px] font-semibold"
            role="status"
        >
            {{
                $t(
                    'Translating with AI… :percent%. Keep this page open; you can stop and continue later.',
                    { percent },
                )
            }}
        </p>
        <p
            v-else-if="language.status === 'failed' && language.failedReason"
            class="bg-danger-tint text-danger-text rounded-md px-3 py-2 text-[12px]"
            role="alert"
        >
            {{ language.failedReason }}
        </p>

        <div class="flex flex-wrap gap-2">
            <Button
                v-if="generating"
                type="button"
                variant="outline"
                class="min-h-10"
                @click="emit('stop')"
            >
                <Square class="size-4" aria-hidden="true" />
                {{ $t('Stop') }}
            </Button>
            <Button
                v-else-if="!complete"
                type="button"
                class="min-h-10"
                :data-test="`interface-language-${language.code}-generate`"
                @click="emit('generate')"
            >
                <Sparkles class="size-4" aria-hidden="true" />
                {{
                    language.translated > 0
                        ? $t('Continue with AI')
                        : $t('Translate with AI')
                }}
            </Button>
            <Button
                type="button"
                variant="outline"
                class="min-h-10"
                :data-test="`interface-language-${language.code}-edit`"
                @click="emit('edit')"
            >
                <Pencil class="size-4" aria-hidden="true" />
                {{ $t('Edit by hand') }}
            </Button>
            <Button as-child variant="outline" class="min-h-10">
                <a :href="exportMethod.url(language.id)">
                    <Download class="size-4" aria-hidden="true" />
                    {{ $t('Export JSON') }}
                </a>
            </Button>
            <Button
                type="button"
                variant="outline"
                class="min-h-10"
                @click="fileInput?.click()"
            >
                <Upload class="size-4" aria-hidden="true" />
                {{ $t('Import JSON') }}
            </Button>
            <input
                ref="fileInput"
                type="file"
                accept="application/json,.json"
                class="sr-only"
                tabindex="-1"
                aria-hidden="true"
                @change="onFile"
            />
            <Button
                type="button"
                variant="outline"
                class="border-danger text-danger-text hover:bg-danger-tint ms-auto min-h-10"
                @click="emit('remove')"
            >
                <Trash2 class="size-4" aria-hidden="true" />
                {{ $t('Delete') }}
            </Button>
        </div>
    </article>
</template>
