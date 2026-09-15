export type LessonFilterOption = {
    value: string;
    label: string;
};

export type LessonsFilters = {
    hotel: string;
    department: string;
    course: string;
    unit: string;
    lesson: string;
    hotels: LessonFilterOption[];
    departments: LessonFilterOption[];
    courses: LessonFilterOption[];
    units: LessonFilterOption[];
    lessons: LessonFilterOption[];
};

export type LessonsTabKey =
    | 'content'
    | 'preview'
    | 'settings'
    | 'materials'
    | 'roleplay'
    | 'quiz';

export type LessonsTab = {
    key: LessonsTabKey;
    label: string;
};

export type LessonsTreeTone =
    | 'brand'
    | 'aqua'
    | 'success'
    | 'warning'
    | 'gold'
    | 'danger';

export type LessonsTreeLesson = {
    id: number;
    title: string;
    active?: boolean;
};

export type LessonsTreeUnit = {
    id: number;
    title: string;
    expanded?: boolean;
    lessons?: LessonsTreeLesson[];
    addLessonLabel?: string;
};

export type LessonsTreeCourse = {
    id: number;
    title: string;
    expanded?: boolean;
    tone?: LessonsTreeTone;
    units?: LessonsTreeUnit[];
};

export type LessonMockupCrop = {
    x: number;
    y: number;
    width: number;
    height: number;
};

export type LessonEditor = {
    title: string;
    titleCount: string;
    coverCrop: LessonMockupCrop;
    introduction: string;
    introductionCount: string;
    objectives: string[];
};

export type LessonBlockIcon =
    | 'situation'
    | 'vocabulary'
    | 'expressions'
    | 'dialogue'
    | 'audio'
    | 'image'
    | 'video'
    | 'practice'
    | 'roleplay'
    | 'quiz'
    | 'download'
    | 'note';

export type LessonBlockTone =
    | 'brand'
    | 'azure'
    | 'success'
    | 'warning'
    | 'danger'
    | 'ai'
    | 'aqua'
    | 'gold';

export type LessonBuilderBlock = {
    id: string;
    label: string;
    icon: LessonBlockIcon;
    tone: LessonBlockTone;
};

export type LessonLibraryTab = {
    key: string;
    label: string;
};

export type LessonLibraryImage = {
    id: string;
    label: string;
    crop: LessonMockupCrop;
};

export type LessonsImageLibrary = {
    activeTab: string;
    tabs: LessonLibraryTab[];
    search: string;
    category: string;
    categories: LessonFilterOption[];
    images: LessonLibraryImage[];
};
