<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { topicTone } from '@/components/essentials-studio/essentialsStudioTones';
import EssentialsIconChip from '@/components/learning/essentials/EssentialsIconChip.vue';
import LessonsModal from '@/components/lessons/LessonsModal.vue';
import { Button } from '@/components/ui/button';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { store as storeTopic } from '@/routes/essentials-studio/topics';
import type {
    CourseTone,
    EssentialsIcon,
    EssentialsStudioIconOption,
    EssentialsStudioOption,
    EssentialsStudioTopic,
} from '@/types';

/*
 * Create or edit an English Essentials topic (client request 2026-11-05):
 * its name, one short line for learners, the icon on their list, a colour,
 * and who sees it (no department chosen = every department).
 */
type Props = {
    topic: EssentialsStudioTopic | null;
    icons: EssentialsStudioIconOption[];
    departments: EssentialsStudioOption[];
};

const props = defineProps<Props>();
const open = defineModel<boolean>('open', { required: true });

const tones: CourseTone[] = [
    'brand',
    'aqua',
    'success',
    'warning',
    'ai',
    'danger',
];

const title = ref('');
const description = ref('');
const icon = ref<EssentialsIcon>('sprout');
const tone = ref<CourseTone>('brand');
const everyone = ref(true);
const audience = ref<number[]>([]);
const errors = ref<Record<string, string>>({});
const saving = ref(false);

watch(open, (value) => {
    if (!value) {
        return;
    }

    errors.value = {};
    title.value = props.topic?.title ?? '';
    description.value = props.topic?.description ?? '';
    icon.value = props.topic?.icon ?? 'sprout';
    tone.value = props.topic?.tone ?? 'brand';
    audience.value = [...(props.topic?.audience ?? [])];
    everyone.value = audience.value.length === 0;
});

const heading = computed(() =>
    props.topic === null ? tk('New topic') : tk('Edit topic'),
);

function toggleDepartment(id: number): void {
    audience.value = audience.value.includes(id)
        ? audience.value.filter((value) => value !== id)
        : [...audience.value, id];
}

const invalidAudience = computed(
    () => !everyone.value && audience.value.length === 0,
);

function save(): void {
    if (saving.value || invalidAudience.value) {
        return;
    }

    const payload = {
        title: title.value.trim(),
        description: description.value.trim() || null,
        icon: icon.value,
        tone: tone.value,
        audience_department_ids: everyone.value ? [] : audience.value,
    };
    const options = {
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
        onError: (bag: Record<string, string>) => {
            errors.value = bag;
        },
    };

    if (props.topic === null) {
        router.post(storeTopic.url(), payload, options);
    } else {
        router.patch(props.topic.updateUrl, payload, options);
    }
}

const field =
    'border-line bg-surface text-ink placeholder:text-ink-faint focus-visible:border-brand-600 focus-visible:ring-brand-600/15 w-full rounded-sm border px-3 text-[13.5px] focus-visible:ring-3 focus-visible:outline-none';
const label = 'text-brand-900 text-[12.5px] font-semibold';
</script>

