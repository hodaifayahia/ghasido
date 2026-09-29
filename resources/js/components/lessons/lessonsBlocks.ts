import {
    AudioLines,
    Award,
    BookA,
    Bot,
    ClipboardCheck,
    Image,
    ListChecks,
    Mail,
    MessageSquareText,
    MessagesSquare,
    Phone,
    Quote,
    StickyNote,
    Type,
    Video,
    Volume2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import type { Component, Ref, WritableComputedRef } from 'vue';
import { tk } from '@/lib/i18n';
import type { BlockSettings, LessonMediaRef } from '@/types';

/**
 * A two-way binding on one key of a settings object held in a ref (the
 * block editor's local copy). Writing replaces the object, so the model
 * emits and nothing mutates a prop.
 */
export function settingField(
    settings: Ref<BlockSettings>,
    key: string,
): WritableComputedRef<string | number | null> {
    return computed({
        get: () => {
            const value = settings.value[key];

            return typeof value === 'string' || typeof value === 'number'
                ? value
                : null;
        },
        set: (value) => {
            settings.value = { ...settings.value, [key]: value ?? '' };
        },
    });
}

/** The same, for a key inside a nested object such as `example.text`. */
export function nestedField(
    settings: Ref<BlockSettings>,
    parent: string,
    key: string,
): WritableComputedRef<string | number | null> {
    return computed({
        get: () => {
            const value = settingObject(settings.value, parent)[key];

            return typeof value === 'string' || typeof value === 'number'
                ? value
                : null;
        },
        set: (value) => {
            settings.value = {
                ...settings.value,
                [parent]: {
                    ...settingObject(settings.value, parent),
                    [key]: value ?? '',
                },
            };
        },
    });
}

/** English labels of stored status and difficulty values, translated where rendered. */
export const valueLabels: Record<string, string> = {
    draft: tk('Draft'),
    published: tk('Published'),
    archived: tk('Archived'),
    beginner: tk('Beginner'),
    elementary: tk('Elementary'),
    intermediate: tk('Intermediate'),
};

/** The label of a stored value, or the value itself when it has none. */
export function valueLabel(value: string): string {
    return valueLabels[value] ?? value;
}

/** MIME type of a palette tile while it is being dragged onto the block list. */
export const BLOCK_DRAG_TYPE = 'application/x-guesvia-block-type';

/** MIME type of a Lesson Blocks row while it is being reordered. */
export const BLOCK_ROW_DRAG_TYPE = 'application/x-guesvia-block-id';

/**
 * The lucide glyph named by `BlockType::icon()` on the server (spec 0003
 * B.8). Unknown names fall back to a plain text glyph.
 */
const blockIcons: Record<string, Component> = {
    MessageSquareText,
    BookA,
    Quote,
    Volume2,
    MessagesSquare,
    Video,
    ListChecks,
    Bot,
    ClipboardCheck,
    Award,
    Type,
    StickyNote,
    Image,
    AudioLines,
    Mail,
    Phone,
};

export function blockIcon(name: string): Component {
    return blockIcons[name] ?? Type;
}

/** Chip classes per `BlockType::tone()` / `ActivityType::tone()`. */
export const toneChip: Record<string, string> = {
    brand: 'bg-brand-100 text-brand-700',
    aqua: 'bg-aqua-tint text-aqua',
    success: 'bg-success-tint text-success',
    warning: 'bg-warning-tint text-warning',
    gold: 'bg-gold-tint text-gold',
    danger: 'bg-danger-tint text-danger',
    ai: 'bg-ai-tint text-ai',
    azure: 'bg-azure-tint text-azure',
    sunset: 'bg-sunset/15 text-sunset',
    blossom: 'bg-blossom/15 text-blossom',
};

export function toneClass(tone: string): string {
    return toneChip[tone] ?? toneChip.brand;
}

// ------------------------------------------------------- settings helpers

export function settingString(settings: BlockSettings, key: string): string {
    const value = settings[key];

    return typeof value === 'string' ? value : '';
}

export function settingNumber(
    settings: BlockSettings,
    key: string,
): number | null {
    const value = settings[key];

    return typeof value === 'number' ? value : null;
}

export function settingList<T extends object>(
    settings: BlockSettings,
    key: string,
): T[] {
    const value = settings[key];

    return Array.isArray(value)
        ? value.filter((item): item is T => typeof item === 'object')
        : [];
}

export function settingObject(
    settings: BlockSettings,
    key: string,
): Record<string, unknown> {
    const value = settings[key];

    return typeof value === 'object' && value !== null && !Array.isArray(value)
        ? (value as Record<string, unknown>)
        : {};
}

export function mediaRef(
    media: Record<string, LessonMediaRef>,
    id: unknown,
): LessonMediaRef | null {
    return typeof id === 'number' ? (media[String(id)] ?? null) : null;
}

export type MediaLookup = {
    /** The media behind an id: the block's resolved map, or one picked in this session. */
    lookup: (id: unknown) => LessonMediaRef | null;
    /** Remember a media chosen in a slot so it renders before the block is saved. */
    remember: (media: LessonMediaRef | null) => void;
};

export function useMediaLookup(
    media: () => Record<string, LessonMediaRef>,
): MediaLookup {
    const picked = ref<Record<string, LessonMediaRef>>({});

    return {
        lookup: (id) =>
            typeof id === 'number'
                ? (picked.value[String(id)] ?? mediaRef(media(), id))
                : null,
        remember: (item) => {
            if (item !== null) {
                picked.value = { ...picked.value, [String(item.id)]: item };
            }
        },
    };
}

/** Deep clone through JSON: settings and payloads are plain data. */
export function cloneData<T>(value: T): T {
    return JSON.parse(JSON.stringify(value)) as T;
}

export function moveItem<T>(list: T[], from: number, to: number): void {
    if (to < 0 || to >= list.length || from === to) {
        return;
    }

    const [item] = list.splice(from, 1);

    if (item !== undefined) {
        list.splice(to, 0, item);
    }
}

/** The next free short id (`a`, `b`, … or `s1`, `s2`, …) for an option or sentence. */
export function nextId(existing: string[], prefix = ''): string {
    if (prefix === '') {
        const letters = 'abcdefghijklmnopqrstuvwxyz';

        for (const letter of letters) {
            if (!existing.includes(letter)) {
                return letter;
            }
        }
    }

    let n = existing.length + 1;

    while (existing.includes(`${prefix}${n}`)) {
        n += 1;
    }

    return `${prefix}${n}`;
}
