<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Super Admin AI role-play scenarios screen (RP-01, RP-04, RP-05,
 * RP-08, RP-11, AIE-04, GEN-03, TSTM-05, ADM-02).
 *
 * Scenarios, departments and preview transcripts are not modelled yet, so
 * this screen returns sample data shaped to the approved mockup for a UI-first
 * build.
 */
class AiScenariosController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/AiScenarios', [
            'tabs' => [
                ['key' => 'scenarios', 'label' => __('Scenarios')],
                [
                    'key' => 'categories',
                    'label' => __('Scenario Categories'),
                ],
                ['key' => 'instructions', 'label' => __('AI Instructions')],
                ['key' => 'feedback', 'label' => __('Feedback Templates')],
                ['key' => 'preview', 'label' => __('Preview & Test')],
            ],
            'activeTab' => 'scenarios',
            'library' => [
                'search' => '',
                'department' => 'all-departments',
                'status' => 'all-statuses',
                'departments' => [
                    [
                        'value' => 'all-departments',
                        'label' => __('All Departments'),
                    ],
                    ['value' => 'reception', 'label' => __('Reception')],
                    [
                        'value' => 'food-service',
                        'label' => __('Food & Beverage'),
                    ],
                    ['value' => 'spa', 'label' => __('Spa')],
                    [
                        'value' => 'housekeeping',
                        'label' => __('Housekeeping'),
                    ],
                ],
                'statuses' => [
                    ['value' => 'all-statuses', 'label' => __('All Statuses')],
                    ['value' => 'published', 'label' => __('Published')],
                    ['value' => 'draft', 'label' => __('Draft')],
                ],
                'scenarios' => [
                    [
                        'id' => 'guest-check-in',
                        'title' => __('Guest Check-in'),
                        'department' => __('Reception'),
                        'level' => __('Level: Beginner'),
                        'status' => 'published',
                        'crop' => [
                            'x' => 197,
                            'y' => 238,
                            'width' => 78,
                            'height' => 55,
                        ],
                    ],
                    [
                        'id' => 'room-service',
                        'title' => __('Room Service Request'),
                        'department' => __('Food & Beverage'),
                        'level' => __('Level: Elementary'),
                        'status' => 'published',
                        'crop' => [
                            'x' => 197,
                            'y' => 307,
                            'width' => 78,
                            'height' => 55,
                        ],
                    ],
                    [
                        'id' => 'spa-information',
                        'title' => __('Spa Information'),
                        'department' => __('Spa'),
                        'level' => __('Level: Elementary'),
                        'status' => 'draft',
                        'crop' => [
                            'x' => 197,
                            'y' => 376,
                            'width' => 78,
                            'height' => 55,
                        ],
                    ],
                    [
                        'id' => 'complaint',
                        'title' => __('Handling a Complaint'),
                        'department' => __('Reception'),
                        'level' => __('Level: Intermediate'),
                        'status' => 'published',
                        'crop' => [
                            'x' => 197,
                            'y' => 445,
                            'width' => 78,
                            'height' => 55,
                        ],
                    ],
                    [
                        'id' => 'directions',
                        'title' => __('Giving Directions'),
                        'department' => __('Reception'),
                        'level' => __('Level: Beginner'),
                        'status' => 'published',
                        'crop' => [
                            'x' => 197,
                            'y' => 514,
                            'width' => 78,
                            'height' => 55,
                        ],
                    ],
                    [
                        'id' => 'lost-item',
                        'title' => __('Lost Item'),
                        'department' => __('Housekeeping'),
                        'level' => __('Level: Intermediate'),
                        'status' => 'draft',
                        'crop' => [
                            'x' => 197,
                            'y' => 583,
                            'width' => 78,
                            'height' => 55,
                        ],
                    ],
                    [
                        'id' => 'special-requests',
                        'title' => __('Special Requests'),
                        'department' => __('Food & Beverage'),
                        'level' => __('Level: Intermediate'),
                        'status' => 'published',
                        'crop' => [
                            'x' => 197,
                            'y' => 652,
                            'width' => 78,
                            'height' => 55,
                        ],
                    ],
                ],
                'showing' => __('Showing 1-7 of 24 scenarios'),
                'pages' => [1, 2, 3, 4],
                'currentPage' => 1,
            ],
            'editor' => [
                'status' => __('Published'),
                'title' => __('Guest Check-in'),
                'titleCount' => '15/100',
                'department' => 'reception',
                'level' => 'beginner',
                'departments' => [
                    ['value' => 'reception', 'label' => __('Reception')],
                    ['value' => 'spa', 'label' => __('Spa')],
                    [
                        'value' => 'food-service',
                        'label' => __('Food & Beverage'),
                    ],
                    [
                        'value' => 'housekeeping',
                        'label' => __('Housekeeping'),
                    ],
                ],
                'levels' => [
                    ['value' => 'beginner', 'label' => __('Beginner')],
                    ['value' => 'elementary', 'label' => __('Elementary')],
                    [
                        'value' => 'intermediate',
                        'label' => __('Intermediate'),
                    ],
                ],
                'coverCrop' => [
                    'x' => 544,
                    'y' => 313,
                    'width' => 245,
                    'height' => 94,
                ],
                'description' => __(
                    'The guest arrives at the hotel. They want to check in, ask about the room, and get some information about hotel facilities.'
                ),
                'descriptionCount' => '104/500',
                'guestRole' => __(
                    'You are an international guest. You are polite and friendly. You want to check in, ask some questions and make a special request.'
                ),
                'employeeRole' => __(
                    'You are a hotel receptionist. Welcome the guest, check their reservation, provide information, and respond to their requests professionally.'
                ),
                'objectives' => [
                    __('Use polite greetings and welcoming language'),
                    __('Ask and answer questions about the reservation'),
                    __('Provide information about hotel facilities'),
                    __('Handle special requests politely'),
                ],
            ],
            'preview' => [
                'messages' => [
                    [
                        'id' => 1,
                        'actor' => 'guest',
                        'text' => __('Good afternoon! I have a reservation under the name Smith.'),
                    ],
                    [
                        'id' => 2,
                        'actor' => 'employee',
                        'text' => __('Good afternoon, Mr. Smith! Welcome to our hotel. Let me check your reservation, please.'),
                        'avatarCrop' => [
                            'x' => 1173,
                            'y' => 274,
                            'width' => 39,
                            'height' => 46,
                        ],
                    ],
                    [
                        'id' => 3,
                        'actor' => 'guest',
                        'text' => __('Thank you. Can you tell me what time the breakfast is?'),
                    ],
                    [
                        'id' => 4,
                        'actor' => 'employee',
                        'text' => __('Of course. Breakfast is served from 6:30 a.m. to 10:00 a.m. in our main restaurant.'),
                        'avatarCrop' => [
                            'x' => 1173,
                            'y' => 387,
                            'width' => 39,
                            'height' => 47,
                        ],
                    ],
                ],
                'placeholder' => __('Type your message here...'),
            ],
            'settings' => [
                'attempts' => '3',
                'attemptOptions' => [
                    ['value' => '1', 'label' => '1'],
                    ['value' => '2', 'label' => '2'],
                    ['value' => '3', 'label' => '3'],
                ],
                'feedbackStyle' => 'encouraging',
                'feedbackStyles' => [
                    [
                        'value' => 'encouraging',
                        'label' => __('Encouraging and constructive'),
                    ],
                    [
                        'value' => 'balanced',
                        'label' => __('Balanced coaching'),
                    ],
                ],
                'focusAreas' => [
                    ['label' => __('Vocabulary'), 'checked' => true],
                    ['label' => __('Grammar'), 'checked' => true],
                    ['label' => __('Pronunciation'), 'checked' => true],
                    ['label' => __('Fluency'), 'checked' => true],
                    ['label' => __('Task Completion'), 'checked' => true],
                ],
                'allowHints' => true,
                'showSuggestions' => true,
                'tags' => [
                    'check-in',
                    'reservation',
                    'greetings',
                    'hotel facilities',
                ],
            ],
        ]);
    }
}