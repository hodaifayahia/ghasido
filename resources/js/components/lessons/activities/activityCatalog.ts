import {
    Headphones,
    Image,
    Images,
    Keyboard,
    Link,
    ListChecks,
    ListOrdered,
    MessageCircle,
    Mic,
    PenLine,
    Video,
} from '@lucide/vue';
import type { Component } from 'vue';
import { tk } from '@/lib/i18n';
import type {
    ActivityTypeKey,
    LessonActivityItem,
    LessonActivityRow,
} from '@/types';

/**
 * The exercise types an admin can add to a Practice or Quiz block
 * (PRAC-01..03, TEST-05, WRITE-01; spec 0003 B.9), and the conversion
 * between an item's stored payload and the editor's draft.
 *
 * The payload shapes are the ones the learner runners and
 * App\Services\Learning\ActivityScorer read; the server validates them
 * again (App\Services\Content\ActivityPayloadValidator). Content stays
 * English (I18N-01): only the editor's labels are translated.
 */

export type BlockActivityMode = 'practice' | 'quiz';

export type ActivityFieldSpec = {
    key: string;
    label: string;
    /** `audio`: a sentence the learner hears, generated once (CTRL-05). `lines`: one entry per line. */
    kind: 'text' | 'textarea' | 'audio' | 'lines';
    hint?: string;
};

export type ActivityNumberSpec = {
    key: string;
    label: string;
    min: number;
    max: number;
    fallback: number;
};

export type ActivityMediaSpec = {
    key: 'image' | 'video' | 'poster';
    kind: 'image' | 'video';
    label: string;
};

export type ActivityTypeSpec = {
    value: ActivityTypeKey;
    label: string;
    description: string;
    icon: Component;
    tone: string;
    /** The instruction line a new activity starts with (English content). */
    defaultPrompt: string;
    fields: ActivityFieldSpec[];
    numbers: ActivityNumberSpec[];
    media: ActivityMediaSpec[];
    /** Answer options with one correct: the key their text is stored under. */
    options: {
        key: 'text' | 'label' | 'audio_text';
        label: string;
        image: boolean;
    } | null;
    /** Word-to-picture matching (pair map). */
    matching: boolean;
    /** Lines the learner puts in order. */
    ordering: {
        listKey: 'sentences' | 'cards';
        textKey: 'text' | 'caption';
        image: boolean;
    } | null;
};

