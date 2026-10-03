export type InterfaceLanguageRow = {
    id: number;
    code: string;
    name: string;
    native: string;
    dir: 'ltr' | 'rtl';
    enabled: boolean;
    status: 'draft' | 'generating' | 'ready' | 'failed';
    failedReason: string | null;
    translated: number;
};

export type InterfaceStringRow = {
    key: string;
    english: string;
    value: string;
};

export type InterfaceLanguageEditor = {
    search: string;
    show: 'all' | 'missing';
    rows: InterfaceStringRow[];
    page: number;
    lastPage: number;
    matching: number;
};
