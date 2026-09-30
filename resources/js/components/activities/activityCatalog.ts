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
    TextCursorInput,
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
 * The question / exercise types of the shared activity editor, used by the
 * Pre/Post-test builder and by the lesson Practice and Quiz blocks alike
 * (PRAC-01..07, PRAC-05, TEST-05, WRITE-01..05; client report 2026-09-29),
 * and the conversion between an item's stored payload and the editor's
 * draft.
 *
 * The builders offer exactly the client's ten types (`builderTypes`). The
 * eight older types stay here, flagged `legacy`, only so the activities
 * already stored with them open and save unchanged (DATA-11).
 *
 * Payload shapes are the ones the learner pages and
 * App\Services\Learning\ActivityScorer read, and the server validates them
 * again (App\Services\Content\ActivityPayloadValidator). Content stays
 * English (I18N-01): only the editor's labels are translated.
 */

export type BlockActivityMode = 'practice' | 'quiz' | 'test';

export type ActivityFieldSpec = {
    key: string;
    label: string;
    /** `audio`: a sentence played from stored TTS (CTRL-05). `lines`: one entry per line. */
    kind: 'text' | 'textarea' | 'audio' | 'lines';
    hint?: string;
    required?: boolean;
};

export type ActivityNumberSpec = {
    key: string;
    label: string;
    min: number;
    max: number;
    fallback: number;
};

export type ActivityMediaSpec = {
    key: 'image' | 'video' | 'poster' | 'audio';
    kind: 'image' | 'video' | 'audio';
    label: string;
    required?: boolean;
};

export type ActivityTypeSpec = {
    value: ActivityTypeKey;
    label: string;
    description: string;
    icon: Component;
    tone: string;
    /** An older type: editable, never offered for a new activity. */
    legacy: boolean;
    /** The instruction line a new activity starts with (English content). */
    defaultPrompt: string;
    fields: ActivityFieldSpec[];
    numbers: ActivityNumberSpec[];
    media: ActivityMediaSpec[];
    /**
     * The prompt may also be heard: a sentence played from stored TTS
     * (`audio_text`) and / or an uploaded, recorded or library clip
     * (`audio`). `required`: one of the two must be there.
     */
    promptAudio: 'optional' | 'required' | null;
    /** Answer options with one correct. */
    options: {
        key: 'text' | 'label' | 'audio_text';
        label: string;
        image: boolean;
        /** Text or picture answers, each with an optional 🔊 pronunciation. */
        flexible: boolean;
    } | null;
    /** `legacy`: words heard → answers (listen_match); `flexible`: pairs. */
    matching: 'legacy' | 'flexible' | null;
    /** Lines the learner puts in order. */
    ordering: {
        listKey: 'sentences' | 'cards';
        textKey: 'text' | 'caption';
        image: boolean;
    } | null;
    /** Short answer: the accepted answers. */
    accepted: boolean;
    /** Fill in the blank: a sentence with its blanks marked. */
    blanks: boolean;
    /** Writing: a rubric and a model answer for the AI evaluation. */
    rubric: boolean;
};

const questionField: ActivityFieldSpec = {
    key: 'question',
    label: tk('Question'),
    kind: 'textarea',
};

const pictureOptional: ActivityMediaSpec = {
    key: 'image',
    kind: 'image',
    label: tk('Picture (optional)'),
};

const flexibleOptions: ActivityTypeSpec['options'] = {
    key: 'text',
    label: tk('Answer'),
    image: true,
    flexible: true,
};

function spec(
    partial: Pick<
        ActivityTypeSpec,
        'value' | 'label' | 'description' | 'icon' | 'tone' | 'defaultPrompt'
    > &
        Partial<ActivityTypeSpec>,
): ActivityTypeSpec {
    return {
        legacy: false,
        fields: [],
        numbers: [],
        media: [],
        promptAudio: null,
        options: null,
        matching: null,
        ordering: null,
        accepted: false,
        blanks: false,
        rubric: false,
        ...partial,
    };
}

