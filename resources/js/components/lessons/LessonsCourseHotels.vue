<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Building2, Check } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { LessonCourseHotels, LessonCourseHotelScope } from '@/types';

/*
 * Which hotels see a shared course (client request 2026-11-05): every
 * hotel, only a chosen group, or every hotel except a chosen group, instead
 * of only "all hotels" or "one hotel". Applies to the whole course, like
 * its department and level; the server scopes every learner query by it
 * (Course::openToHotel, ROLE-02).
 */
type Props = {
    hotels: LessonCourseHotels;
    readonly?: boolean;
};

const props = withDefaults(defineProps<Props>(), { readonly: false });

const scope = ref<LessonCourseHotelScope>(props.hotels.scope);
const selected = ref<number[]>([...props.hotels.hotelIds]);
const saving = ref(false);

watch(
    () => props.hotels,
    (hotels) => {
        scope.value = hotels.scope;
        selected.value = [...hotels.hotelIds];
    },
);

const choices: { value: LessonCourseHotelScope; label: string }[] = [
    { value: 'all', label: tk('All hotels') },
    { value: 'only', label: tk('Only these hotels') },
    { value: 'except', label: tk('All hotels except these') },
];

const dirty = computed(
    () =>
        scope.value !== props.hotels.scope ||
        [...selected.value].sort().join(',') !==
            [...props.hotels.hotelIds].sort().join(','),
);

const invalid = computed(
    () => scope.value !== 'all' && selected.value.length === 0,
);

function toggle(id: number): void {
    if (props.readonly) {
        return;
    }

    selected.value = selected.value.includes(id)
        ? selected.value.filter((value) => value !== id)
        : [...selected.value, id];
}

function save(): void {
    if (props.readonly || invalid.value || saving.value) {
        return;
    }

    router.patch(
        props.hotels.url,
        {
            hotel_scope: scope.value,
            hotel_ids: scope.value === 'all' ? [] : selected.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                saving.value = true;
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}
</script>

<template>
    <section
        class="border-line bg-surface grid gap-3 rounded-md border px-4 py-3"
        aria-labelledby="course-hotels-title"
        data-test="course-hotels"
    >
        <div class="flex items-start gap-2">
            <Building2
                class="text-brand-700 mt-0.5 size-4 shrink-0"
                aria-hidden="true"
            />
            <div class="min-w-0">
                <h3
                    id="course-hotels-title"
                    class="font-heading text-brand-900 text-[13.5px] font-semibold"
                >
                    {{ $t('Which hotels see this course') }}
                </h3>
                <p class="text-ink-slate text-[12px]">
                    {{
                        $t(
                            'Applies to every unit and lesson of the course. Choose a group of hotels to include or to leave out.',
                        )
                    }}
                </p>
            </div>
        </div>

        <div
            class="flex flex-wrap gap-2"
            role="radiogroup"
            :aria-label="$t('Which hotels see this course')"
        >
            <button
                v-for="choice in choices"
                :key="choice.value"
                type="button"
                role="radio"
                :aria-checked="scope === choice.value"
                :disabled="readonly"
                :class="
                    cn(
                        'focus-visible:ring-brand-600/15 inline-flex min-h-9 items-center gap-1.5 rounded-md border px-3 text-[12.5px] font-semibold transition-colors focus-visible:ring-3 focus-visible:outline-none disabled:opacity-60',
                        scope === choice.value
                            ? 'border-brand-600 bg-brand-50 text-brand-700'
                            : 'border-line text-ink-slate hover:bg-brand-50/60',
                    )
                "
                :data-test="`course-hotels-scope-${choice.value}`"
                @click="scope = choice.value"
            >
                <Check
                    v-if="scope === choice.value"
                    class="size-3.5"
                    aria-hidden="true"
                />
                {{ $t(choice.label) }}
            </button>
        </div>

        <div v-if="scope !== 'all'" class="grid gap-1.5">
            <p class="text-ink-slate text-[12px] font-medium">
                {{
                    scope === 'only'
                        ? $t('Hotels that see it')
                        : $t('Hotels that do not see it')
                }}
            </p>
            <ul class="grid list-none gap-1.5 sm:grid-cols-2">
                <li v-for="hotel in hotels.options" :key="hotel.id">
                    <label
                        :class="
                            cn(
                                'border-line hover:bg-brand-50/60 flex min-h-10 cursor-pointer items-center gap-2 rounded-md border px-3 text-[12.5px]',
                                selected.includes(hotel.id) &&
                                    'border-brand-300 bg-brand-50/60',
                                readonly && 'pointer-events-none opacity-60',
                            )
                        "
                    >
                        <input
                            type="checkbox"
                            class="accent-brand-600 size-4 shrink-0"
                            :checked="selected.includes(hotel.id)"
                            :disabled="readonly"
                            :data-test="`course-hotels-hotel-${hotel.id}`"
                            @change="toggle(hotel.id)"
                        />
                        <span class="text-ink min-w-0 truncate">
                            {{ hotel.name }}
                        </span>
                    </label>
                </li>
            </ul>
            <p v-if="invalid" class="text-danger-text text-[12px]" role="alert">
                {{ $t('Choose at least one hotel.') }}
            </p>
        </div>

        <div v-if="!readonly" class="flex justify-end">
            <Button
                type="button"
                class="bg-brand-600 hover:bg-brand-700 shadow-btn h-9 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97]"
                :disabled="!dirty || invalid || saving"
                data-test="course-hotels-save"
                @click="save"
            >
                {{ $t('Save hotels') }}
            </Button>
        </div>
    </section>
</template>
