<script setup lang="ts">
import { ArrowRight, BookOpen, CirclePlus, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { TestQuestionBankItem } from '@/types';

const props = defineProps<{
    items: TestQuestionBankItem[];
}>();

const emit = defineEmits<{
    create: [];
    open: [item: TestQuestionBankItem];
}>();

const search = ref('');

const filtered = computed(() => {
    const term = search.value.trim().toLocaleLowerCase();

    return props.items.filter((item) => {
        if (term === '') return true;

        return `${item.title} ${item.prompt} ${item.kindLabel} ${item.department}`
            .toLocaleLowerCase()
            .includes(term);
    });
});
</script>

<template>
    <PanelCard
        :title="$t('Question Bank')"
        title-id="test-question-bank-title"
        class="min-w-0"
    >
        <template #icon>
            <div
                class="bg-azure/20 text-brand-600 grid size-8 place-items-center rounded-md"
            >
                <BookOpen class="size-4" aria-hidden="true" />
            </div>
        </template>
        <template #actions>
            <div class="flex items-center gap-2">
                <span
                    class="text-ink-muted hidden text-xs font-medium sm:inline"
                >
                    {{
                        $tc(':count question|:count questions', filtered.length)
                    }}
                </span>
                <Button
                    type="button"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-8 gap-1.5 rounded-md px-3 text-[11.5px] font-semibold text-white"
                    @click="emit('create')"
                >
                    <CirclePlus class="size-3.5" aria-hidden="true" />
                    {{ $t('Create Question') }}
                </Button>
            </div>
        </template>

        <div class="relative">
            <Search
                class="text-ink-muted pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2"
                aria-hidden="true"
            />
            <Input
                v-model="search"
                type="search"
                :placeholder="$t('Search the question bank...')"
                :aria-label="$t('Search the question bank')"
                class="border-line bg-surface h-10 rounded-md ps-9 text-sm"
            />
        </div>

        <div
            v-if="filtered.length"
            class="border-line mt-3 overflow-hidden rounded-md border"
        >
            <div class="divide-line divide-y">
                <article
                    v-for="item in filtered"
                    :key="item.id"
                    class="hover:bg-brand-50/35 grid gap-3 px-3 py-3 transition-colors md:grid-cols-[minmax(0,1fr)_140px_190px] md:items-center"
                >
                    <div class="min-w-0">
                        <p
                            class="text-brand-900 truncate text-[13px] font-semibold"
                        >
                            {{ item.title }}
                        </p>
                        <p class="text-ink-slate mt-1 text-xs leading-5">
                            {{ item.prompt }}
                        </p>
                    </div>
                    <div
                        class="flex flex-wrap items-center gap-1.5 text-[11px]"
                    >
                        <span
                            class="bg-brand-50 text-brand-700 rounded-full px-2 py-1 font-semibold"
                        >
                            {{ item.kindLabel }}
                        </span>
                        <span class="text-ink-muted">{{
                            item.department
                        }}</span>
                    </div>
                    <div
                        class="text-ink-muted text-start text-[11px] md:text-end"
                    >
                        <p>
                            {{
                                $tc(
                                    ':count test use|:count test uses',
                                    item.uses,
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t('Version :version', {
                                    version: item.version,
                                })
                            }}
                        </p>
                    </div>
                    <div class="flex items-center justify-start md:justify-end">
                        <Button
                            v-if="item.openUrl"
                            type="button"
                            variant="outline"
                            class="border-line text-brand-700 hover:bg-brand-50 h-8 gap-1.5 rounded-md px-2.5 text-[11.5px] font-semibold shadow-none"
                            @click="emit('open', item)"
                        >
                            {{ $t('Open Test') }}
                            <ArrowRight class="size-3.5" aria-hidden="true" />
                        </Button>
                        <span v-else class="text-ink-faint text-[11px]">
                            {{ $t('No linked test') }}
                        </span>
                    </div>
                </article>
            </div>
        </div>

        <p
            v-else
            class="text-ink-slate border-line mt-3 rounded-md border border-dashed px-4 py-10 text-center text-sm"
        >
            {{ $t('No question matches your search.') }}
        </p>
    </PanelCard>
</template>
