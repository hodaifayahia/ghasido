# 14 — Laravel + Vue Setup

## 14.1 Install

```bash
composer create-project laravel/laravel guesvia
cd guesvia
composer require inertiajs/inertia-laravel laravel/sanctum
npm i @inertiajs/vue3 vue @vitejs/plugin-vue
npm i -D tailwindcss postcss autoprefixer @tailwindcss/forms @tailwindcss/typography
npm i lucide-vue-next vuedraggable@next chart.js vue-chartjs
npx tailwindcss init -p
```

Then copy `tailwind.config.js` and `tokens.css` from this folder into the project root and `resources/css/`.

`resources/css/app.css`:

```css
@import './tokens.css';
@tailwind base;
@tailwind components;
@tailwind utilities;
```

Other packages worth adding: `spatie/laravel-permission` (the three roles), `maatwebsite/excel` (the Excel/CSV exports), `barryvdh/laravel-dompdf` (certificates), `intervention/image` (upload resizing), `laravel/horizon` (queues for AI calls, TTS generation and reminder emails).

---

## 14.2 Front-end structure

```
resources/js/
├─ app.js
├─ Layouts/
│  ├─ AdminLayout.vue        sidebar + topbar + content
│  ├─ ManagerLayout.vue      same shell, manager nav
│  ├─ EmployeeLayout.vue     desktop sidebar → mobile bottom tabs
│  └─ AuthLayout.vue         centred card on the gradient background
├─ Components/
│  ├─ shell/       AppSidebar  AppTopbar  BottomNav  PageHeader  ScriptAccent  HeaderPhoto
│  ├─ ui/          BaseButton  BaseInput  BaseSelect  BaseToggle  StatusPill  IconChip
│  │               PanelCard  StatCard  Modal  BottomSheet  Tabs  Pagination  EmptyState
│  │               SkeletonCard  Tooltip
│  ├─ data/        DataTable  ProgressBar  Donut  BarChart  ActivityFeed  FilterBar
│  ├─ learning/    StepTracker  VocabCard  ExpressionCard  DialogueLine  AudioButton
│  │               ShowMeaning  PhrasebookButton  LessonCompleteCard
│  ├─ test/        TestQuestionCard  TestSidebar  QuestionNavigator  TimerCard
│  │               OptionList  SpeakingRecorder  OrderingActivity  WritingActivity
│  │               TestIntroCard  TestResultCard
│  ├─ roleplay/    ScenarioCard  GetReadyPanel  ChatWindow  FeedbackPanel  AttemptBadge
│  └─ builder/     BlockPalette  BlockCanvas  BlockWrapper  LayoutSwitcher  ImageLibrary
│                  MediaSlot  AiGenerateButton  AudioGenerator
├─ Pages/
│  ├─ Auth/        Login  FirstLogin
│  ├─ Admin/       Dashboard  Hotels  Departments  Employees  Lessons  Scenarios
│  │               Tests  Reminders  Reports  Settings
│  ├─ Manager/     Dashboard  Team  Progress  TestResults  Reminders
│  └─ Employee/    Home  Lessons  LessonShow  Phrasebook  Progress
│                  PreTest  PostTest  TestResult  RolePlay  Certificate
└─ composables/    useProgress  useAudio  useShowMeaning  useTimer  useRecorder  useAi
```

**Naming:** the component names above match `11-components.md` one-to-one, so a spec line maps to exactly one file.

---

## 14.3 Example: StatCard.vue

```vue
<script setup>
const props = defineProps({
    value: { type: [String, Number], required: true },
    label: { type: String, required: true },
    sub: { type: String, default: null },
    tone: { type: String, default: 'brand' }, // brand|success|warning|danger|ai|teal
});

const tones = {
    brand: ['bg-brand-50', 'text-brand-600'],
    success: ['bg-success-tint', 'text-success'],
    warning: ['bg-warning-tint', 'text-warning'],
    danger: ['bg-danger-tint', 'text-danger'],
    ai: ['bg-ai-tint', 'text-ai'],
    teal: ['bg-teal-tint', 'text-teal'],
};
</script>

<template>
    <div
        class="flex items-center gap-4 rounded-lg border border-line bg-white p-5 shadow-card
              transition duration-150 ease-out hover:-translate-y-0.5 hover:shadow-hover"
    >
        <div
            :class="[
                'grid h-11 w-11 place-items-center rounded-xl',
                tones[tone][0],
                tones[tone][1],
            ]"
        >
            <slot name="icon" />
        </div>
        <div class="min-w-0">
            <div class="font-heading text-stat text-ink">{{ value }}</div>
            <div class="truncate text-[13px] text-ink-muted">{{ label }}</div>
            <div v-if="sub" :class="['text-xs font-semibold', tones[tone][1]]">
                {{ sub }}
            </div>
        </div>
    </div>
</template>
```

