<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Super Admin Lessons & Content builder screen (CMS-01, CMS-02, CMS-03,
 * CMS-05, CMS-06, BLD-01, BLD-02, BLD-03, BLD-06).
 *
 * Courses, units, lessons and the asset library are not modelled yet, so this
 * screen returns the approved mockup's sample data for a UI-first build.
 */
class LessonsContentController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/LessonsContent', [
            'filters' => [
                'hotel' => 'la-gazelle-dor',
                'department' => 'reception',
                'course' => 'guest-service-basics',
                'unit' => 'unit-1',
                'lesson' => 'lesson-1',
                'hotels' => [
                    ['value' => 'la-gazelle-dor', 'label' => 'La Gazelle d\'Or'],
                    ['value' => 'aurassi', 'label' => __('Hotel El Aurassi')],
                    [
                        'value' => 'sheraton-club',
                        'label' => __('Sheraton Club des Pins'),
                    ],
                ],
                'departments' => [
                    ['value' => 'reception', 'label' => __('Reception')],
                    ['value' => 'spa', 'label' => __('Spa')],
                    [
                        'value' => 'housekeeping',
                        'label' => __('Housekeeping'),
                    ],
                    ['value' => 'food-service', 'label' => __('Food Service')],
                ],
                'courses' => [
                    [
                        'value' => 'guest-service-basics',
                        'label' => __('Guest Service Basics'),
                    ],
                    [
                        'value' => 'restaurant-communication',
                        'label' => __('Restaurant Communication'),
                    ],
                    [
                        'value' => 'housekeeping-essentials',
                        'label' => __('Housekeeping Essentials'),
                    ],
                ],
                'units' => [
                    ['value' => 'unit-1', 'label' => __('1. Welcoming Guests')],
                    ['value' => 'unit-2', 'label' => __('2. Check-in Process')],
                    ['value' => 'unit-3', 'label' => __('3. During the Stay')],
                ],
                'lessons' => [
                    ['value' => 'lesson-1', 'label' => __('1. Greeting Guests')],
                    [
                        'value' => 'lesson-2',
                        'label' => __('2. Introducing Yourself'),
                    ],
                    [
                        'value' => 'lesson-3',
                        'label' => __('3. Asking for Information'),
                    ],
                ],
            ],
            'tabs' => [
                ['key' => 'content', 'label' => __('Lesson Content')],
                ['key' => 'preview', 'label' => __('Preview')],
                ['key' => 'settings', 'label' => __('Settings')],
                ['key' => 'materials', 'label' => __('Materials')],
                ['key' => 'roleplay', 'label' => __('AI Role-play')],
                ['key' => 'quiz', 'label' => __('Quiz / Practice')],
            ],
            'activeTab' => 'content',
            'courses' => [
                [
                    'id' => 1,
                    'title' => __('Guest Service Basics'),
                    'expanded' => true,
                    'tone' => 'brand',
                    'units' => [
                        [
                            'id' => 11,
                            'title' => __('Unit 1: Welcoming Guests'),
                            'expanded' => true,
                            'lessons' => [
                                [
                                    'id' => 111,
                                    'title' => __('1. Greeting Guests'),
                                    'active' => true,
                                ],
                                [
                                    'id' => 112,
                                    'title' => __('2. Introducing Yourself'),
                                ],
                                [
                                    'id' => 113,
                                    'title' => __('3. Asking for Information'),
                                ],
                            ],
                            'addLessonLabel' => __('+ Add Lesson'),
                        ],
                        [
                            'id' => 12,
                            'title' => __('Unit 2: Check-in Process'),
                        ],
                        [
                            'id' => 13,
                            'title' => __('Unit 3: During the Stay'),
                        ],
                        [
                            'id' => 14,
                            'title' => __('Unit 4: Check-out'),
                        ],
                        [
                            'id' => 15,
                            'title' => __('Unit 5: Handling Complaints'),
                        ],
                    ],
                ],
                [
                    'id' => 2,
                    'title' => __('Restaurant Communication'),
                    'tone' => 'aqua',
                ],
                [
                    'id' => 3,
                    'title' => __('Housekeeping Essentials'),
                    'tone' => 'success',
                ],
                [
                    'id' => 4,
                    'title' => __('Spa and Wellness'),
                    'tone' => 'warning',
                ],
                [
                    'id' => 5,
                    'title' => __('Kitchen Communication'),
                    'tone' => 'gold',
                ],
                [
                    'id' => 6,
                    'title' => __('Telephone English'),
                    'tone' => 'danger',
                ],
            ],
            'editor' => [
                'title' => __('Greeting Guests'),
                'titleCount' => '15/100',
                'coverCrop' => [
                    'x' => 466,
                    'y' => 293,
                    'width' => 351,
                    'height' => 93,
                ],
                'introduction' => __(
                    'In this lesson, you will learn how to greet guests in a friendly and professional way. You will practice useful expressions, listen to a short dialogue, and try an interactive role-play.'
                ),
                'introductionCount' => '162/500',
                'objectives' => [
                    __('Use polite and professional greetings'),
                    __('Respond to common guest greetings'),
                    __('Show a welcoming attitude'),
                ],
            ],
            'blocks' => [
                ['id' => 'situation', 'label' => __('Situation'), 'icon' => 'situation', 'tone' => 'brand'],
                ['id' => 'vocabulary', 'label' => __('Vocabulary'), 'icon' => 'vocabulary', 'tone' => 'success'],
                ['id' => 'expressions', 'label' => __('Useful Expressions'), 'icon' => 'expressions', 'tone' => 'warning'],
                ['id' => 'dialogue', 'label' => __('Dialogue'), 'icon' => 'dialogue', 'tone' => 'ai'],
                ['id' => 'audio', 'label' => __('Watch & Listen'), 'icon' => 'audio', 'tone' => 'brand'],
                ['id' => 'gallery', 'label' => __('Image / Gallery'), 'icon' => 'image', 'tone' => 'success'],
                ['id' => 'video', 'label' => __('Video'), 'icon' => 'video', 'tone' => 'danger'],
                ['id' => 'practice', 'label' => __('Practice Activity'), 'icon' => 'practice', 'tone' => 'warning'],
                ['id' => 'roleplay', 'label' => __('AI Role-play'), 'icon' => 'roleplay', 'tone' => 'brand'],
                ['id' => 'quiz', 'label' => __('Quiz / Test'), 'icon' => 'quiz', 'tone' => 'ai'],
                ['id' => 'download', 'label' => __('Downloadable File'), 'icon' => 'download', 'tone' => 'azure'],
                ['id' => 'note', 'label' => __('Note / Tip'), 'icon' => 'note', 'tone' => 'gold'],
            ],
            'library' => [
                'activeTab' => 'my-images',
                'tabs' => [
                    ['key' => 'my-images', 'label' => __('My Images')],
                    [
                        'key' => 'guesvia-library',
                        'label' => __('Guesvia Library'),
                    ],
                    [
                        'key' => 'icons-stickers',
                        'label' => __('Icons & Stickers'),
                    ],
                ],
                'search' => '',
                'category' => 'all-categories',
                'categories' => [
                    [
                        'value' => 'all-categories',
                        'label' => __('All Categories'),
                    ],
                    ['value' => 'reception', 'label' => __('Reception')],
                    ['value' => 'hotel', 'label' => __('Hotel')],
                    ['value' => 'people', 'label' => __('People')],
                ],
                'images' => [
                    [
                        'id' => 'reception',
                        'label' => 'reception.jpg',
                        'crop' => [
                            'x' => 982,
                            'y' => 566,
                            'width' => 67,
                            'height' => 47,
                        ],
                    ],
                    [
                        'id' => 'welcome',
                        'label' => 'welcome.jpg',
                        'crop' => [
                            'x' => 1052,
                            'y' => 566,
                            'width' => 67,
                            'height' => 47,
                        ],
                    ],
                    [
                        'id' => 'bell',
                        'label' => 'bell.jpg',
                        'crop' => [
                            'x' => 1122,
                            'y' => 566,
                            'width' => 67,
                            'height' => 47,
                        ],
                    ],
                    [
                        'id' => 'lobby',
                        'label' => 'hotel_lobby.jpg',
                        'crop' => [
                            'x' => 1192,
                            'y' => 566,
                            'width' => 67,
                            'height' => 47,
                        ],
                    ],
                    [
                        'id' => 'guests',
                        'label' => 'guest.jpg',
                        'crop' => [
                            'x' => 982,
                            'y' => 624,
                            'width' => 67,
                            'height' => 47,
                        ],
                    ],
                    [
                        'id' => 'smile',
                        'label' => 'smile.jpg',
                        'crop' => [
                            'x' => 1052,
                            'y' => 624,
                            'width' => 67,
                            'height' => 47,
                        ],
                    ],
                    [
                        'id' => 'key',
                        'label' => 'key.jpg',
                        'crop' => [
                            'x' => 1122,
                            'y' => 624,
                            'width' => 67,
                            'height' => 47,
                        ],
                    ],
                    [
                        'id' => 'suitcase',
                        'label' => 'suitcase.jpg',
                        'crop' => [
                            'x' => 1192,
                            'y' => 624,
                            'width' => 67,
                            'height' => 47,
                        ],
                    ],
                ],
            ],
        ]);
    }
}