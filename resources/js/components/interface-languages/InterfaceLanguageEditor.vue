<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, ChevronLeft, ChevronRight, Search } from '@lucide/vue';
import { reactive, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { update as updateString } from '@/routes/interface-languages/strings';
import type {
    InterfaceLanguageEditor,
    InterfaceLanguageRow,
    InterfaceStringRow,
} from '@/types';

/*
 * Write or correct an added language line by line (client request
 * 2026-10-03). Each line is the English text beside its translation; an
 * empty line shows in English.
 */
type Props = {
    language: InterfaceLanguageRow;
    editor: InterfaceLanguageEditor;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    query: [query: { search?: string; show?: string; page?: number }];
}>();

const search = ref(props.editor.search);
const drafts = reactive<Record<string, string>>({});
const saved = ref<string | null>(null);
const saving = ref<string | null>(null);

watch(
    () => props.editor.rows,
    (rows) => {
        for (const key of Object.keys(drafts)) {
            delete drafts[key];
        }
        for (const row of rows) {
            drafts[row.key] = row.value;
        }
    },
    { immediate: true },
);

function apply(page = 1): void {
    emit('query', {
        search: search.value.trim() || undefined,
        show: props.editor.show === 'missing' ? 'missing' : undefined,
        page: page > 1 ? page : undefined,
    });
}

function setShow(show: 'all' | 'missing'): void {
    emit('query', {
        search: search.value.trim() || undefined,
        show: show === 'missing' ? 'missing' : undefined,
    });
}

function save(row: InterfaceStringRow): void {
    if ((drafts[row.key] ?? '') === row.value) {
        return;
    }

    saving.value = row.key;
    router.patch(
        updateString.url(props.language.id),
        { key: row.key, value: drafts[row.key] ?? '' },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['languages', 'editor'],
            onSuccess: () => {
                saved.value = row.key;
            },
            onFinish: () => {
                saving.value = null;
            },
        },
    );
}
</script>

<template>
    <section
        class="border-line bg-surface shadow-card grid min-w-0 gap-3 rounded-lg border p-4"
        data-test="interface-language-editor"
    >
        <header class="grid min-w-0 gap-2">
            <h2 class="font-heading text-brand-900 text-[15px] font-semibold">
                {{ $t('Edit :language by hand', { language: language.name }) }}
            </h2>
            <form
                class="flex min-w-0 flex-wrap items-center gap-2"
                @submit.prevent="apply()"
            >
                <label class="relative min-w-0 flex-1">
                    <span class="sr-only">{{ $t('Search') }}</span>
                    <Search
                        class="text-ink-faint pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2"
                        aria-hidden="true"
                    />
                    <input
                        v-model="search"
                        type="search"
                        :placeholder="$t('Search English or translation')"
                        class="border-line bg-surface text-ink focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 w-full rounded-md border ps-9 pe-3 text-[13px] focus-visible:ring-3 focus-visible:outline-none"
                    />
                </label>
                <div
                    class="border-line flex rounded-md border p-0.5"
                    role="group"
                >
                    <button
                        v-for="option in ['all', 'missing'] as const"
                        :key="option"
                        type="button"
                        :class="
                            cn(
                                'min-h-9 rounded-sm px-3 text-[12.5px] font-semibold',
                                editor.show === option
                                    ? 'bg-brand-600 text-white'
                                    : 'text-ink-slate hover:bg-brand-50',
                            )
                        "
                        @click="setShow(option)"
                    >
                        {{
                            option === 'all' ? $t('All') : $t('Not translated')
                        }}
                    </button>
                </div>
            </form>
            <p class="text-ink-slate text-[12px]">
                {{
                    $t(
                        ':count lines. Keep words that start with a colon, such as :name, exactly as they are.',
                        { count: editor.matching },
                    )
                }}
            </p>
        </header>

        <ul class="divide-line grid min-w-0 divide-y">
            <li
                v-for="row in editor.rows"
                :key="row.key"
                class="grid min-w-0 gap-1.5 py-2.5 md:grid-cols-2 md:gap-3"
            >
                <p class="text-ink text-[13px] leading-5 break-words" dir="ltr">
                    {{ row.english }}
                </p>
                <div class="flex min-w-0 items-start gap-2">
                    <textarea
                        v-model="drafts[row.key]"
                        rows="1"
                        :dir="language.dir"
                        :lang="language.code"
                        class="border-line bg-surface text-ink focus-visible:border-brand-600 focus-visible:ring-brand-600/15 field-sizing-content min-h-10 w-full min-w-0 resize-y rounded-md border px-3 py-2 text-[13px] leading-5 focus-visible:ring-3 focus-visible:outline-none"
                        @blur="save(row)"
                    />
                    <span
                        class="grid size-10 shrink-0 place-items-center"
                        aria-live="polite"
                    >
                        <Check
                            v-if="saved === row.key && saving !== row.key"
                            class="text-success size-4"
                            :aria-label="$t('Saved')"
                        />
                    </span>
                </div>
            </li>
            <li
                v-if="editor.rows.length === 0"
                class="text-ink-slate py-6 text-center text-[13px]"
            >
                {{ $t('No lines match.') }}
            </li>
        </ul>

        <footer
            v-if="editor.lastPage > 1"
            class="flex items-center justify-between gap-2"
        >
            <Button
                type="button"
                variant="outline"
                class="min-h-10"
                :disabled="editor.page <= 1"
                @click="apply(editor.page - 1)"
            >
                <ChevronLeft class="size-4 rtl:rotate-180" aria-hidden="true" />
                {{ $t('Previous') }}
            </Button>
            <span class="text-ink-slate text-[12.5px]">
                {{
                    $t('Page :page of :last', {
                        page: editor.page,
                        last: editor.lastPage,
                    })
                }}
            </span>
            <Button
                type="button"
                variant="outline"
                class="min-h-10"
                :disabled="editor.page >= editor.lastPage"
                @click="apply(editor.page + 1)"
            >
                {{ $t('Next') }}
                <ChevronRight
                    class="size-4 rtl:rotate-180"
                    aria-hidden="true"
                />
            </Button>
        </footer>
    </section>
</template>
