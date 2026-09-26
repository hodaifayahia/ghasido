<script setup lang="ts">
import { BarChart3, ClipboardCheck, Sparkles, Users } from '@lucide/vue';
import { reactive } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import StatCard from '@/components/common/StatCard.vue';
import TestsJudgedAnswers from '@/components/tests/TestsJudgedAnswers.vue';
import type { TestResults } from '@/types';

defineProps<{
    results: TestResults;
}>();

/** Sittings whose AI review is expanded (AIE-04). */
const open = reactive(new Set<number>());

function toggle(id: number): void {
    if (open.has(id)) {
        open.delete(id);
    } else {
        open.add(id);
    }
}
</script>

<template>
    <div class="grid min-w-0 gap-3">
        <div class="grid grid-cols-2 gap-2 xl:grid-cols-4">
            <StatCard
                v-for="stat in results.stats"
                :key="stat.key"
                :value="stat.value"
                :unit="stat.unit"
                :label="stat.label"
                :detail="stat.detail"
                :tone="stat.tone === 'success' ? 'success' : 'brand'"
            >
                <template #icon>
                    <ClipboardCheck
                        v-if="stat.key === 'completed'"
                        class="size-5"
                        aria-hidden="true"
                    />
                    <BarChart3 v-else class="size-5" aria-hidden="true" />
                </template>
            </StatCard>
        </div>

        <PanelCard :title="$t('Recent Results')" title-id="test-results-title">
            <template #icon>
                <div
                    class="bg-success/20 text-success grid size-8 place-items-center rounded-md"
                >
                    <Users class="size-4" aria-hidden="true" />
                </div>
            </template>
            <div
                v-if="results.rows.length"
                class="border-line @container overflow-x-auto rounded-md border"
            >
                <table class="w-full min-w-[620px] border-collapse text-start">
                    <thead
                        class="bg-brand-50/35 text-ink-slate text-[11px] font-semibold tracking-[0.08em] uppercase"
                    >
                        <tr class="border-line border-b">
                            <th class="px-3 py-3">{{ $t('Employee') }}</th>
                            <th class="px-3 py-3">{{ $t('Test') }}</th>
                            <th class="px-3 py-3">{{ $t('Type') }}</th>
                            <th class="px-3 py-3">{{ $t('Score') }}</th>
                            <th class="px-3 py-3">{{ $t('Submitted') }}</th>
                            <th class="px-3 py-3">
                                <span class="sr-only">{{
                                    $t('AI review')
                                }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="row in results.rows" :key="row.id">
                            <tr class="border-line/80 border-b last:border-b-0">
                                <td
                                    class="text-brand-900 px-3 py-3 text-[13px] font-semibold"
                                >
                                    {{ row.employee }}
                                </td>
                                <td class="text-ink-slate px-3 py-3 text-xs">
                                    {{ row.test }}
                                </td>
                                <td class="text-ink-slate px-3 py-3 text-xs">
                                    {{ row.type }}
                                </td>
                                <td
                                    class="text-brand-700 px-3 py-3 text-xs font-semibold"
                                >
                                    {{ row.score }}
                                </td>
                                <td class="text-ink-muted px-3 py-3 text-xs">
                                    {{ row.submittedAt }}
                                </td>
                                <td class="px-3 py-3 text-end">
                                    <button
                                        v-if="row.answers?.length"
                                        type="button"
                                        class="text-brand-600 focus-visible:ring-brand-600/15 inline-flex min-h-11 items-center gap-1 rounded-sm text-xs font-semibold whitespace-nowrap hover:underline focus-visible:ring-3 focus-visible:outline-none md:min-h-8"
                                        :aria-expanded="open.has(row.id)"
                                        @click="toggle(row.id)"
                                    >
                                        <Sparkles
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        {{
                                            $t('AI review (:count)', {
                                                count: row.answers.length,
                                            })
                                        }}
                                    </button>
                                </td>
                            </tr>
                            <tr
                                v-if="row.answers?.length && open.has(row.id)"
                                class="border-line/80 bg-app/40 border-b"
                            >
                                <td colspan="6" class="px-3 py-3">
                                    <!-- Pinned to the visible width, so a
                                         phone never scrolls sideways to read
                                         the AI review (RESP-01). -->
                                    <div
                                        class="sticky start-3 w-[calc(100cqw-1.5rem)]"
                                    >
                                        <TestsJudgedAnswers
                                            :answers="row.answers"
                                        />
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <p
                v-else
                class="text-ink-slate border-line rounded-md border border-dashed px-4 py-10 text-center text-sm"
            >
                {{ $t('No completed test results yet.') }}
            </p>
        </PanelCard>
    </div>
</template>