export const activityTypes: ActivityTypeSpec[] = [
    {
        value: 'multiple_choice',
        label: tk('Multiple Choice'),
        description: tk('Read the question, then choose the best answer.'),
        icon: ListChecks,
        tone: 'brand',
        defaultPrompt: 'Choose the best answer.',
        fields: [{ key: 'question', label: tk('Question'), kind: 'textarea' }],
        numbers: [],
        media: [
            { key: 'image', kind: 'image', label: tk('Picture (optional)') },
        ],
        options: { key: 'text', label: tk('Answer'), image: false },
        matching: false,
        ordering: null,
    },
    {
        value: 'listen_choose',
        label: tk('Listen & Choose'),
        description: tk(
            'The learner hears a word or sentence and chooses the right picture.',
        ),
        icon: Headphones,
        tone: 'brand',
        defaultPrompt: 'Listen and choose the correct picture.',
        fields: [
            {
                key: 'audio_text',
                label: tk('Word or sentence the learner hears'),
                kind: 'audio',
            },
        ],
        numbers: [],
        media: [],
        options: { key: 'label', label: tk('Answer'), image: true },
        matching: false,
        ordering: null,
    },
    {
        value: 'look_listen',
        label: tk('Look & Listen'),
        description: tk(
            'The learner looks at a picture and chooses the right audio.',
        ),
        icon: Image,
        tone: 'success',
        defaultPrompt: 'Look at the picture and choose the correct audio.',
        fields: [],
        numbers: [],
        media: [{ key: 'image', kind: 'image', label: tk('Picture') }],
        options: {
            key: 'audio_text',
            label: tk('Sentence to play'),
            image: false,
        },
        matching: false,
        ordering: null,
    },
    {
        value: 'best_response',
        label: tk('Best Response'),
        description: tk(
            'The learner hears a guest and chooses the most appropriate reply.',
        ),
        icon: MessageCircle,
        tone: 'sunset',
        defaultPrompt: 'Listen to the guest and choose the best response.',
        fields: [
            { key: 'situation', label: tk('Situation'), kind: 'textarea' },
            {
                key: 'guest_audio_text',
                label: tk('What the guest says'),
                kind: 'audio',
            },
        ],
        numbers: [],
        media: [],
        options: { key: 'text', label: tk('Answer'), image: true },
        matching: false,
        ordering: null,
    },
    {
        value: 'listen_match',
        label: tk('Matching'),
        description: tk(
            'The learner hears words and matches each one with its answer.',
        ),
        icon: Link,
        tone: 'ai',
        defaultPrompt: 'Listen and match the words with the correct pictures.',
        fields: [],
        numbers: [],
        media: [],
        options: null,
        matching: true,
        ordering: null,
    },
    {
        value: 'words_sentences',
        label: tk('Fill in the Blank'),
        description: tk(
            'The learner completes a sentence with the missing word.',
        ),
        icon: Keyboard,
        tone: 'gold',
        defaultPrompt: 'Choose the word that completes the sentence.',
        fields: [
            {
                key: 'sentence',
                label: tk('Sentence with a blank'),
                kind: 'text',
                hint: tk('Write ____ (four underscores) where the word goes.'),
            },
            {
                key: 'audio_text',
                label: tk('Full sentence to play (optional)'),
                kind: 'audio',
            },
        ],
        numbers: [],
        media: [
            { key: 'image', kind: 'image', label: tk('Picture (optional)') },
        ],
        options: { key: 'label', label: tk('Answer'), image: false },
        matching: false,
        ordering: null,
    },
    {
        value: 'watch_respond',
        label: tk('Watch & Respond'),
        description: tk(
            'The learner watches a short video and chooses what to say next.',
        ),
        icon: Video,
        tone: 'blossom',
        defaultPrompt: 'Watch the video and choose the best response.',
        fields: [
            { key: 'subtitle', label: tk('Subtitle'), kind: 'text' },
            { key: 'question', label: tk('Question'), kind: 'text' },
            { key: 'hint', label: tk('Hint'), kind: 'text' },
        ],
        numbers: [],
        media: [
            { key: 'video', kind: 'video', label: tk('Video') },
            { key: 'poster', kind: 'image', label: tk('Poster image') },
        ],
        options: { key: 'text', label: tk('Answer'), image: false },
        matching: false,
        ordering: null,
    },
    {
        value: 'dialogue_order',
        label: tk('Put the Dialogue in Order'),
        description: tk(
            'The learner listens and puts the sentences in the correct order.',
        ),
        icon: ListOrdered,
        tone: 'success',
        defaultPrompt: 'Listen and put the sentences in the correct order.',
        fields: [
            {
                key: 'audio_text',
                label: tk('Whole dialogue to play (optional)'),
                kind: 'audio',
            },
        ],
        numbers: [],
        media: [],
        options: null,
        matching: false,
        ordering: { listKey: 'sentences', textKey: 'text', image: false },
    },
    {
        value: 'picture_order',
        label: tk('Ordering a Conversation'),
        description: tk(
            'The learner puts picture cards of a conversation in order.',
        ),
        icon: Images,
        tone: 'brand',
        defaultPrompt: 'Put the conversation in the correct order.',
        fields: [{ key: 'context', label: tk('Situation'), kind: 'textarea' }],
        numbers: [],
        media: [],
        options: null,
        matching: false,
        ordering: { listKey: 'cards', textKey: 'caption', image: true },
    },
    {
        value: 'speaking',
        label: tk('Speaking'),
        description: tk('The learner records a short spoken answer.'),
        icon: Mic,
        tone: 'ai',
        defaultPrompt: 'Record a short and polite response.',
        fields: [
            { key: 'question', label: tk('Question'), kind: 'text' },
            { key: 'situation', label: tk('Situation'), kind: 'textarea' },
            { key: 'instruction', label: tk('Instruction'), kind: 'text' },
        ],
        numbers: [
            {
                key: 'max_seconds',
                label: tk('Maximum recording length (seconds)'),
                min: 5,
                max: 180,
                fallback: 30,
            },
        ],
        media: [
            { key: 'image', kind: 'image', label: tk('Picture (optional)') },
        ],
        options: null,
        matching: false,
        ordering: null,
    },
    {
        value: 'writing',
        label: tk('Writing'),
        description: tk('The learner writes a short reply.'),
        icon: PenLine,
        tone: 'aqua',
        defaultPrompt: 'Write a short reply.',
        fields: [
            { key: 'scenario', label: tk('Situation'), kind: 'textarea' },
            {
                key: 'request_text',
                label: tk('Message from the guest'),
                kind: 'textarea',
            },
            {
                key: 'information',
                label: tk('Information to use (one per line)'),
                kind: 'lines',
            },
        ],
        numbers: [
            {
                key: 'min_words',
                label: tk('Minimum words'),
                min: 1,
                max: 500,
                fallback: 20,
            },
        ],
        media: [],
        options: null,
        matching: false,
        ordering: null,
    },
];

export function activityTypeSpec(type: string): ActivityTypeSpec {
    return (
        activityTypes.find((spec) => spec.value === type) ?? activityTypes[0]
    );
}

