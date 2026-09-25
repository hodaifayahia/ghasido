<?php

/*
 * The fully built lesson "Handling Guest Complaints" (spec 0003 G.3), nine
 * visible blocks in mockup order. Copy is transcribed from
 * desginphotos/employ/photo_1 … photo_6, photo_15 and photo_19; the settings
 * follow the B.10 contracts, with `media:<name>` resolved by the seeder.
 */

return [
    'lesson' => [
        'title' => 'Handling Guest Complaints',
        'introduction' => 'In this lesson, you will learn how to respond to common guest complaints in a polite and professional way.',
        'objectives' => [
            'Understand the situation',
            'Learn useful language',
            'Practice and be ready for real situations',
        ],
        'cover' => 'media:situation-complaint',
        'estimated_minutes' => 25,
    ],

    // 1. Situation (photo_1)
    'situation' => [
        'quote' => '“A calm and polite response can turn a problem into a positive experience.”',
        'objectives' => [
            ['icon' => 'chat', 'text' => 'Understand the situation'],
            ['icon' => 'people', 'text' => 'Learn useful language'],
            ['icon' => 'check', 'text' => 'Practice and be ready for real situations'],
        ],
    ],

    // 2. Vocabulary (photo_2): the featured word first, then the five related words.
    'vocabulary' => [
        'settings' => [
            'tip' => "Tap the speaker to hear the pronunciation.\nTap the eye icon to see the meaning.",
            'side_title' => 'Related Words',
        ],
        'items' => [
            [
                'english_text' => 'Air conditioning',
                'ipa' => '/ˈer kənˌdɪʃənɪŋ/',
                'part_of_speech' => 'n',
                'arabic_meaning' => 'تكييف الهواء',
                'simple_explanation' => 'A machine that cools the air in a room.',
                'hotel_example' => "The air conditioning in my room isn't working.",
                'hotel_example_arabic' => 'التكييف في غرفتي لا يعمل.',
                'image' => 'media:vocab-air-conditioning',
            ],
            [
                'english_text' => 'AC (air conditioner)',
                'ipa' => '/ˌeɪ ˈsiː/',
                'part_of_speech' => 'n',
                'arabic_meaning' => 'مكيّف الهواء',
                'simple_explanation' => 'A short name for the air conditioning machine.',
                'hotel_example' => 'Could you turn on the AC, please?',
                'hotel_example_arabic' => 'هل يمكنك تشغيل المكيّف من فضلك؟',
                'image' => 'media:vocab-ac',
            ],
            [
                'english_text' => 'temperature',
                'ipa' => '/ˈtemprətʃər/',
                'part_of_speech' => 'n',
                'arabic_meaning' => 'درجة الحرارة',
                'simple_explanation' => 'How hot or cold something is.',
                'hotel_example' => 'What temperature would you like in your room?',
                'hotel_example_arabic' => 'ما درجة الحرارة التي تفضّلها في غرفتك؟',
                'image' => 'media:vocab-temperature',
            ],
            [
                'english_text' => 'maintenance',
                'ipa' => '/ˈmeɪntənəns/',
                'part_of_speech' => 'n',
                'arabic_meaning' => 'الصيانة',
                'simple_explanation' => 'The team or work that keeps things in good condition.',
                'hotel_example' => 'I will call maintenance to check the problem.',
                'hotel_example_arabic' => 'سأتصل بالصيانة لفحص المشكلة.',
                'image' => 'media:vocab-maintenance',
            ],
            [
                'english_text' => 'broken',
                'ipa' => '/ˈbroʊkən/',
                'part_of_speech' => 'adj',
                'arabic_meaning' => 'معطّل',
                'simple_explanation' => 'Not working; damaged.',
                'hotel_example' => 'The remote control is broken.',
                'hotel_example_arabic' => 'جهاز التحكم عن بُعد معطّل.',
                'image' => 'media:vocab-broken',
            ],
            [
                'english_text' => 'repair',
                'ipa' => '/rɪˈper/',
                'part_of_speech' => 'v',
                'arabic_meaning' => 'يصلح',
                'simple_explanation' => 'To fix something that is broken.',
                'hotel_example' => 'A technician will repair it this afternoon.',
                'hotel_example_arabic' => 'سيقوم فنيّ بإصلاحه بعد الظهر.',
                'image' => 'media:vocab-repair',
            ],
        ],
    ],

    // 3. Useful Expressions (photo_3)
    'expressions' => [
        'settings' => [
            'subtitle' => 'Learn and practise common phrases for this situation.',
            'tip' => "Click the speaker to hear the pronunciation.\nClick the eye icon to see the meaning in Arabic.",
            'side_title' => 'More Useful Expressions',
        ],
        'items' => [
            [
                'english_text' => "I'm sorry for the inconvenience.",
                'ipa' => '/aɪm ˈsɒri fɔːr ði ɪnˈkʌnviniəns/',
                'part_of_speech' => 'phrase',
                'arabic_meaning' => 'أنا آسف على الإزعاج.',
                'simple_explanation' => 'A polite way to apologise when a guest has a problem.',
                'hotel_example' => "I'm sorry for the inconvenience. I will fix this right away.",
                'hotel_example_arabic' => 'أنا آسف على الإزعاج. سأصلح هذا على الفور.',
                'image' => 'media:expr-sorry-inconvenience',
            ],
            [
                'english_text' => 'How can I help you?',
                'ipa' => '/haʊ kæn aɪ help juː/',
                'part_of_speech' => 'phrase',
                'arabic_meaning' => 'كيف يمكنني مساعدتك؟',
                'simple_explanation' => 'The question you ask a guest who comes to the desk.',
                'hotel_example' => 'Good morning. How can I help you?',
                'hotel_example_arabic' => 'صباح الخير. كيف يمكنني مساعدتك؟',
                'image' => 'media:expr-how-can-i-help',
            ],
            [
                'english_text' => 'Let me check that for you.',
                'ipa' => '/let mi tʃek ðæt fɔːr juː/',
                'part_of_speech' => 'phrase',
                'arabic_meaning' => 'دعني أتحقق من ذلك لك.',
                'simple_explanation' => 'Say this before you look something up for a guest.',
                'hotel_example' => 'Let me check that for you. One moment, please.',
                'hotel_example_arabic' => 'دعني أتحقق من ذلك لك. لحظة من فضلك.',
                'image' => 'media:expr-let-me-check',
            ],
            [
                'english_text' => 'Would you like to make a reservation?',
                'ipa' => '/wʊd juː laɪk tu meɪk ə ˌrezərˈveɪʃən/',
                'part_of_speech' => 'phrase',
                'arabic_meaning' => 'هل تودّ إجراء حجز؟',
                'simple_explanation' => 'A polite offer to book a room or a table.',
                'hotel_example' => 'Would you like to make a reservation for dinner tonight?',
                'hotel_example_arabic' => 'هل تودّ حجز طاولة للعشاء الليلة؟',
                'image' => 'media:expr-reservation',
            ],
            [
                'english_text' => 'The room will be ready at 3 p.m.',
                'ipa' => '/ðə ruːm wɪl bi ˈredi æt θriː piː em/',
                'part_of_speech' => 'phrase',
                'arabic_meaning' => 'ستكون الغرفة جاهزة في الساعة الثالثة مساءً.',
                'simple_explanation' => 'Tells the guest when they can go to their room.',
                'hotel_example' => 'The room will be ready at 3 p.m. You can leave your bags here.',
                'hotel_example_arabic' => 'ستكون الغرفة جاهزة في الثالثة مساءً. يمكنك ترك حقائبك هنا.',
                'image' => 'media:expr-room-ready',
            ],
            [
                'english_text' => 'I can show you the way.',
                'ipa' => '/aɪ kæn ʃoʊ juː ðə weɪ/',
                'part_of_speech' => 'phrase',
                'arabic_meaning' => 'يمكنني أن أدلّك على الطريق.',
                'simple_explanation' => 'An offer to walk the guest to a place.',
                'hotel_example' => 'The restaurant is on the first floor. I can show you the way.',
                'hotel_example_arabic' => 'المطعم في الطابق الأول. يمكنني أن أدلّك على الطريق.',
                'image' => 'media:expr-show-the-way',
            ],
        ],
    ],

    // 4. Listen & Repeat (photo_4)
    'listen_repeat' => [
        'subtitle' => 'Listen to the sentence, then repeat it. Try to sound like the native speaker.',
        'tip' => 'Try to speak clearly and at a similar speed.',
        'step_listen' => ['title' => 'Step 1: Listen', 'text' => 'Click the button to hear the sentence.'],
        'step_repeat' => ['title' => 'Step 2: Repeat', 'text' => 'Click the microphone and repeat the sentence.'],
        'record_label' => 'Tap to record',
        'recording_label' => 'Your recording',
        'success_text' => 'Great! Keep practicing!',
        'items' => [
            ['text' => 'How can I help you?', 'arabic' => 'كيف يمكنني مساعدتك؟', 'image' => 'media:listen-how-can-i-help'],
        ],
    ],

    // 5. Dialogue (photo_5). Only the first line is visible in the mockup;
    // the other five continue the same check-in.
    'dialogue' => [
        'subtitle' => 'Listen to the conversation. Take turns and follow the dialogue step by step.',
        'image' => 'media:dialogue-checkin',
        'staff_avatar' => 'media:dialogue-staff-avatar',
        'situation_caption' => 'Situation: Check-in at the hotel',
        'tip' => 'Listen carefully, then repeat. Focus on pronunciation and intonation.',
        'lines' => [
            ['speaker' => 'staff', 'text' => "Good afternoon.\nHow can I help you?", 'arabic' => "مساء الخير.\nكيف يمكنني مساعدتك؟"],
            ['speaker' => 'guest', 'text' => 'Good afternoon. I have a reservation under the name Ben Ali.', 'arabic' => 'مساء الخير. لديّ حجز باسم بن علي.'],
            ['speaker' => 'staff', 'text' => 'Let me check that for you. Yes, a double room for three nights.', 'arabic' => 'دعني أتحقق من ذلك لك. نعم، غرفة مزدوجة لثلاث ليالٍ.'],
            ['speaker' => 'guest', 'text' => "That's right. Is breakfast included?", 'arabic' => 'هذا صحيح. هل الإفطار مشمول؟'],
            ['speaker' => 'staff', 'text' => 'Yes, it is. Here is your key. Your room is 215.', 'arabic' => 'نعم. هذا مفتاحك. غرفتك رقم 215.'],
            ['speaker' => 'guest', 'text' => 'Thank you very much.', 'arabic' => 'شكراً جزيلاً.'],
        ],
    ],

    // 6. Video (photo_6). No video file is seeded: the player shows the
    // poster and the controls bar (documented in the lane report).
    'video' => [
        'subtitle' => 'Watch the short video and see how the conversation happens in real life.',
        'video' => null,
        'poster' => 'media:video-poster-reception',
        'controls_title' => 'Video Controls',
        'controls_note' => 'Watch as many times as you need.',
        'example_title' => 'Example from the Video',
        'example' => [
            'text' => 'How can I help you?',
            'arabic' => 'كيف يمكنني مساعدتك؟',
            'note' => 'This is one of the sentences from the video.',
        ],
        'tip' => 'Watch, listen and notice the language, gestures and tone of voice.',
    ],

    // 7. Practice (photo_7). Activities come from practice-activities.php.
    'practice' => [
        'subtitle' => 'Choose a practice activity to improve your skills.',
        'motto' => 'Complete the activities and take a step closer to real conversations!',
        'progress_label' => 'activities completed',
    ],

    // 8. AI Role-play (photo_15). scenario_ids are filled by the seeder.
    'ai_roleplay' => [
        'subtitle' => 'Choose a scenario and practice with our AI guest.',
        'attempts_note' => 'You have 3 attempts for each scenario.',
        'tip' => 'Choose a situation that is relevant to your job. You can try each scenario up to 3 times.',
    ],

    // 9. Lesson Completed (photo_19)
    'complete' => [
        'subtitle' => 'Great job! You have finished this lesson.',
        'image' => 'media:complete-thumbs-up',
        'quote' => "“Small steps\nmake a big difference!”",
        'closing_quote' => '“Better communication creates happier guests.”',
        'closing_quote_author' => 'Guesvia',
        'encouragement' => "Today you practiced\na real-life situation.\nWith more practice, you will feel more confident in speaking English with guests.",
        'progress_note' => "Keep going!\nYou're on the right track.",
        'summary_scenario' => 'Check-in (Guest arrival and registration)',
    ],
];
