/**
 * Settings → AI models (API-04): the Super Admin's model overrides over
 * .env, a fake/real switch per paid capability, and the last connection
 * test per capability. No key or endpoint ever travels here (SEC-03).
 */

/** '' = follow .env, 'fake' = off, 'real' = on. */
export type AiCapabilityMode = '' | 'fake' | 'real';

export type AiSwitchCapability = 'ai' | 'image' | 'tts' | 'stt';

export type AiCheckCapability = 'ai' | 'fast' | 'image' | 'tts' | 'stt';

export type AiModelValues = {
    aiMode: AiCapabilityMode;
    aiModel: string;
    aiFastModel: string;
    imageMode: AiCapabilityMode;
    imageModel: string;
    imageSizeLandscape: string;
    imageSizeSquare: string;
    ttsMode: AiCapabilityMode;
    ttsVoice: string;
    ttsExpressivity: number | null;
    sttMode: AiCapabilityMode;
    sttModel: string;
};

export type AiModelEnv = {
    aiProvider: string;
    aiModel: string;
    aiFastModel: string;
    imageProvider: string;
    imageModel: string;
    imageSizeLandscape: string;
    imageSizeSquare: string;
    ttsProvider: string;
    ttsVoice: string;
    ttsExpressivity: number;
    sttProvider: string;
    sttModel: string;
};

export type AiEffectiveCapability = {
    provider: string;
    model: string;
    keyConfigured: boolean;
};

export type AiModelSettingsPayload = {
    values: AiModelValues;
    env: AiModelEnv;
    effective: Record<AiCheckCapability, AiEffectiveCapability>;
    realDefaults: Record<AiSwitchCapability, string>;
    presets: {
        text: string[];
        image: string[];
        imageLandscape: string[];
        imageSquare: string[];
        ttsVoices: string[];
        stt: string[];
    };
};

export type AiCheckStatus = 'pending' | 'running' | 'ok' | 'failed';

export type AiCheckState = {
    status: AiCheckStatus;
    latency_ms: number | null;
    detail: string | null;
    error: string | null;
    checked_at: string | null;
    model: string | null;
    provider: string | null;
};

export type AiChecks = Record<AiCheckCapability, AiCheckState | null>;

/** One row of the Settings → AI usage price table (spec 0005 §4.3). */
export type AiPriceRow = {
    model: string;
    unit: string;
    /** Price per million units (tokens or characters). */
    input: number;
    output: number;
};

/** Settings → AI usage (API-03, AIL-04; spec 0005 §4.3). */
export type AiUsageReport = {
    period: number;
    hotel: number | null;
    totals: {
        calls: number;
        promptTokens: number;
        completionTokens: number;
        cost: number;
        points: number;
        /** Some rows were costed at today's prices. */
        estimated: boolean;
    };
    byModel: {
        provider: string;
        model: string;
        calls: number;
        promptTokens: number;
        completionTokens: number;
        cost: number;
        priced: boolean;
        unit: string | null;
        estimated: boolean;
    }[];
    byFeature: {
        feature: string;
        label: string;
        calls: number;
        cost: number;
    }[];
    byHotel: {
        hotelId: number | null;
        hotel: string;
        calls: number;
        cost: number;
    }[];
    /** Who used the points and where (client request 2026-10-02). */
    byUser?: {
        userId: number;
        name: string;
        username: string | null;
        hotel: string | null;
        points: number;
        calls: number;
        where: { label: string; points: number; calls: number }[];
    }[];
    daily: { date: string; label: string; calls: number; cost: number }[];
    unpricedModels: string[];
};
