<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Eye, EyeOff, Plus } from '@lucide/vue';
import { topicTone } from '@/components/essentials-studio/essentialsStudioTones';
import EssentialsIconChip from '@/components/learning/essentials/EssentialsIconChip.vue';
import { cn } from '@/lib/utils';
import { essentialsStudio } from '@/routes';
import type { EssentialsStudioTopic } from '@/types';

/*
 * The English Essentials topics, in the order employees see them (client
 * request 2026-11-05). Each card says at a glance whether it is visible,
 * how many of its lessons are published and how many employees used it;
 * the open one is highlighted. Order changes with the arrow buttons.
 */
type Props = {
    topics: EssentialsStudioTopic[];
    openId: number | null;
    manage: boolean;
    busy?: boolean;
};

withDefaults(defineProps<Props>(), { busy: false });

const emit = defineEmits<{
    create: [];
    move: [index: number, direction: -1 | 1];
}>();

const arrow =
    'text-ink-slate hover:bg-brand-50 hover:text-brand-800 focus-visible:ring-brand-600/20 grid size-9 place-items-center rounded-md focus-visible:ring-3 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-30 md:size-7';
</script>

<template>
    <nav
        class="border-line bg-surface shadow-card grid content-start gap-3 rounded-lg border p-3"
        :aria-label="$t('English Essentials topics')"
    >
        <div class="flex items-center justify-between gap-2 px-1">
            <h2 class="font-heading text-brand-900 text-[15px] font-semibold">
                {{ $t('Topics') }}
            </h2>
            <button
                v-if="manage"
                type="button"
                class="bg-brand-600 hover:bg-brand-700 shadow-btn focus-visible:ring-brand-600/30 inline-flex h-10 items-center gap-1.5 rounded-md px-3 text-[12.5px] font-semibold text-white focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] md:h-8"
                data-test="essentials-new-topic"
                @click="emit('create')"
            >
                <Plus class="size-4" aria-hidden="true" />
                {{ $t('New topic') }}
            </button>
        </div>

        <p
            v-if="topics.length === 0"
            class="border-line-strong bg-app text-ink-slate rounded-md border border-dashed px-3 py-5 text-center text-[12.5px] leading-5"
        >
            {{
                $t(
                    'No topic yet. Create the first one, for example Letters & Spelling.',
                )
            }}
        </p>

        <ol v-else class="grid list-none gap-2">
            <li
                v-for="(topic, index) in topics"
                :key="topic.id"
                class="relative"
            >
                <Link
                    :href="essentialsStudio({ query: { topic: topic.id } })"
                    preserve-scroll
                    :aria-current="topic.id === openId ? 'page' : undefined"
                    :class="
                        cn(
                            'focus-visible:ring-brand-600/30 flex min-h-16 items-center gap-3 rounded-md border p-2.5 pe-12 text-start transition-colors duration-150 focus-visible:ring-3 focus-visible:outline-none motion-reduce:transition-none',
                            topic.id === openId
                                ? 'border-brand-300 bg-brand-50/70'
                                : 'hover:bg-app border-transparent',
                        )
                    "
                    :data-test="`essentials-topic-${topic.id}`"
                >
                    <EssentialsIconChip
                        :icon="topic.icon"
                        :class="cn('size-10', topicTone[topic.tone])"
                    />
                    <span class="min-w-0 flex-1">
                        <span
                            class="text-ink-night block truncate text-[13.5px] font-semibold"
                        >
                            {{ topic.title }}
                        </span>
                        <span
                            class="text-ink-slate mt-0.5 flex items-center gap-1.5 text-[11.5px]"
                        >
                            <component
                                :is="topic.visible ? Eye : EyeOff"
                                :class="
                                    cn(
                                        'size-3 shrink-0',
                                        topic.visible
                                            ? 'text-success'
                                            : 'text-warning-text',
                                    )
                                "
                                aria-hidden="true"
                            />
                            <span class="truncate">
                                {{
                                    topic.visible ? $t('Visible') : $t('Hidden')
                                }}
                                ·
                                {{
                                    $t(':published / :total published', {
                                        published: topic.lessonsPublished,
                                        total: topic.lessonsTotal,
                                    })
                                }}
                            </span>
                        </span>
                    </span>
                </Link>
                <span
                    v-if="manage && topics.length > 1"
                    class="absolute inset-y-0 end-1 flex flex-col justify-center"
                >
                    <button
                        type="button"
                        :class="arrow"
                        :disabled="index === 0 || busy"
                        :aria-label="$t('Move :name up', { name: topic.title })"
                        @click="emit('move', index, -1)"
                    >
                        <ArrowUp class="size-3.5" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        :class="arrow"
                        :disabled="index === topics.length - 1 || busy"
                        :aria-label="
                            $t('Move :name down', { name: topic.title })
                        "
                        @click="emit('move', index, 1)"
                    >
                        <ArrowDown class="size-3.5" aria-hidden="true" />
                    </button>
                </span>
            </li>
        </ol>
    </nav>
</template>