## 14.4 Example: StatusPill.vue

```vue
<script setup>
const props = defineProps({ status: String });
const map = {
    active: ['Active', 'bg-success-tint text-success-text', 'bg-success'],
    completed: ['Completed', 'bg-success-tint text-success-text', 'bg-success'],
    published: ['Published', 'bg-success-tint text-success-text', 'bg-success'],
    in_progress: [
        'In progress',
        'bg-warning-tint text-warning-text',
        'bg-warning',
    ],
    draft: ['Draft', 'bg-warning-tint text-warning-text', 'bg-warning'],
    not_started: [
        'Not started',
        'bg-danger-tint text-danger-text',
        'bg-danger',
    ],
    inactive: ['Inactive', 'bg-danger-tint text-danger-text', 'bg-danger'],
};
</script>

<template>
    <span
        :class="[
            'inline-flex h-6 items-center gap-1.5 rounded-pill px-2.5 text-xs font-semibold',
            map[status]?.[1],
        ]"
    >
        <span :class="['h-1.5 w-1.5 rounded-full', map[status]?.[2]]" />
        {{ map[status]?.[0] ?? status }}
    </span>
</template>
```

## 14.5 Example: ShowMeaning.vue

The single most-used component in the learner UI — English stays, Arabic is revealed.

```vue
<script setup>
import { ref } from 'vue';
import { Languages } from 'lucide-vue-next';
defineProps({ arabic: String, explanation: String, example: String });
const open = ref(false);
</script>

<template>
    <div>
        <button
            class="inline-flex h-9 items-center gap-1.5 rounded-pill bg-brand-50 px-3
             text-[13px] font-semibold text-brand-600 transition hover:bg-brand-100"
            :aria-expanded="open"
            @click="open = !open"
        >
            <Languages :size="16" />
            {{ open ? 'Hide Meaning' : 'Show Meaning' }}
        </button>

        <Transition
            enter-active-class="transition-all duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            leave-active-class="transition-all duration-150 ease-out"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div v-if="open" class="mt-2 rounded-md bg-brand-50 p-4">
                <p lang="ar" dir="rtl" class="font-arabic text-base text-ink">
                    {{ arabic }}
                </p>
                <p v-if="explanation" class="mt-2 text-[13px] text-ink-muted">
                    {{ explanation }}
                </p>
                <p
                    v-if="example"
                    class="mt-2 border-l-2 border-brand-300 pl-3 text-[13px] italic text-ink"
                >
                    {{ example }}
                </p>
            </div>
        </Transition>
    </div>
</template>
```

---

## 14.6 Things to get right early

1. **Progress persistence is server-side.** Every step completion and answer POSTs immediately; the client never holds the source of truth. Browser storage is only a short-lived buffer for offline recovery.
2. **Authorisation by policy, not by UI.** `LessonPolicy`, `TestPolicy`, `ScenarioPolicy` all check `user.hotel_id` and `user.department_id`. An employee hitting `/lessons/999` directly must get a 403.
3. **Queue the slow work.** AI generation, TTS, evaluation and reminder emails go through jobs, with the UI showing an in-progress state and polling or using Echo.
4. **Cache the audio.** TTS output is stored once on the media disk and served as a static file — never regenerated on play.
5. **Build the shell first.** `AdminLayout`, `EmployeeLayout`, `BaseButton`, `PanelCard`, `StatCard`, `DataTable`, `StatusPill`. Every screen after that becomes assembly rather than styling, which is what keeps all 20+ screens visually identical.
6. **Check 390px width on every employee screen** before calling it done.
