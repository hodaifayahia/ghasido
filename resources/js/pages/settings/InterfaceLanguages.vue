<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onUnmounted, ref } from 'vue';
import ConfirmRemoveDialog from '@/components/common/ConfirmRemoveDialog.vue';
import Heading from '@/components/Heading.vue';
import InterfaceLanguageAddForm from '@/components/interface-languages/InterfaceLanguageAddForm.vue';
import InterfaceLanguageCard from '@/components/interface-languages/InterfaceLanguageCard.vue';
import InterfaceLanguageEditor from '@/components/interface-languages/InterfaceLanguageEditor.vue';
import { tk } from '@/lib/i18n';
import {
    destroy,
    generate,
    importMethod,
    index,
    update,
} from '@/routes/interface-languages';
import type {
    InterfaceLanguageEditor as Editor,
    InterfaceLanguageRow,
} from '@/types';

/*
 * Settings → Interface languages (client request 2026-10-03): the top
 * language menu offers English and Arabic; here the Super Admin adds any
 * other language, translates it with AI or by hand, and switches it on.
 */
type Props = {
    languages: InterfaceLanguageRow[];
    total: number;
    selected: string | null;
    editor: Editor | null;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: tk('Interface languages'), href: index() }],
    },
});

const selectedLanguage = computed(
    () => props.languages.find((item) => item.code === props.selected) ?? null,
);

function visit(query: Record<string, string | number | undefined>): void {
    const clean = Object.fromEntries(
        Object.entries(query).filter(([, value]) => value !== undefined),
    ) as Record<string, string | number>;

    router.get(
        index.url({ query: clean }),
        {},
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

function edit(language: InterfaceLanguageRow): void {
    visit({ language: language.code });
}

function onQuery(query: { search?: string; show?: string; page?: number }) {
    visit({ language: props.selected ?? undefined, ...query });
}

function toggle(language: InterfaceLanguageRow, enabled: boolean): void {
    router.patch(
        update.url(language.id),
        { enabled },
        { preserveScroll: true, preserveState: true },
    );
}

// ------------------------------------------------------------- AI loop
//
// Each call translates for about twenty seconds; the page calls again
// until nothing is left, so no background worker is needed.

const generatingId = ref<number | null>(null);
let stopped = false;

function generateStep(language: InterfaceLanguageRow): void {
    router.post(
        generate.url(language.id),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: ['languages', 'editor'],
            onSuccess: () => {
                const fresh = props.languages.find(
                    (item) => item.id === language.id,
                );

                if (
                    !stopped &&
                    fresh !== undefined &&
                    fresh.status === 'generating' &&
                    fresh.translated < props.total
                ) {
                    generateStep(fresh);
                } else {
                    generatingId.value = null;
                }
            },
            onError: () => {
                generatingId.value = null;
            },
            onCancel: () => {
                generatingId.value = null;
            },
        },
    );
}

function startGenerate(language: InterfaceLanguageRow): void {
    stopped = false;
    generatingId.value = language.id;
    generateStep(language);
}

function stopGenerate(): void {
    stopped = true;
}

onUnmounted(() => {
    stopped = true;
});

function importFile(language: InterfaceLanguageRow, file: File): void {
    router.post(
        importMethod.url(language.id),
        { file },
        { preserveScroll: true, preserveState: true, forceFormData: true },
    );
}

const removing = ref<InterfaceLanguageRow | null>(null);
const removeOpen = ref(false);
const busy = ref(false);

function askRemove(language: InterfaceLanguageRow): void {
    removing.value = language;
    removeOpen.value = true;
}

function remove(): void {
    if (removing.value === null) {
        return;
    }

    busy.value = true;
    router.delete(destroy.url(removing.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            removeOpen.value = false;
        },
        onFinish: () => {
            busy.value = false;
        },
    });
}
</script>

<template>
    <Head :title="$t('Interface languages')" />

    <h1 class="sr-only">{{ $t('Interface languages') }}</h1>

    <div class="flex min-w-0 flex-col space-y-5">
        <Heading
            variant="small"
            :title="$t('Interface languages')"
            :description="
                $t(
                    'The languages of the top language menu. English and Arabic are built in; add any other, translate it with AI or by hand, then switch it on.',
                )
            "
        />

        <InterfaceLanguageAddForm
            :existing="languages.map((language) => language.code)"
        />

        <InterfaceLanguageCard
            v-for="language in languages"
            :key="language.id"
            :language="language"
            :total="total"
            :generating="generatingId === language.id"
            :editing="selected === language.code"
            @toggle="(enabled) => toggle(language, enabled)"
            @generate="startGenerate(language)"
            @stop="stopGenerate"
            @edit="edit(language)"
            @import="(file) => importFile(language, file)"
            @remove="askRemove(language)"
        />

        <p
            v-if="languages.length === 0"
            class="text-ink-slate border-line rounded-lg border border-dashed p-6 text-center text-[13px]"
        >
            {{ $t('No added languages yet.') }}
        </p>

        <InterfaceLanguageEditor
            v-if="selectedLanguage && editor"
            :language="selectedLanguage"
            :editor="editor"
            @query="onQuery"
        />
    </div>

    <ConfirmRemoveDialog
        v-if="removing"
        v-model:open="removeOpen"
        :title="$t('Delete :name?', { name: removing.name })"
        :description="
            $t(
                'Its translations are deleted, and anyone using it goes back to English.',
            )
        "
        :confirm-text="removing.code"
        :busy="busy"
        @confirm="remove"
    />
</template>