/** The client's ten types, in the order the builders list them. */
export const builderTypes: ActivityTypeSpec[] = [
    spec({
        value: 'multiple_choice',
        label: tk('Multiple Choice'),
        description: tk('Read the question, then choose the best answer.'),
        icon: ListChecks,
        tone: 'brand',
        defaultPrompt: 'Choose the best answer.',
        fields: [{ ...questionField, required: true }],
        media: [pictureOptional],
        promptAudio: 'optional',
        options: flexibleOptions,
    }),
    spec({
        value: 'ordering',
        label: tk('Ordering'),
        description: tk(
            'The learner puts words or sentences in the correct order.',
        ),
        icon: ListOrdered,
        tone: 'success',
        defaultPrompt: 'Put the sentences in the correct order.',
        fields: [questionField],
        media: [pictureOptional],
        promptAudio: 'optional',
        ordering: { listKey: 'sentences', textKey: 'text', image: true },
    }),
    spec({
        value: 'matching',
        label: tk('Matching'),
        description: tk('The learner matches each word with its pair.'),
        icon: Link,
        tone: 'ai',
        defaultPrompt: 'Match each word with its pair.',
        fields: [questionField],
        media: [pictureOptional],
        promptAudio: 'optional',
        matching: 'flexible',
    }),
    spec({
        value: 'short_answer',
        label: tk('Short Answer'),
        description: tk(
            'The learner types a short answer; it is compared with the accepted answers.',
        ),
        icon: TextCursorInput,
        tone: 'sunset',
        defaultPrompt: 'Type your answer.',
        fields: [{ ...questionField, required: true }],
        media: [pictureOptional],
        promptAudio: 'optional',
        accepted: true,
    }),
    spec({
        value: 'audio_question',
        label: tk('Audio Question'),
        description: tk(
            'The learner listens, then chooses the right answer or picture.',
        ),
        icon: Headphones,
        tone: 'brand',
        defaultPrompt: 'Listen and choose the correct answer.',
        fields: [questionField],
        media: [pictureOptional],
        promptAudio: 'required',
        options: flexibleOptions,
    }),
    spec({
        value: 'image_question',
        label: tk('Image Question'),
        description: tk(
            'The learner looks at a picture, then chooses the right answer.',
        ),
        icon: Image,
        tone: 'success',
        defaultPrompt: 'Look at the picture and choose the correct answer.',
        fields: [questionField],
        media: [
            {
                key: 'image',
                kind: 'image',
                label: tk('Picture'),
                required: true,
            },
        ],
        promptAudio: 'optional',
        options: flexibleOptions,
    }),
    spec({
        value: 'video_question',
        label: tk('Video Question'),
        description: tk(
            'The learner watches a short video, then chooses the right answer.',
        ),
        icon: Video,
        tone: 'blossom',
        defaultPrompt: 'Watch the video and choose the correct answer.',
        fields: [questionField],
        media: [
            { key: 'video', kind: 'video', label: tk('Video'), required: true },
            { key: 'poster', kind: 'image', label: tk('Poster image') },
        ],
        options: flexibleOptions,
    }),
    spec({
        value: 'speaking',
        label: tk('Speaking'),
        description: tk(
            'The learner records an answer; a sentence to say gets a pronunciation score.',
        ),
        icon: Mic,
        tone: 'ai',
        defaultPrompt: 'Listen, then record yourself.',
        fields: [
            questionField,
            {
                key: 'expected_text',
                label: tk('Sentence the learner says'),
                kind: 'text',
                hint: tk(
                    'Scored for pronunciation. Leave it empty for an open answer the AI judges.',
                ),
            },
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
        media: [pictureOptional],
        promptAudio: 'optional',
    }),
    spec({
        value: 'fill_blank',
        label: tk('Fill in the Blank'),
        description: tk('The learner types the missing word in each blank.'),
        icon: Keyboard,
        tone: 'gold',
        defaultPrompt: 'Type the missing word.',
        fields: [
            {
                key: 'question',
                label: tk('Instruction (optional)'),
                kind: 'text',
            },
        ],
        media: [pictureOptional],
        promptAudio: 'optional',
        blanks: true,
    }),
    spec({
        value: 'writing',
        label: tk('Writing Activity'),
        description: tk(
            'The learner writes an email or a reply; the AI scores it, corrects it and shows a better answer.',
        ),
        icon: PenLine,
        tone: 'aqua',
        defaultPrompt: 'Write a short reply.',
        fields: [
            {
                key: 'scenario',
                label: tk('Situation'),
                kind: 'textarea',
                required: true,
            },
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
            {
                key: 'model_answer',
                label: tk('Model answer (for the AI, never shown in a test)'),
                kind: 'textarea',
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
        media: [pictureOptional],
        promptAudio: 'optional',
        rubric: true,
    }),
];

/** Older types: their stored activities stay editable (DATA-11). */
const legacyTypes: ActivityTypeSpec[] = [
    spec({
        value: 'listen_choose',
        label: tk('Listen & Choose'),
        description: tk(
            'The learner hears a word or sentence and chooses the right picture.',
        ),
        icon: Headphones,
        tone: 'brand',
        legacy: true,
        defaultPrompt: 'Listen and choose the correct picture.',
        fields: [
            {
                key: 'audio_text',
                label: tk('Word or sentence the learner hears'),
                kind: 'audio',
            },
        ],
        options: {
            key: 'label',
            label: tk('Answer'),
            image: true,
            flexible: false,
        },
    }),
    spec({
        value: 'look_listen',
        label: tk('Look & Listen'),
        description: tk(
            'The learner looks at a picture and chooses the right audio.',
        ),
        icon: Image,
        tone: 'success',
        legacy: true,
        defaultPrompt: 'Look at the picture and choose the correct audio.',
        media: [{ key: 'image', kind: 'image', label: tk('Picture') }],
        options: {
            key: 'audio_text',
            label: tk('Sentence to play'),
            image: false,
            flexible: false,
        },
    }),
    spec({
        value: 'best_response',
        label: tk('Best Response'),
        description: tk(
            'The learner hears a guest and chooses the most appropriate reply.',
        ),
        icon: MessageCircle,
        tone: 'sunset',
        legacy: true,
        defaultPrompt: 'Listen to the guest and choose the best response.',
        fields: [
            { key: 'situation', label: tk('Situation'), kind: 'textarea' },
            {
                key: 'guest_audio_text',
                label: tk('What the guest says'),
                kind: 'audio',
            },
        ],
        options: {
            key: 'text',
            label: tk('Answer'),
            image: true,
            flexible: false,
        },
    }),
    spec({
        value: 'listen_match',
        label: tk('Listen & Match'),
        description: tk(
            'The learner hears words and matches each one with its answer.',
        ),
        icon: Link,
        tone: 'ai',
        legacy: true,
        defaultPrompt: 'Listen and match the words with the correct pictures.',
        matching: 'legacy',
    }),
    spec({
        value: 'words_sentences',
        label: tk('Words & Sentences'),
        description: tk(
            'The learner completes a sentence by choosing the missing word.',
        ),
        icon: Keyboard,
        tone: 'gold',
        legacy: true,
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
        media: [pictureOptional],
        options: {
            key: 'label',
            label: tk('Answer'),
            image: false,
            flexible: false,
        },
    }),
    spec({
        value: 'watch_respond',
        label: tk('Watch & Respond'),
        description: tk(
            'The learner watches a short video and chooses what to say next.',
        ),
        icon: Video,
        tone: 'blossom',
        legacy: true,
        defaultPrompt: 'Watch the video and choose the best response.',
        fields: [
            { key: 'subtitle', label: tk('Subtitle'), kind: 'text' },
            { key: 'question', label: tk('Question'), kind: 'text' },
            { key: 'hint', label: tk('Hint'), kind: 'text' },
        ],
        media: [
            { key: 'video', kind: 'video', label: tk('Video') },
            { key: 'poster', kind: 'image', label: tk('Poster image') },
        ],
        options: {
            key: 'text',
            label: tk('Answer'),
            image: false,
            flexible: false,
        },
    }),
    spec({
        value: 'dialogue_order',
        label: tk('Put the Dialogue in Order'),
        description: tk(
            'The learner listens and puts the sentences in the correct order.',
        ),
        icon: ListOrdered,
        tone: 'success',
        legacy: true,
        defaultPrompt: 'Listen and put the sentences in the correct order.',
        fields: [
            {
                key: 'audio_text',
                label: tk('Whole dialogue to play (optional)'),
                kind: 'audio',
            },
        ],
        ordering: { listKey: 'sentences', textKey: 'text', image: false },
    }),
    spec({
        value: 'picture_order',
        label: tk('Ordering a Conversation'),
        description: tk(
            'The learner puts picture cards of a conversation in order.',
        ),
        icon: Images,
        tone: 'brand',
        legacy: true,
        defaultPrompt: 'Put the conversation in the correct order.',
        fields: [{ key: 'context', label: tk('Situation'), kind: 'textarea' }],
        ordering: { listKey: 'cards', textKey: 'caption', image: true },
    }),
];

export const activityTypes: ActivityTypeSpec[] = [
    ...builderTypes,
    ...legacyTypes,
];

export function activityTypeSpec(type: string): ActivityTypeSpec {
    return (
        activityTypes.find((entry) => entry.value === type) ?? builderTypes[0]
    );
}

// ------------------------------------------------------------------ drafts

export type OptionDraft = {
    id: string;
    text: string;
    image: number | null;
    /** Play the answer's pronunciation (`audio_text` = its text). */
    speak: boolean;
};

export type PairDraft = {
    prompt: string;
    promptImage: number | null;
    promptAudio: number | null;
    target: string;
    image: number | null;
};

export type BlankDraft = {
    /** The answer written in the sentence, between [brackets]. */
    word: string;
    /** More answers accepted for the same blank. */
    alternatives: string[];
};

export type CriterionDraft = { key: string; label: string };

/** One item as the editor holds it; `extra` keeps keys the editor does not show. */
export type ItemDraft = {
    id: string;
    fields: Record<string, string>;
    numbers: Record<string, number | null>;
    media: Record<string, number | null>;
    /** The sentence played from stored TTS (`audio_text`). */
    audioText: string;
    optionStyle: 'text' | 'image';
    options: OptionDraft[];
    correct: string | null;
    pairs: PairDraft[];
    distractors: OptionDraft[];
    lines: OptionDraft[];
    accepted: string[];
    /** Fill in the blank: the sentence with its blanks as [word]. */
    sentence: string;
    blanks: BlankDraft[];
    criteria: CriterionDraft[];
    extra: Record<string, unknown>;
};

const LETTERS = 'abcdefghijklmnopqrstuvwxyz';

export const DEFAULT_CRITERIA: CriterionDraft[] = [
    { key: 'task_completion', label: 'Task completion' },
    { key: 'accuracy', label: 'Accuracy' },
    { key: 'politeness', label: 'Politeness' },
    { key: 'clarity', label: 'Clarity' },
];

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

function strings(value: unknown): string[] {
    return Array.isArray(value)
        ? value.map(str).filter((entry) => entry.trim() !== '')
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

/** `[[b1]]` tokens in a stored fill-in sentence. */
const BLANK_TOKEN = /\[\[([A-Za-z0-9_-]+)\]\]/gu;

/** `[word]` in the editor's sentence. */
const BRACKET = /\[([^[\]]*)\]/gu;

/** The words the admin marked as blanks, in order. */
export function bracketWords(sentence: string): string[] {
    return [...sentence.matchAll(BRACKET)].map((match) =>
        (match[1] ?? '').trim(),
    );
}

function handledKeys(type: ActivityTypeSpec): string[] {
    return [
        'id',
        ...type.fields.map((field) => field.key),
        ...type.numbers.map((field) => field.key),
        ...type.media.map((field) => field.key),
        ...(type.promptAudio !== null ? ['audio', 'audio_text'] : []),
        'options',
        'option_style',
        'correct',
        'prompts',
        'targets',
        'pairs',
        'sentences',
        'cards',
        'order',
        'accepted',
        'sentence',
        'blanks',
        'criteria',
    ];
}

function blankOption(index: number): OptionDraft {
    return {
        id: LETTERS[index] ?? `o${index + 1}`,
        text: '',
        image: null,
        speak: false,
    };
}

export function blankDraft(type: ActivityTypeSpec, id: string): ItemDraft {
    return {
        id,
        fields: Object.fromEntries(type.fields.map((field) => [field.key, ''])),
        numbers: Object.fromEntries(
            type.numbers.map((field) => [field.key, field.fallback]),
        ),
        media: Object.fromEntries(type.media.map((field) => [field.key, null])),
        audioText: '',
        optionStyle: 'text',
        options: type.options === null ? [] : [0, 1, 2].map(blankOption),
        correct: null,
        pairs:
            type.matching !== null
                ? [0, 1, 2].map(() => ({
                      prompt: '',
                      promptImage: null,
                      promptAudio: null,
                      target: '',
                      image: null,
                  }))
                : [],
        distractors: [],
        lines:
            type.ordering === null
                ? []
                : [0, 1, 2].map(() => ({
                      id: '',
                      text: '',
                      image: null,
                      speak: false,
                  })),
        accepted: type.accepted ? [''] : [],
        sentence: '',
        blanks: [],
        criteria: type.rubric ? DEFAULT_CRITERIA.map((c) => ({ ...c })) : [],
        extra: type.value === 'multiple_choice' ? { layout: 'side' } : {},
    };
}

export function draftFromItem(
    type: ActivityTypeSpec,
    item: LessonActivityItem,
): ItemDraft {
    const draft = blankDraft(type, str(item.id) || 'i1');
    const handled = handledKeys(type);

    draft.extra = Object.fromEntries(
        Object.entries(item).filter(([key]) => !handled.includes(key)),
    );

    for (const field of type.fields) {
        const value = item[field.key];
        draft.fields[field.key] =
            field.kind === 'lines' && Array.isArray(value)
                ? value.map(str).join('\n')
                : str(value);
    }

    for (const field of type.numbers) {
        draft.numbers[field.key] = num(item[field.key]) ?? field.fallback;
    }

    for (const field of type.media) {
        draft.media[field.key] = num(item[field.key]);
    }

    if (type.promptAudio !== null) {
        draft.media.audio = num(item.audio);
        draft.audioText = str(item.audio_text);
    }

    if (type.options !== null) {
        const key = type.options.key;
        draft.optionStyle = item.option_style === 'image' ? 'image' : 'text';
        draft.options = records(item.options).map((option) => ({
            id: str(option.id),
            text: str(option[key]),
            image: num(option.image),
            speak: key !== 'audio_text' && str(option.audio_text).trim() !== '',
        }));
        draft.correct = str(item.correct) || null;
    }

    if (type.matching !== null) {
        const targets = records(item.targets);
        const pairs =
            typeof item.pairs === 'object' && item.pairs !== null
                ? (item.pairs as Record<string, unknown>)
                : {};
        const used = new Set<string>();
        const promptKey = type.matching === 'legacy' ? 'audio_text' : 'text';
        const targetKey = type.matching === 'legacy' ? 'label' : 'text';

        draft.pairs = records(item.prompts).map((prompt) => {
            const targetId = str(pairs[str(prompt.id)]);
            const target = targets.find((entry) => str(entry.id) === targetId);
            used.add(targetId);

            return {
                prompt: str(prompt[promptKey]),
                promptImage: num(prompt.image),
                promptAudio: num(prompt.audio),
                target: str(target?.[targetKey]),
                image: num(target?.image),
            };
        });
        draft.distractors = targets
            .filter((target) => !used.has(str(target.id)))
            .map((target) => ({
                id: str(target.id),
                text: str(target[targetKey]),
                image: num(target.image),
                speak: false,
            }));
    }

    if (type.ordering !== null) {
        const { listKey, textKey } = type.ordering;
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
            speak: false,
        }));
    }

    if (type.accepted) {
        const accepted = strings(item.accepted);
        draft.accepted = accepted.length > 0 ? accepted : [''];
    }

    if (type.blanks) {
        const blanks = new Map(
            records(item.blanks).map((blank) => [
                str(blank.id),
                strings(blank.accepted),
            ]),
        );

        draft.blanks = [];
        draft.sentence = str(item.sentence).replace(
            BLANK_TOKEN,
            (_, id: string) => {
                const [word = '', ...alternatives] = blanks.get(id) ?? [];
                draft.blanks.push({ word, alternatives });

                return `[${word}]`;
            },
        );
    }

    if (type.rubric) {
        const criteria = records(item.criteria)
            .map((criterion) => ({
                key: str(criterion.key),
                label: str(criterion.label),
            }))
            .filter((criterion) => criterion.label.trim() !== '');
        draft.criteria =
            criteria.length > 0
                ? criteria
                : DEFAULT_CRITERIA.map((c) => ({ ...c }));
    }

    return draft;
}

function criterionKey(label: string, used: Set<string>): string {
    const base =
        label
            .toLowerCase()
            .replace(/[^a-z0-9]+/gu, '_')
            .replace(/^_+|_+$/gu, '') || 'criterion';
    let key = base;
    let n = 2;

    while (used.has(key)) {
        key = `${base}_${n}`;
        n += 1;
    }

    used.add(key);

    return key;
}

export function itemFromDraft(
    type: ActivityTypeSpec,
    draft: ItemDraft,
): LessonActivityItem {
    const item: LessonActivityItem = { ...draft.extra, id: draft.id };

    for (const field of type.fields) {
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

    for (const field of type.numbers) {
        item[field.key] = draft.numbers[field.key] ?? field.fallback;
    }

    for (const field of type.media) {
        item[field.key] = draft.media[field.key] ?? null;
    }

    if (type.promptAudio !== null) {
        item.audio = draft.media.audio ?? null;
        item.audio_text =
            draft.audioText.trim() === '' ? null : draft.audioText.trim();
    }

    if (type.options !== null) {
        const { key, image, flexible } = type.options;

        if (flexible) {
            item.option_style = draft.optionStyle;
        }

        item.options = draft.options.map((option) => ({
            id: option.id,
            [key]: option.text.trim(),
            ...(image ? { image: option.image } : {}),
            ...(flexible && option.speak && option.text.trim() !== ''
                ? { audio_text: option.text.trim() }
                : {}),
        }));
        item.correct = draft.correct;
    }

    if (type.matching !== null) {
        const legacy = type.matching === 'legacy';
        const rows = draft.pairs.filter(
            (pair) =>
                pair.prompt.trim() !== '' ||
                pair.target.trim() !== '' ||
                pair.promptImage !== null ||
                pair.image !== null,
        );
        const targetKey = legacy ? 'label' : 'text';
        const targets = [
            ...rows.map((pair, index) => ({
                id: LETTERS[index] ?? `t${index + 1}`,
                [targetKey]: pair.target.trim(),
                image: pair.image,
            })),
            ...draft.distractors.map((option, index) => ({
                id:
                    LETTERS[rows.length + index] ??
                    `t${rows.length + index + 1}`,
                [targetKey]: option.text.trim(),
                image: option.image,
            })),
        ];

        item.prompts = rows.map((pair, index) =>
            legacy
                ? { id: String(index + 1), audio_text: pair.prompt.trim() }
                : {
                      id: String(index + 1),
                      text: pair.prompt.trim(),
                      image: pair.promptImage,
                      audio: pair.promptAudio,
                  },
        );
        item.targets = scramble(targets);
        item.pairs = Object.fromEntries(
            rows.map((_, index) => [
                String(index + 1),
                LETTERS[index] ?? `t${index + 1}`,
            ]),
        );
    }

    if (type.ordering !== null) {
        const { listKey, textKey, image } = type.ordering;
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

    if (type.accepted) {
        item.accepted = draft.accepted
            .map((answer) => answer.trim())
            .filter((answer) => answer !== '');
    }

    if (type.blanks) {
        let index = 0;
        const blanks: { id: string; accepted: string[] }[] = [];

        item.sentence = draft.sentence
            .trim()
            .replace(BRACKET, (_, word: string) => {
                const blank = draft.blanks[index];
                const id = `b${index + 1}`;
                index += 1;
                blanks.push({
                    id,
                    accepted: [word, ...(blank?.alternatives ?? [])]
                        .map((answer) => answer.trim())
                        .filter((answer) => answer !== ''),
                });

                return `[[${id}]]`;
            });
        item.blanks = blanks;
    }

    if (type.rubric) {
        const used = new Set<string>();
        item.criteria = draft.criteria
            .filter((criterion) => criterion.label.trim() !== '')
            .map((criterion) => ({
                key:
                    criterion.key.trim() !== '' && !used.has(criterion.key)
                        ? (used.add(criterion.key), criterion.key)
                        : criterionKey(criterion.label, used),
                label: criterion.label.trim(),
            }));
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

/**
 * Keep the blanks list in step with the [words] of the sentence: the
 * alternatives of a blank follow it by position.
 */
export function syncBlanks(draft: ItemDraft): BlankDraft[] {
    return bracketWords(draft.sentence).map((word, index) => ({
        word,
        alternatives: draft.blanks[index]?.alternatives ?? [],
    }));
}

// ---------------------------------------------------------------- summaries

/** The line a list row shows for an activity: its first question-ish text. */
export function activitySummary(activity: LessonActivityRow): string {
    const item = activity.payload.items[0];

    if (item !== undefined) {
        for (const key of [
            'question',
            'sentence',
            'expected_text',
            'situation',
            'context',
            'scenario',
            'audio_text',
            'guest_audio_text',
            'subtitle',
        ]) {
            const value = str(item[key]).replace(BLANK_TOKEN, '____');

            if (value.trim() !== '') {
                return value;
            }
        }
    }

    return activity.prompt;
}

/** The correct answer of the first item in words, when the type has one. */
export function correctAnswer(activity: LessonActivityRow): string | null {
    const type = activityTypeSpec(activity.type);
    const item = activity.payload.items[0];

    if (item === undefined) {
        return null;
    }

    if (type.accepted) {
        const accepted = strings(item.accepted);

        return accepted.length > 0 ? accepted.join(' / ') : null;
    }

    if (type.blanks) {
        const words = records(item.blanks)
            .map((blank) => strings(blank.accepted)[0] ?? '')
            .filter((word) => word !== '');

        return words.length > 0 ? words.join(', ') : null;
    }

    if (type.options === null) {
        return null;
    }

    const option = records(item.options).find(
        (entry) => str(entry.id) === str(item.correct),
    );

    if (option === undefined) {
        return null;
    }

    return (
        str(option[type.options.key]) || str(option.id).toUpperCase() || null
    );
}
