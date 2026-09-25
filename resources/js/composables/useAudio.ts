import type { DeepReadonly, Ref } from 'vue';
import { readonly, ref } from 'vue';

export type UseAudioReturn = {
    /** URL of the clip playing right now, or null. */
    current: DeepReadonly<Ref<string | null>>;
    playing: DeepReadonly<Ref<boolean>>;
    /** Applied to the next play(); 1 = normal. Videos read it for "Slower". */
    playbackRate: Ref<number>;
    play: (url: string | null | undefined, rate?: number) => Promise<void>;
    stop: () => void;
    isPlaying: (url: string | null | undefined) => boolean;
};

/*
 * One <audio> element for the whole app, so tapping a second speaker stops
 * the first and two clips never overlap. Every clip is a pre-generated file
 * served by URL; nothing here ever synthesises (CTRL-05, TTS-02).
 */
let element: HTMLAudioElement | null = null;
const current = ref<string | null>(null);
const playing = ref(false);
const playbackRate = ref(1);

function el(): HTMLAudioElement {
    if (element === null) {
        element = new Audio();
        element.preload = 'none';
        element.addEventListener('ended', () => {
            playing.value = false;
            current.value = null;
        });
        element.addEventListener('pause', () => {
            playing.value = false;
        });
        element.addEventListener('play', () => {
            playing.value = true;
        });
    }

    return element;
}

export function useAudio(): UseAudioReturn {
    async function play(
        url: string | null | undefined,
        rate?: number,
    ): Promise<void> {
        if (!url || typeof window === 'undefined') {
            return;
        }

        const audio = el();

        if (current.value === url && !audio.paused) {
            audio.pause();
            audio.currentTime = 0;
            current.value = null;

            return;
        }

        audio.pause();
        audio.src = url;
        audio.playbackRate = rate ?? playbackRate.value;
        current.value = url;

        try {
            await audio.play();
        } catch {
            // Autoplay refused or the file is missing: never a broken state.
            playing.value = false;
            current.value = null;
        }
    }

    function stop(): void {
        if (element !== null) {
            element.pause();
            element.currentTime = 0;
        }

        current.value = null;
        playing.value = false;
    }

    function isPlaying(url: string | null | undefined): boolean {
        return Boolean(url) && playing.value && current.value === url;
    }

    return {
        current: readonly(current),
        playing: readonly(playing),
        playbackRate,
        play,
        stop,
        isPlaying,
    };
}
