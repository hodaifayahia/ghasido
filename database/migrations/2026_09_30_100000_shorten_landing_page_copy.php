<?php

use App\Models\LandingPageContent;
use App\Services\Landing\LandingPageArabicDefaults;
use App\Services\Landing\LandingPageContentStore;
use Illuminate\Database\Migrations\Migration;

/**
 * The shorter homepage (client request 2026-09-30) rewrote the copy of the
 * "How it works", AI practice and platform sections. A saved page keeps the
 * old default copy, so a field still holding it takes the new one; a field
 * the Super Admin changed is left alone.
 */
return new class extends Migration
{
    /** @var array<string, array<string, array<string, mixed>>> */
    private const OLD = [
        'en' => [
            'journey' => [
                'eyebrow' => 'A learning path that stays clear',
                'title' => 'From first assessment to confident guest conversations.',
                'description' => 'Every learner always knows what comes next, and progress is saved throughout the journey.',
            ],
            'features' => [
                'eyebrow' => 'The complete platform',
                'title' => 'Built to make training easier to run and easier to finish.',
                'description' => 'GHASIDO connects content, people and evidence of progress without adding administrative clutter.',
                'items' => ['Create hotel-specific learning without code', 'Keep every hotel team organised', 'Turn learning activity into useful evidence'],
            ],
            'ai' => [
                'description' => 'Give employees a safe place to try guest conversations and get encouraging feedback.',
                'items' => ['Hotel guest role-play', 'Speaking and writing feedback', 'AI-assisted content creation', 'Normal and slow lesson audio'],
            ],
        ],
        'ar' => [
            'journey' => [
                'eyebrow' => 'مسار تعلم يبقى واضحاً',
                'title' => 'من التقييم الأول إلى محادثات واثقة مع الضيوف.',
                'description' => 'يعرف كل متعلم دائماً خطوته التالية، ويُحفظ تقدمه طوال المسار.',
            ],
            'features' => [
                'eyebrow' => 'المنصة المتكاملة',
                'title' => 'مصممة لتسهيل إدارة التدريب وإتمامه.',
                'description' => 'تربط GHASIDO المحتوى والأشخاص وأدلة التقدم دون أعباء إدارية إضافية.',
                'items' => ['أنشئ تعلماً خاصاً بفندقك دون برمجة', 'حافظ على تنظيم كل فريق في الفندق', 'حوّل نشاط التعلم إلى أدلة مفيدة'],
            ],
            'ai' => [
                'description' => 'امنح الموظفين مساحة آمنة لتجربة المحادثات مع الضيوف والحصول على ملاحظات مشجعة.',
                'items' => ['لعب الأدوار مع ضيوف الفندق', 'ملاحظات على التحدث والكتابة', 'إنشاء المحتوى بمساعدة الذكاء الاصطناعي', 'صوت الدروس بسرعة عادية وبطيئة'],
            ],
        ],
    ];

    public function up(): void
    {
        $page = LandingPageContent::query()->find(1);

        if ($page === null) {
            return;
        }

        if (is_array($page->content)) {
            $page->content = $this->refresh($page->content, self::OLD['en'], app(LandingPageContentStore::class)->defaults());
        }

        if (is_array($page->content_ar)) {
            $page->content_ar = $this->refresh($page->content_ar, self::OLD['ar'], LandingPageArabicDefaults::all());
        }

        $page->save();
    }

    public function down(): void
    {
        // Copy only: nothing to undo.
    }

    /**
     * @param  array<string, mixed>  $stored
     * @param  array<string, array<string, mixed>>  $old
     * @param  array<string, mixed>  $new
     * @return array<string, mixed>
     */
    private function refresh(array $stored, array $old, array $new): array
    {
        foreach ($old as $section => $fields) {
            if (! is_array($stored[$section] ?? null)) {
                continue;
            }

            foreach ($fields as $field => $oldValue) {
                $current = $stored[$section][$field] ?? null;

                if ($field === 'items') {
                    $titles = is_array($current)
                        ? array_map(fn (mixed $item): mixed => is_array($item) ? ($item['title'] ?? null) : null, $current)
                        : null;

                    if ($titles === $oldValue) {
                        $stored[$section]['items'] = $new[$section]['items'];
                    }

                    continue;
                }

                if ($current === $oldValue) {
                    $stored[$section][$field] = $new[$section][$field];
                }
            }
        }

        return $stored;
    }
};
