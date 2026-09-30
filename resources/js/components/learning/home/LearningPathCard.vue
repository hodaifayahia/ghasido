<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check, ClipboardCheck, GraduationCap, Lock } from '@lucide/vue';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import { lessons, postTest as postTestRoute } from '@/routes/learn';
import type { LearningPath } from '@/types';

/*
 * "Your learning path" on Home (client request 2026-09-30): the learner's
 * level on the Beginner → Intermediate → Advanced track, and a timeline of
 * the modules of their department and level with the lessons done in
 * each, ending with the Post-test. State is said with an icon and words,
 * never colour alone (ACC-02).
 */
type Props = {
    path: LearningPath;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const percent = computed(() =>
    props.path.modulesTotal === 0
        ? 0
        : Math.round(
              (props.path.modulesCompleted / props.path.modulesTotal) * 100,
          ),
);
</script>

<template>
    <section
        :class="
            cn(
                'border-line bg-surface shadow-card flex min-w-0 flex-col gap-4 rounded-lg border p-4 md:p-5',
                props.class,
            )
        "
        aria-labelledby="learning-path-title"
        data-test="learning-path"
    >
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2
                id="learning-path-title"
                class="font-heading text-brand-900 flex items-center gap-2 text-lg font-semibold"
            >
                <GraduationCap
                    class="text-brand-600 size-5"
                    aria-hidden="true"
                />
                {{ $t('Your learning path') }}
            </h2>
            <span
                v-if="path.department"
                class="rounded-pill bg-brand-50 text-brand-700 px-2.5 py-1 text-xs font-semibold"
            >
                {{ path.department }}
            </span>
        </div>

        <!-- The level track -->
        <div>
            <p
                class="text-ink-slate text-xs font-semibold tracking-wide uppercase"
            >
                {{ $t('My level') }}
            </p>
            <ol
                class="mt-2 grid grid-cols-3 gap-1.5"
                :aria-label="$t('English levels')"
            >
                <li
                    v-for="level in path.levels"
                    :key="level.value"
                    :aria-current="
                        level.state === 'current' ? 'step' : undefined
                    "
                    :class="
                        cn(
                            'flex min-h-11 items-center justify-center gap-1.5 rounded-md border px-2 text-center text-[13px] font-semibold',
                            level.state === 'current' &&
                                'border-brand-600 bg-brand-600 text-white',
                            level.state === 'done' &&
                                'border-success/40 bg-success-tint text-success-text',
                            level.state === 'next' &&
                                'border-line bg-app text-ink-slate',
                        )
                    "
                    :data-test="`path-level-${level.value}`"
                >
                    <Check
                        v-if="level.state === 'done'"
                        class="size-3.5 shrink-0"
                        aria-hidden="true"
                    />
                    {{ level.label }}
                    <span v-if="level.state === 'current'" class="sr-only">{{
                        $t('(your level)')
                    }}</span>
                </li>
            </ol>
        </div>

        <!-- Modules -->
        <div>
            <div class="flex items-center justify-between gap-2">
                <p
                    class="text-ink-slate text-xs font-semibold tracking-wide uppercase"
                >
                    {{ $t('Modules') }}
                </p>
                <p class="text-brand-800 text-sm font-semibold">
                    {{
                        $t(':done of :total modules completed', {
                            done: path.modulesCompleted,
                            total: path.modulesTotal,
                        })
                    }}
                </p>
            </div>
            <div
                class="bg-tint-track rounded-pill mt-2 h-2 w-full overflow-hidden"
                role="progressbar"
                :aria-valuenow="percent"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-label="$t('Modules completed')"
            >
                <div
                    class="bg-brand-600 rounded-pill h-full transition-[width] duration-700 ease-out motion-reduce:transition-none"
                    :style="{ width: `${percent}%` }"
                />
            </div>

            <ol v-if="path.modules.length" class="relative mt-4 grid gap-3">
                <li
                    v-for="(module, index) in path.modules"
                    :key="module.id"
                    class="relative flex gap-3"
                    :aria-current="
                        module.state === 'current' ? 'step' : undefined
                    "
                >
                    <span
                        v-if="index < path.modules.length"
                        aria-hidden="true"
                        class="bg-line absolute start-[13px] top-7 -bottom-3 w-0.5"
                    />
                    <span
                        :class="
                            cn(
                                'relative z-10 grid size-7 shrink-0 place-items-center rounded-full text-xs font-bold',
                                module.state === 'done' &&
                                    'bg-success text-white',
                                module.state === 'current' &&
                                    'bg-brand-600 ring-brand-100 text-white ring-4',
                                module.state === 'next' &&
                                    'border-line bg-surface text-ink-slate border-2',
                            )
                        "
                    >
                        <Check
                            v-if="module.state === 'done'"
                            class="size-4"
                            aria-hidden="true"
                        />
                        <template v-else>{{ index + 1 }}</template>
                    </span>
                    <div class="min-w-0 flex-1 pt-0.5">
                        <p
                            class="text-ink truncate text-sm font-semibold"
                            :title="module.title"
                        >
                            {{ module.title }}
                        </p>
                        <p class="text-ink-slate text-xs">
                            {{
                                module.state === 'done'
                                    ? $t('Completed')
                                    : $t(':done of :total lessons', {
                                          done: module.lessonsCompleted,
                                          total: module.lessonsTotal,
                                      })
                            }}
                            <span
                                v-if="module.state === 'current'"
                                class="text-brand-600 font-semibold"
                                >· {{ $t('In progress') }}</span
                            >
                        </p>
                    </div>
                </li>

                <li class="relative flex gap-3">
                    <span
                        :class="
                            cn(
                                'relative z-10 grid size-7 shrink-0 place-items-center rounded-full',
                                path.postTest.submitted
                                    ? 'bg-success text-white'
                                    : path.postTest.unlocked
                                      ? 'bg-warning text-white'
                                      : 'border-line bg-surface text-ink-slate border-2',
                            )
                        "
                    >
                        <Check
                            v-if="path.postTest.submitted"
                            class="size-4"
                            aria-hidden="true"
                        />
                        <ClipboardCheck
                            v-else-if="path.postTest.unlocked"
                            class="size-4"
                            aria-hidden="true"
                        />
                        <Lock v-else class="size-3.5" aria-hidden="true" />
                    </span>
                    <div class="min-w-0 flex-1 pt-0.5">
                        <p class="text-ink text-sm font-semibold">
                            {{ $t('Post-test') }}
                        </p>
                        <p class="text-ink-slate text-xs">
                            {{
                                path.postTest.submitted
                                    ? $t('Completed')
                                    : path.postTest.unlocked
                                      ? $t('Ready for you')
                                      : $t('Unlocks when every module is done')
                            }}
                        </p>
                    </div>
                    <Link
                        v-if="
                            path.postTest.unlocked && !path.postTest.submitted
                        "
                        :href="postTestRoute()"
                        class="text-brand-600 inline-flex min-h-11 items-center text-sm font-semibold hover:underline"
                    >
                        {{ $t('Start') }}
                    </Link>
                </li>
            </ol>
            <p v-else class="text-ink-slate mt-3 text-sm">
                {{ $t('No modules for your level yet.') }}
                <Link :href="lessons()" class="text-brand-600 font-semibold">
                    {{ $t('See all lessons') }}
                </Link>
            </p>
        </div>
    </section>
</template>
