<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, FolderInput } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { getJson } from '@/components/lessons/lessonsHttp';
import LessonsModal from '@/components/lessons/LessonsModal.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import lessonRoutes from '@/routes/lessons';

/*
 * "Move to…" for a lesson in Course Structure (client request 2026-11-07):
 * pick any course the admin may edit, then a unit, and whether the lesson
 * goes first or last. The server checks both courses (SEC-01); the
 * lesson keeps its steps and every learner record (DATA-10).
 */
type Props = {
    lessonId: number | null;
    lessonName: string;
};

type Target = {
    id: number;
    title: string;
    group: string;
    units: { id: number; title: string; lessons: number }[];
};

const props = defineProps<Props>();
const open = defineModel<boolean>('open', { required: true });

const loading = ref(false);
const failed = ref(false);
const courses = ref<Target[]>([]);
const currentUnitId = ref<number | null>(null);
const courseId = ref<number | null>(null);
const unitId = ref<number | null>(null);
const place = ref<'end' | 'start'>('end');
const saving = ref(false);

watch(open, async (value) => {
    if (!value || props.lessonId === null) {
        return;
    }

    loading.value = true;
    failed.value = false;
    place.value = 'end';

    try {
        const data = await getJson<{
            currentUnitId: number;
            courses: Target[];
        }>(lessonRoutes.moveTargets.url(props.lessonId));
        courses.value = data.courses;
        currentUnitId.value = data.currentUnitId;
        courseId.value =
            data.courses.find((course) =>
                course.units.some((unit) => unit.id === data.currentUnitId),
            )?.id ??
            data.courses[0]?.id ??
            null;
        unitId.value = null;
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
});

const course = computed(
    () => courses.value.find((row) => row.id === courseId.value) ?? null,
);

const groups = computed(() => {
    const map = new Map<string, Target[]>();

    for (const row of courses.value) {
        map.set(row.group, [...(map.get(row.group) ?? []), row]);
    }

    return [...map.entries()];
});

function save(): void {
    if (props.lessonId === null || unitId.value === null || saving.value) {
        return;
    }

    router.patch(
        lessonRoutes.move.url(props.lessonId),
        { unit_id: unitId.value, position: place.value === 'start' ? 1 : null },
        {
            preserveScroll: true,
            onStart: () => {
                saving.value = true;
            },
            onFinish: () => {
                saving.value = false;
            },
            onSuccess: () => {
                open.value = false;
            },
        },
    );
}
</script>

<template>
    <LessonsModal
        v-model:open="open"
        :title="t('Move “:name”', { name: lessonName })"
        :description="
            t(
                'Choose the course and the unit. The lesson keeps its steps and everything employees did in it.',
            )
        "
        size="lg"
    >
        <div class="mt-4 grid gap-4" data-test="lesson-move-dialog">
            <div v-if="loading" class="grid gap-2">
                <Skeleton class="h-10 w-full rounded-md" />
                <Skeleton class="h-24 w-full rounded-md" />
            </div>
            <p
                v-else-if="failed"
                class="text-danger-text text-[13px]"
                role="alert"
            >
                {{ t('The courses could not be loaded. Please try again.') }}
            </p>

            <template v-else>
                <div class="grid gap-1.5">
                    <label
                        for="lesson-move-course"
                        class="text-brand-900 text-[12.5px] font-semibold"
                    >
                        {{ t('Course') }}
                    </label>
                    <select
                        id="lesson-move-course"
                        v-model="courseId"
                        class="border-line bg-surface text-ink focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-11 w-full rounded-sm border px-3 text-[13.5px] focus-visible:ring-3 focus-visible:outline-none"
                        data-test="lesson-move-course"
                        @change="unitId = null"
                    >
                        <optgroup
                            v-for="[group, rows] in groups"
                            :key="group"
                            :label="group"
                        >
                            <option
                                v-for="row in rows"
                                :key="row.id"
                                :value="row.id"
                            >
                                {{ row.title }}
                            </option>
                        </optgroup>
                    </select>
                </div>

                <fieldset v-if="course" class="grid gap-1.5">
                    <legend
                        class="text-brand-900 mb-1.5 text-[12.5px] font-semibold"
                    >
                        {{ t('Unit') }}
                    </legend>
                    <p
                        v-if="course.units.length === 0"
                        class="text-ink-slate text-[12.5px]"
                    >
                        {{
                            t(
                                'This course has no unit yet. Add a unit to it first.',
                            )
                        }}
                    </p>
                    <ul class="grid list-none gap-1.5" role="radiogroup">
                        <li v-for="unit in course.units" :key="unit.id">
                            <button
                                type="button"
                                role="radio"
                                :aria-checked="unitId === unit.id"
                                :class="
                                    cn(
                                        'focus-visible:ring-brand-600/15 flex min-h-11 w-full items-center gap-2 rounded-md border px-3 text-start text-[13px] focus-visible:ring-3 focus-visible:outline-none',
                                        unitId === unit.id
                                            ? 'border-brand-600 bg-brand-50 text-brand-800'
                                            : 'border-line text-ink hover:bg-brand-50/60',
                                    )
                                "
                                :data-test="`lesson-move-unit-${unit.id}`"
                                @click="unitId = unit.id"
                            >
                                <Check
                                    v-if="unitId === unit.id"
                                    class="size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                <span class="min-w-0 flex-1 truncate">
                                    {{ unit.title }}
                                </span>
                                <span
                                    v-if="unit.id === currentUnitId"
                                    class="text-ink-slate text-[11.5px]"
                                >
                                    {{ t('Current unit') }}
                                </span>
                                <span class="text-ink-faint text-[11.5px]">
                                    {{
                                        $tc(
                                            ':count lesson|:count lessons',
                                            unit.lessons,
                                        )
                                    }}
                                </span>
                            </button>
                        </li>
                    </ul>
                </fieldset>

                <fieldset v-if="unitId !== null" class="flex flex-wrap gap-2">
                    <legend class="sr-only">{{ t('Position') }}</legend>
                    <button
                        v-for="option in ['start', 'end'] as const"
                        :key="option"
                        type="button"
                        :aria-pressed="place === option"
                        :class="
                            cn(
                                'focus-visible:ring-brand-600/15 inline-flex min-h-10 items-center rounded-md border px-3 text-[12.5px] font-semibold focus-visible:ring-3 focus-visible:outline-none',
                                place === option
                                    ? 'border-brand-600 bg-brand-50 text-brand-700'
                                    : 'border-line text-ink-slate hover:bg-brand-50/60',
                            )
                        "
                        @click="place = option"
                    >
                        {{
                            option === 'start'
                                ? t('First in the unit')
                                : t('Last in the unit')
                        }}
                    </button>
                </fieldset>
            </template>

            <div class="flex justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="border-line h-11 rounded-md px-4 md:h-10"
                    @click="open = false"
                >
                    {{ t('Cancel') }}
                </Button>
                <Button
                    type="button"
                    class="bg-brand-600 hover:bg-brand-700 shadow-btn h-11 gap-1.5 rounded-md px-5 font-semibold text-white active:scale-[.97] md:h-10"
                    :disabled="unitId === null || saving"
                    data-test="lesson-move-save"
                    @click="save"
                >
                    <FolderInput class="size-4" aria-hidden="true" />
                    {{ t('Move lesson') }}
                </Button>
            </div>
        </div>
    </LessonsModal>
</template>