// ------------------------------------------------------------------ drafts

export type OptionDraft = { id: string; text: string; image: number | null };

export type PairDraft = {
    prompt: string;
    target: string;
    image: number | null;
};

/** One item as the editor holds it; `extra` keeps keys the editor does not show. */
export type ItemDraft = {
    id: string;
    fields: Record<string, string>;
    numbers: Record<string, number | null>;
    media: Record<string, number | null>;
    options: OptionDraft[];
    correct: string | null;
    pairs: PairDraft[];
    distractors: OptionDraft[];
    lines: OptionDraft[];
    extra: Record<string, unknown>;
};

const LETTERS = 'abcdefghijklmnopqrstuvwxyz';

function str(value: unknown): string {
    return typeof value === 'string'
        ? value
        : typeof value === 'number'
          ? String(value)
          : '';
}

function num(value: unknown): number | null {
    return typeof value === 'number' && value > 0 ? value : null;
}

function records(value: unknown): Record<string, unknown>[] {
    return Array.isArray(value)
        ? value.filter(
              (entry): entry is Record<string, unknown> =>
                  typeof entry === 'object' && entry !== null,
          )
        : [];
}

/**
 * The learner sees lines and targets in stored order, so the stored order
 * must never be the answer: odd positions first, then even (never the
 * identity for two or more entries).
 */
export function scramble<T>(list: T[]): T[] {
    return [
        ...list.filter((_, index) => index % 2 === 1),
        ...list.filter((_, index) => index % 2 === 0),
    ];
}

function handledKeys(spec: ActivityTypeSpec): string[] {
    return [
        'id',
        ...spec.fields.map((field) => field.key),
        ...spec.numbers.map((field) => field.key),
        ...spec.media.map((field) => field.key),
        'options',
        'correct',
        'prompts',
        'targets',
        'pairs',
        'sentences',
        'cards',
        'order',
    ];
}

export function blankDraft(spec: ActivityTypeSpec, id: string): ItemDraft {
    const option = (index: number): OptionDraft => ({
        id: LETTERS[index] ?? `o${index + 1}`,
        text: '',
        image: null,
    });

    return {
        id,
        fields: Object.fromEntries(spec.fields.map((field) => [field.key, ''])),
        numbers: Object.fromEntries(
            spec.numbers.map((field) => [field.key, field.fallback]),
        ),
        media: Object.fromEntries(spec.media.map((field) => [field.key, null])),
        options: spec.options === null ? [] : [0, 1, 2].map(option),
        correct: null,
        pairs: spec.matching
            ? [0, 1, 2].map(() => ({ prompt: '', target: '', image: null }))
            : [],
        distractors: [],
        lines:
            spec.ordering === null
                ? []
                : [0, 1, 2].map(() => ({ id: '', text: '', image: null })),
        extra: spec.value === 'multiple_choice' ? { layout: 'side' } : {},
    };
}

export function draftFromItem(
    spec: ActivityTypeSpec,
    item: LessonActivityItem,
): ItemDraft {
    const draft = blankDraft(spec, str(item.id) || 'i1');
    const handled = handledKeys(spec);

    draft.extra = Object.fromEntries(
        Object.entries(item).filter(([key]) => !handled.includes(key)),
    );

    for (const field of spec.fields) {
        const value = item[field.key];
        draft.fields[field.key] =
            field.kind === 'lines' && Array.isArray(value)
                ? value.map(str).join('\n')
                : str(value);
    }

    for (const field of spec.numbers) {
        draft.numbers[field.key] = num(item[field.key]) ?? field.fallback;
    }

    for (const field of spec.media) {
        draft.media[field.key] = num(item[field.key]);
    }

    if (spec.options !== null) {
        const key = spec.options.key;
        draft.options = records(item.options).map((option) => ({
            id: str(option.id),
            text: str(option[key]),
            image: num(option.image),
        }));
        draft.correct = str(item.correct) || null;
    }

    if (spec.matching) {
        const targets = records(item.targets);
        const pairs =
            typeof item.pairs === 'object' && item.pairs !== null
                ? (item.pairs as Record<string, unknown>)
                : {};
        const used = new Set<string>();

        draft.pairs = records(item.prompts).map((prompt) => {
            const targetId = str(pairs[str(prompt.id)]);
            const target = targets.find((entry) => str(entry.id) === targetId);
            used.add(targetId);

            return {
                prompt: str(prompt.audio_text),
                target: str(target?.label),
                image: num(target?.image),
            };
        });
        draft.distractors = targets
            .filter((target) => !used.has(str(target.id)))
            .map((target) => ({
                id: str(target.id),
                text: str(target.label),
                image: num(target.image),
            }));
    }

    if (spec.ordering !== null) {
        const { listKey, textKey } = spec.ordering;
        const entries = records(item[listKey]);
        const order = Array.isArray(item.order) ? item.order.map(str) : [];
        const byId = new Map(entries.map((entry) => [str(entry.id), entry]));
        const ordered = [
            ...order
                .map((id) => byId.get(id))
                .filter(
                    (entry): entry is Record<string, unknown> =>
                        entry !== undefined,
                ),
            ...entries.filter((entry) => !order.includes(str(entry.id))),
        ];

        draft.lines = ordered.map((entry) => ({
            id: str(entry.id),
            text: str(entry[textKey]),
            image: num(entry.image),
        }));
    }

    return draft;
}