<template>
    <LessonsModal
        v-model:open="open"
        :title="$t(heading)"
        :description="
            $t(
                'Topics are the beginner basics employees open whenever they need them. They never count toward the certificate.',
            )
        "
        size="lg"
    >
        <form
            class="mt-4 grid gap-5"
            data-test="essentials-topic-form"
            @submit.prevent="save"
        >
            <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_220px]">
                <div class="grid gap-4">
                    <div class="grid gap-1.5">
                        <label for="essentials-topic-title" :class="label">
                            {{ $t('Topic name') }}
                            <span class="text-danger-text">*</span>
                        </label>
                        <input
                            id="essentials-topic-title"
                            v-model="title"
                            :class="cn(field, 'h-11')"
                            maxlength="120"
                            required
                            :placeholder="$t('e.g. Letters & Spelling')"
                            data-test="essentials-topic-title"
                        />
                        <p v-if="errors.title" class="text-danger-text text-xs">
                            {{ errors.title }}
                        </p>
                    </div>
                    <div class="grid gap-1.5">
                        <label
                            for="essentials-topic-description"
                            :class="label"
                        >
                            {{ $t('One line for employees') }}
                        </label>
                        <textarea
                            id="essentials-topic-description"
                            v-model="description"
                            rows="2"
                            maxlength="500"
                            :class="cn(field, 'resize-none py-2 leading-6')"
                            :placeholder="
                                $t(
                                    'e.g. Say the alphabet and spell a guest’s name.',
                                )
                            "
                        />
                    </div>
                </div>

                <!-- Live preview of the row employees will see. -->
                <div
                    class="border-line bg-app grid content-start gap-2 rounded-lg border p-3"
                    aria-hidden="true"
                >
                    <p
                        class="text-ink-slate text-[11px] font-semibold tracking-[0.06em] uppercase"
                    >
                        {{ $t('Employees see') }}
                    </p>
                    <div
                        class="border-line bg-surface shadow-card flex items-center gap-3 rounded-md border p-3"
                    >
                        <EssentialsIconChip
                            :icon="icon"
                            :class="topicTone[tone]"
                        />
                        <div class="min-w-0">
                            <p
                                class="text-ink-night truncate text-[13.5px] font-semibold"
                            >
                                {{ title || $t('Topic name') }}
                            </p>
                            <p
                                class="text-ink-slate line-clamp-2 text-[11.5px]"
                            >
                                {{
                                    description || $t('One line for employees')
                                }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <fieldset class="grid gap-2">
                <legend :class="label">{{ $t('Icon') }}</legend>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="option in icons"
                        :key="option.value"
                        type="button"
                        :aria-pressed="icon === option.value"
                        :class="
                            cn(
                                'focus-visible:ring-brand-600/20 flex min-h-12 items-center gap-2 rounded-md border px-2.5 pe-3 text-[12.5px] font-medium transition-colors focus-visible:ring-3 focus-visible:outline-none',
                                icon === option.value
                                    ? 'border-brand-600 bg-brand-50 text-brand-800'
                                    : 'border-line text-ink-slate hover:bg-brand-50/60',
                            )
                        "
                        :data-test="`essentials-topic-icon-${option.value}`"
                        @click="icon = option.value"
                    >
                        <EssentialsIconChip
                            :icon="option.value"
                            class="size-8 rounded-lg"
                        />
                        {{ option.label }}
                    </button>
                </div>
            </fieldset>

            <fieldset class="grid gap-2">
                <legend :class="label">{{ $t('Colour') }}</legend>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="option in tones"
                        :key="option"
                        type="button"
                        :aria-pressed="tone === option"
                        :aria-label="option"
                        :class="
                            cn(
                                'focus-visible:ring-brand-600/20 grid size-10 place-items-center rounded-full border-2 focus-visible:ring-3 focus-visible:outline-none',
                                topicTone[option],
                                tone === option
                                    ? 'border-brand-600'
                                    : 'border-transparent',
                            )
                        "
                        @click="tone = option"
                    >
                        <Check
                            v-if="tone === option"
                            class="size-4"
                            aria-hidden="true"
                        />
                    </button>
                </div>
            </fieldset>

            <fieldset class="grid gap-2">
                <legend :class="label">{{ $t('Who sees it') }}</legend>
                <div class="flex flex-wrap gap-2" role="radiogroup">
                    <button
                        v-for="choice in [true, false]"
                        :key="String(choice)"
                        type="button"
                        role="radio"
                        :aria-checked="everyone === choice"
                        :class="
                            cn(
                                'focus-visible:ring-brand-600/20 inline-flex min-h-10 items-center gap-1.5 rounded-md border px-3 text-[12.5px] font-semibold focus-visible:ring-3 focus-visible:outline-none',
                                everyone === choice
                                    ? 'border-brand-600 bg-brand-50 text-brand-700'
                                    : 'border-line text-ink-slate hover:bg-brand-50/60',
                            )
                        "
                        @click="everyone = choice"
                    >
                        <Check
                            v-if="everyone === choice"
                            class="size-3.5"
                            aria-hidden="true"
                        />
                        {{
                            choice
                                ? $t('Every department')
                                : $t('Only some departments')
                        }}
                    </button>
                </div>
                <ul
                    v-if="!everyone"
                    class="grid list-none gap-1.5 sm:grid-cols-2 md:grid-cols-3"
                >
                    <li
                        v-for="department in departments"
                        :key="department.value"
                    >
                        <label
                            :class="
                                cn(
                                    'border-line hover:bg-brand-50/60 flex min-h-10 cursor-pointer items-center gap-2 rounded-md border px-3 text-[12.5px]',
                                    audience.includes(department.value) &&
                                        'border-brand-300 bg-brand-50/60',
                                )
                            "
                        >
                            <input
                                type="checkbox"
                                class="accent-brand-600 size-4 shrink-0"
                                :checked="audience.includes(department.value)"
                                @change="toggleDepartment(department.value)"
                            />
                            <span class="text-ink min-w-0 truncate">
                                {{ department.label }}
                            </span>
                        </label>
                    </li>
                </ul>
                <p
                    v-if="invalidAudience"
                    class="text-danger-text text-xs"
                    role="alert"
                >
                    {{ $t('Choose at least one department.') }}
                </p>
            </fieldset>

            <div class="flex flex-wrap justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="border-line h-11 rounded-md px-4 md:h-10"
                    @click="open = false"
                >
                    {{ $t('Cancel') }}
                </Button>
                <Button
                    type="submit"
                    class="bg-brand-600 hover:bg-brand-700 shadow-btn h-11 rounded-md px-5 font-semibold text-white active:scale-[.97] md:h-10"
                    :disabled="saving || title.trim() === '' || invalidAudience"
                    data-test="essentials-topic-save"
                >
                    {{ topic === null ? $t('Create topic') : $t('Save topic') }}
                </Button>
            </div>
        </form>
    </LessonsModal>
</template>
