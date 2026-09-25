<?php

/*
 * Departments, courses, units and lessons (spec 0003 G.1, G.2).
 *
 * Read by LearningContentSeeder. Media are referenced by their seed-media.json
 * name as `media:<name>` and resolved to media_assets ids by the seeder.
 */

return [

    // G.1: the one-line focus under every catalogue department.
    'department_focus' => [
        'Reception' => 'Guest arrival, greeting and check-in language.',
        'Food Service' => 'Restaurant greetings, orders and service recovery.',
        'Housekeeping' => 'Room status, service requests and apology language.',
        'Spa' => 'Spa welcome, treatment briefing and product upsell.',
        'Kitchen' => 'Back-of-house requests, timing and allergy alerts.',
        'Marketing' => 'Event sales, proposals and guest follow-up.',
        'Technical Services' => 'Maintenance requests and safety language.',
    ],

    // G.2: the main Reception course. Lesson 1 is built in full from
    // lesson-handling-complaints.php; lessons 2–8 get the light block set.
    'main_course' => [
        'slug' => 'guest-service-basics',
        'title' => 'Guest Service Basics',
        'department' => 'Reception',
        'tone' => 'brand',
        'description' => 'Everyday English for welcoming guests, handling requests and solving problems at the front desk.',
        'units' => [
            [
                'title' => 'Welcoming Guests',
                'lessons' => [
                    ['slug' => 'handling-guest-complaints', 'title' => 'Handling Guest Complaints', 'full' => true],
                    [
                        'slug' => 'greeting-guests',
                        'title' => 'Greeting Guests',
                        'introduction' => 'In this lesson, you will learn how to greet guests warmly and make a good first impression.',
                        'situation_quote' => '“A warm greeting is the first step to a happy stay.”',
                        'words' => [
                            ['english_text' => 'welcome', 'part_of_speech' => 'v', 'arabic_meaning' => 'يرحّب', 'simple_explanation' => 'To greet someone in a friendly way when they arrive.', 'hotel_example' => 'Welcome to our hotel. How can I help you?', 'hotel_example_arabic' => 'مرحباً بكم في فندقنا. كيف يمكنني مساعدتك؟', 'image' => 'media:expr-how-can-i-help'],
                            ['english_text' => 'greet', 'part_of_speech' => 'v', 'arabic_meaning' => 'يحيّي', 'simple_explanation' => 'To say hello to someone politely.', 'hotel_example' => 'Always greet the guest with a smile.', 'hotel_example_arabic' => 'حيِّ الضيف دائماً بابتسامة.', 'image' => 'media:practice-response-a'],
                            ['english_text' => 'lobby', 'part_of_speech' => 'n', 'arabic_meaning' => 'بهو الفندق', 'simple_explanation' => 'The entrance hall of a hotel.', 'hotel_example' => 'You can wait in the lobby while we prepare your room.', 'hotel_example_arabic' => 'يمكنك الانتظار في البهو بينما نجهّز غرفتك.', 'image' => 'media:practice-bell-welcome-key'],
                        ],
                        'practice' => [
                            'title' => 'Greeting Guests – Listen & Choose',
                            'audio_text' => 'Welcome',
                            'options' => [
                                ['label' => 'Welcome', 'image' => 'media:expr-how-can-i-help'],
                                ['label' => 'Goodbye', 'image' => 'media:test-check-out'],
                                ['label' => 'Wait', 'image' => 'media:practice-response-b'],
                            ],
                        ],
                    ],
                    [
                        'slug' => 'introducing-yourself',
                        'title' => 'Introducing Yourself',
                        'introduction' => 'In this lesson, you will learn how to introduce yourself and your role to a guest.',
                        'situation_quote' => '“Your name and your smile are the first things a guest remembers.”',
                        'words' => [
                            ['english_text' => 'receptionist', 'part_of_speech' => 'n', 'arabic_meaning' => 'موظف الاستقبال', 'simple_explanation' => 'The person who welcomes guests at the front desk.', 'hotel_example' => 'My name is Samira and I am the receptionist today.', 'hotel_example_arabic' => 'اسمي سميرة وأنا موظفة الاستقبال اليوم.', 'image' => 'media:expr-how-can-i-help'],
                            ['english_text' => 'name badge', 'part_of_speech' => 'n', 'arabic_meaning' => 'شارة الاسم', 'simple_explanation' => 'A small sign with your name that you wear at work.', 'hotel_example' => 'Please wear your name badge at the front desk.', 'hotel_example_arabic' => 'يرجى ارتداء شارة الاسم في مكتب الاستقبال.', 'image' => 'media:expr-let-me-check'],
                            ['english_text' => 'colleague', 'part_of_speech' => 'n', 'arabic_meaning' => 'زميل', 'simple_explanation' => 'A person you work with.', 'hotel_example' => 'My colleague will show you to your room.', 'hotel_example_arabic' => 'سيرافقك زميلي إلى غرفتك.', 'image' => 'media:vocab-repair'],
                        ],
                        'practice' => [
                            'title' => 'Introducing Yourself – Listen & Choose',
                            'audio_text' => 'Name badge',
                            'options' => [
                                ['label' => 'Name badge', 'image' => 'media:expr-let-me-check'],
                                ['label' => 'Room key', 'image' => 'media:practice-room-key'],
                                ['label' => 'Towel', 'image' => 'media:practice-towel'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Check-in Process',
                'lessons' => [
                    [
                        'slug' => 'asking-for-information',
                        'title' => 'Asking for Information',
                        'introduction' => 'In this lesson, you will learn how to ask a guest politely for the details you need at check-in.',
                        'situation_quote' => '“Polite questions get clear answers.”',
                        'words' => [
                            ['english_text' => 'passport', 'part_of_speech' => 'n', 'arabic_meaning' => 'جواز السفر', 'simple_explanation' => 'An official document that shows who you are when you travel.', 'hotel_example' => 'May I see your passport, please?', 'hotel_example_arabic' => 'هل يمكنني رؤية جواز سفرك من فضلك؟', 'image' => 'media:practice-passport'],
                            ['english_text' => 'credit card', 'part_of_speech' => 'n', 'arabic_meaning' => 'بطاقة ائتمان', 'simple_explanation' => 'A card used to pay for things.', 'hotel_example' => 'Could I have a credit card for the deposit?', 'hotel_example_arabic' => 'هل يمكنني الحصول على بطاقة ائتمان للتأمين؟', 'image' => 'media:practice-credit-card'],
                            ['english_text' => 'signature', 'part_of_speech' => 'n', 'arabic_meaning' => 'توقيع', 'simple_explanation' => 'Your name written by hand on a document.', 'hotel_example' => 'I need your signature here, please.', 'hotel_example_arabic' => 'أحتاج إلى توقيعك هنا من فضلك.', 'image' => 'media:expr-reservation'],
                        ],
                        'practice' => [
                            'title' => 'Asking for Information – Listen & Choose',
                            'audio_text' => 'Passport',
                            'options' => [
                                ['label' => 'Passport', 'image' => 'media:practice-passport'],
                                ['label' => 'Credit card', 'image' => 'media:practice-credit-card'],
                                ['label' => 'Pillow', 'image' => 'media:practice-pillow'],
                            ],
                        ],
                    ],
                    [
                        'slug' => 'confirming-a-reservation',
                        'title' => 'Confirming a Reservation',
                        'introduction' => 'In this lesson, you will learn how to find and confirm a guest’s reservation.',
                        'situation_quote' => '“Confirm the details, and the guest can relax.”',
                        'words' => [
                            ['english_text' => 'reservation', 'part_of_speech' => 'n', 'arabic_meaning' => 'حجز', 'simple_explanation' => 'A room that has been booked in advance.', 'hotel_example' => 'I have a reservation under the name Ben Ali.', 'hotel_example_arabic' => 'لديّ حجز باسم بن علي.', 'image' => 'media:expr-reservation'],
                            ['english_text' => 'double room', 'part_of_speech' => 'n', 'arabic_meaning' => 'غرفة مزدوجة', 'simple_explanation' => 'A room with one large bed for two people.', 'hotel_example' => 'You booked a double room for three nights.', 'hotel_example_arabic' => 'لقد حجزت غرفة مزدوجة لثلاث ليالٍ.', 'image' => 'media:practice-pillow'],
                            ['english_text' => 'confirm', 'part_of_speech' => 'v', 'arabic_meaning' => 'يؤكّد', 'simple_explanation' => 'To say that something is correct or certain.', 'hotel_example' => 'Let me confirm your reservation.', 'hotel_example_arabic' => 'دعني أؤكد حجزك.', 'image' => 'media:expr-let-me-check'],
                        ],
                        'practice' => [
                            'title' => 'Confirming a Reservation – Listen & Choose',
                            'audio_text' => 'Reservation',
                            'options' => [
                                ['label' => 'Reservation', 'image' => 'media:expr-reservation'],
                                ['label' => 'Clock', 'image' => 'media:expr-room-ready'],
                                ['label' => 'Map', 'image' => 'media:expr-show-the-way'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'During the Stay',
                'lessons' => [
                    [
                        'slug' => 'room-requests',
                        'title' => 'Room Requests',
                        'introduction' => 'In this lesson, you will learn how to respond to requests for extra items and services.',
                        'situation_quote' => '“Small requests, handled well, make a big difference.”',
                        'words' => [
                            ['english_text' => 'towel', 'part_of_speech' => 'n', 'arabic_meaning' => 'منشفة', 'simple_explanation' => 'A soft cloth used to dry yourself.', 'hotel_example' => 'Could we have two extra towels, please?', 'hotel_example_arabic' => 'هل يمكننا الحصول على منشفتين إضافيتين من فضلك؟', 'image' => 'media:practice-towel'],
                            ['english_text' => 'pillow', 'part_of_speech' => 'n', 'arabic_meaning' => 'وسادة', 'simple_explanation' => 'A soft cushion you rest your head on in bed.', 'hotel_example' => 'I will send an extra pillow to your room.', 'hotel_example_arabic' => 'سأرسل وسادة إضافية إلى غرفتك.', 'image' => 'media:practice-pillow'],
                            ['english_text' => 'hair dryer', 'part_of_speech' => 'n', 'arabic_meaning' => 'مجفف شعر', 'simple_explanation' => 'A machine that blows hot air to dry your hair.', 'hotel_example' => 'There is a hair dryer in the bathroom.', 'hotel_example_arabic' => 'يوجد مجفف شعر في الحمام.', 'image' => 'media:practice-hair-dryer'],
                        ],
                        'practice' => [
                            'title' => 'Room Requests – Listen & Choose',
                            'audio_text' => 'Hair dryer',
                            'options' => [
                                ['label' => 'Hair dryer', 'image' => 'media:practice-hair-dryer'],
                                ['label' => 'Slippers', 'image' => 'media:practice-slippers'],
                                ['label' => 'Toiletries', 'image' => 'media:practice-toiletries'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Check-out',
                'lessons' => [
                    [
                        'slug' => 'settling-the-bill',
                        'title' => 'Settling the Bill',
                        'introduction' => 'In this lesson, you will learn how to check a guest out and settle the bill politely.',
                        'situation_quote' => '“A smooth check-out is the last impression a guest takes home.”',
                        'words' => [
                            ['english_text' => 'bill', 'part_of_speech' => 'n', 'arabic_meaning' => 'فاتورة', 'simple_explanation' => 'A list of what the guest must pay.', 'hotel_example' => 'Here is your bill. Would you like to check it?', 'hotel_example_arabic' => 'هذه فاتورتك. هل تودّ مراجعتها؟', 'image' => 'media:test-check-out'],
                            ['english_text' => 'receipt', 'part_of_speech' => 'n', 'arabic_meaning' => 'إيصال', 'simple_explanation' => 'A paper that shows you have paid.', 'hotel_example' => 'Would you like a printed receipt?', 'hotel_example_arabic' => 'هل تودّ إيصالاً مطبوعاً؟', 'image' => 'media:practice-credit-card'],
                            ['english_text' => 'check out', 'part_of_speech' => 'v', 'arabic_meaning' => 'يغادر الفندق', 'simple_explanation' => 'To leave the hotel and pay at the end of your stay.', 'hotel_example' => 'What time would you like to check out?', 'hotel_example_arabic' => 'في أي وقت تودّ المغادرة؟', 'image' => 'media:test-check-out'],
                        ],
                        'practice' => [
                            'title' => 'Settling the Bill – Listen & Choose',
                            'audio_text' => 'Credit card',
                            'options' => [
                                ['label' => 'Credit card', 'image' => 'media:practice-credit-card'],
                                ['label' => 'Passport', 'image' => 'media:practice-passport'],
                                ['label' => 'Room key', 'image' => 'media:practice-room-key'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Handling Complaints',
                'lessons' => [
                    [
                        'slug' => 'apologising-professionally',
                        'title' => 'Apologising Professionally',
                        'introduction' => 'In this lesson, you will learn how to apologise sincerely and offer a solution.',
                        'situation_quote' => '“A sincere apology opens the door to a solution.”',
                        'words' => [
                            ['english_text' => 'apologise', 'part_of_speech' => 'v', 'arabic_meaning' => 'يعتذر', 'simple_explanation' => 'To say sorry for a problem.', 'hotel_example' => 'I apologise for the delay.', 'hotel_example_arabic' => 'أعتذر عن التأخير.', 'image' => 'media:expr-sorry-inconvenience'],
                            ['english_text' => 'inconvenience', 'part_of_speech' => 'n', 'arabic_meaning' => 'إزعاج', 'simple_explanation' => 'A problem that causes trouble for someone.', 'hotel_example' => 'I am sorry for the inconvenience.', 'hotel_example_arabic' => 'أنا آسف على الإزعاج.', 'image' => 'media:situation-complaint'],
                            ['english_text' => 'solution', 'part_of_speech' => 'n', 'arabic_meaning' => 'حل', 'simple_explanation' => 'A way to fix a problem.', 'hotel_example' => 'Let me find a solution for you right away.', 'hotel_example_arabic' => 'دعني أجد لك حلاً على الفور.', 'image' => 'media:vocab-repair'],
                        ],
                        'practice' => [
                            'title' => 'Apologising Professionally – Listen & Choose',
                            'audio_text' => 'Maintenance',
                            'options' => [
                                ['label' => 'Maintenance', 'image' => 'media:vocab-maintenance'],
                                ['label' => 'Temperature', 'image' => 'media:vocab-temperature'],
                                ['label' => 'Broken', 'image' => 'media:vocab-broken'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],

    // G.2: the other courses the admin tree shows, one unit and two lessons of
    // two blocks (situation + complete) each.
    'other_courses' => [
        [
            'slug' => 'restaurant-communication',
            'title' => 'Restaurant Communication',
            'department' => 'Food Service',
            'tone' => 'aqua',
            'description' => 'Greeting diners, taking orders and recovering from mistakes at the table.',
            'unit' => 'At the Table',
            'lessons' => [
                ['slug' => 'greeting-diners', 'title' => 'Greeting Diners', 'introduction' => 'In this lesson, you will learn how to welcome guests to the restaurant and seat them.'],
                ['slug' => 'taking-an-order', 'title' => 'Taking an Order', 'introduction' => 'In this lesson, you will learn how to take a food and drink order clearly.'],
            ],
        ],
        [
            'slug' => 'housekeeping-essentials',
            'title' => 'Housekeeping Essentials',
            'department' => 'Housekeeping',
            'tone' => 'success',
            'description' => 'Room status, service requests and apology language for housekeeping staff.',
            'unit' => 'In the Room',
            'lessons' => [
                ['slug' => 'entering-a-room', 'title' => 'Entering a Room', 'introduction' => 'In this lesson, you will learn what to say before entering a guest room.'],
                ['slug' => 'extra-items', 'title' => 'Extra Items', 'introduction' => 'In this lesson, you will learn how to respond to requests for extra items.'],
            ],
        ],
        [
            'slug' => 'spa-and-wellness',
            'title' => 'Spa and Wellness',
            'department' => 'Spa',
            'tone' => 'warning',
            'description' => 'Welcoming spa guests, explaining treatments and booking appointments.',
            'unit' => 'Spa Reception',
            'lessons' => [
                ['slug' => 'welcoming-spa-guests', 'title' => 'Welcoming Spa Guests', 'introduction' => 'In this lesson, you will learn how to welcome a guest to the spa.'],
                ['slug' => 'explaining-treatments', 'title' => 'Explaining Treatments', 'introduction' => 'In this lesson, you will learn how to describe treatments simply and clearly.'],
            ],
        ],
        [
            'slug' => 'kitchen-communication',
            'title' => 'Kitchen Communication',
            'department' => 'Kitchen',
            'tone' => 'gold',
            'description' => 'Back-of-house requests, timing and allergy alerts.',
            'unit' => 'On the Line',
            'lessons' => [
                ['slug' => 'timing-and-orders', 'title' => 'Timing and Orders', 'introduction' => 'In this lesson, you will learn the words used to time and call orders.'],
                ['slug' => 'allergy-alerts', 'title' => 'Allergy Alerts', 'introduction' => 'In this lesson, you will learn how to ask about and flag food allergies.'],
            ],
        ],
        [
            'slug' => 'telephone-english',
            'title' => 'Telephone English',
            'department' => 'Reception',
            'tone' => 'danger',
            'description' => 'Answering the phone, taking messages and confirming bookings by phone.',
            'unit' => 'On the Phone',
            'lessons' => [
                ['slug' => 'answering-the-phone', 'title' => 'Answering the Phone', 'introduction' => 'In this lesson, you will learn how to answer the hotel phone professionally.'],
                ['slug' => 'taking-a-message', 'title' => 'Taking a Message', 'introduction' => 'In this lesson, you will learn how to take and repeat a message accurately.'],
            ],
        ],
    ],
];