export function itemFromDraft(
    spec: ActivityTypeSpec,
    draft: ItemDraft,
): LessonActivityItem {
    const item: LessonActivityItem = { ...draft.extra, id: draft.id };

    for (const field of spec.fields) {
        const value = draft.fields[field.key] ?? '';
        item[field.key] =
            field.kind === 'lines'
                ? value
                      .split('\n')
                      .map((line) => line.trim())
                      .filter((line) => line !== '')
                : value.trim() === ''
                  ? null
                  : value.trim();
    }

    for (const field of spec.numbers) {
        item[field.key] = draft.numbers[field.key] ?? field.fallback;
    }

    for (const field of spec.media) {
        item[field.key] = draft.media[field.key] ?? null;
    }

    if (spec.options !== null) {
        const { key, image } = spec.options;
        item.options = draft.options.map((option) => ({
            id: option.id,
            [key]: option.text.trim(),
            ...(image ? { image: option.image } : {}),
        }));
        item.correct = draft.correct;
    }

    if (spec.matching) {
        const rows = draft.pairs.filter(
            (pair) => pair.prompt.trim() !== '' || pair.target.trim() !== '',
        );
        const targets = [
            ...rows.map((pair, index) => ({
                id: LETTERS[index] ?? `t${index + 1}`,
                label: pair.target.trim(),
                image: pair.image,
            })),
            ...draft.distractors.map((option, index) => ({
                id:
                    LETTERS[rows.length + index] ??
                    `t${rows.length + index + 1}`,
                label: option.text.trim(),
                image: option.image,
            })),
        ];

        item.prompts = rows.map((pair, index) => ({
            id: String(index + 1),
            audio_text: pair.prompt.trim(),
        }));
        item.targets = scramble(targets);
        item.pairs = Object.fromEntries(
            rows.map((_, index) => [
                String(index + 1),
                LETTERS[index] ?? `t${index + 1}`,
            ]),
        );
    }

    if (spec.ordering !== null) {
        const { listKey, textKey, image } = spec.ordering;
        const entries = draft.lines
            .filter((line) => line.text.trim() !== '' || line.image !== null)
            .map((line, index) => ({
                id:
                    listKey === 'cards'
                        ? (LETTERS[index]?.toUpperCase() ?? `C${index + 1}`)
                        : `s${index + 1}`,
                [textKey]: line.text.trim(),
                ...(image ? { image: line.image } : {}),
            }));

        item[listKey] = scramble(entries);
        item.order = entries.map((entry) => entry.id);
    }

    return item;
}

/** The next free option id in a list. */
export function nextOptionId(options: OptionDraft[]): string {
    const used = options.map((option) => option.id);

    return (
        LETTERS.split('').find((letter) => !used.includes(letter)) ??
        `o${options.length + 1}`
    );
}

/** The next free item id (`i1`, `i2`, …). */
export function nextItemId(items: ItemDraft[]): string {
    let n = items.length + 1;

    while (items.some((item) => item.id === `i${n}`)) {
        n += 1;
    }

    return `i${n}`;
}

// ---------------------------------------------------------------- summaries

/** The line a list row shows for an activity: its first question-ish text. */
export function activitySummary(activity: LessonActivityRow): string {
    const item = activity.payload.items[0];

    if (item !== undefined) {
        for (const key of [
            'question',
            'sentence',
            'situation',
            'context',
            'scenario',
            'audio_text',
            'guest_audio_text',
            'subtitle',
        ]) {
            const value = str(item[key]);

            if (value.trim() !== '') {
                return value;
            }
        }
    }

    return activity.prompt;
}

/** The correct answer's text of the first item, when the type has one. */
export function correctAnswer(activity: LessonActivityRow): string | null {
    const spec = activityTypeSpec(activity.type);
    const item = activity.payload.items[0];

    if (spec.options === null || item === undefined) {
        return null;
    }

    const option = records(item.options).find(
        (entry) => str(entry.id) === str(item.correct),
    );

    return option === undefined ? null : str(option[spec.options.key]) || null;
}
