<?php

/*
 * The Reception Pre-test and Post-test (spec 0003 G.4, payload contracts B.9).
 *
 * Pre-test questions 8, 12, 15, 18, 21, 23, 24 and 25 are transcribed from
 * desginphotos/employ/photo_21 … photo_28; the intro from photo_20. The other
 * seventeen are plausible reception questions of the same types, and the
 * Post-test mirrors the Pre-test position by position with different content.
 *
 * Each question is one activity with one item. `prompt` is the instruction
 * strip, `skill_label` the chip, `side_note` (extra key on the payload) the
 * card at the right of the question. `media:<name>` is resolved by the seeder.
 */

$mc = static function (
    string $skill,
    string $instruction,
    string $question,
    array $options,
    string $correct,
    ?string $image = null,
    string $layout = 'side',
    ?string $subtitle = null,
    ?array $passage = null,
    string $sideNote = "Choose the response you think is most appropriate.\nThere is no feedback during the test.",
): array {
    $letters = ['A', 'B', 'C', 'D'];

    return [
        'type' => 'multiple_choice',
        'skill_label' => $skill,
        'prompt' => $instruction,
        'payload' => [
            'side_note' => $sideNote,
            'items' => [[
                'id' => 'i1',
                'question' => $question,
                'subtitle' => $subtitle,
                'image' => $image,
                'passage' => $passage,
                'layout' => $layout,
                'options' => array_map(
                    static fn (int $i, string $text): array => ['id' => $letters[$i], 'text' => $text],
                    array_keys($options),
                    $options,
                ),
                'correct' => $correct,
            ]],
        ],
    ];
};

$fill = static function (
    string $instruction,
    string $sentence,
    string $audioText,
    array $options,
    string $correct,
    ?string $image = null,
    string $sideNote = "This sentence is from a real situation at the reception desk.\nChoose the best answer.",
): array {
    $letters = ['A', 'B', 'C', 'D'];

    return [
        'type' => 'words_sentences',
        'skill_label' => 'Vocabulary in Context',
        'prompt' => $instruction,
        'payload' => [
            'side_note' => $sideNote,
            'items' => [[
                'id' => 'i1',
                'question' => 'Complete the sentence.',
                'sentence' => $sentence,
                'audio_text' => $audioText,
                'image' => $image,
                'options' => array_map(
                    static fn (int $i, string $label): array => ['id' => $letters[$i], 'label' => $label, 'image' => null],
                    array_keys($options),
                    $options,
                ),
                'correct' => $correct,
            ]],
        ],
    ];
};

$speak = static function (string $situation, ?string $image = null): array {
    return [
        'type' => 'speaking',
        'skill_label' => 'Speaking',
        'prompt' => 'Speak your answer. You have 20 seconds to record.',
        'payload' => [
            'side_note' => "Give a short and natural response.\nThere is no single correct answer.\nDo your best!",
            'record_hint' => 'Click the microphone to start recording.',
            'play_label' => 'Play My Recording',
            'items' => [[
                'id' => 'i1',
                'question' => 'What would you say in this situation?',
                'situation' => $situation,
                'instruction' => 'Record a short and polite response.',
                'image' => $image,
                'max_seconds' => 20,
            ]],
        ],
    ];
};

$order = static function (string $context, array $captions, array $images): array {
    $letters = ['A', 'B', 'C', 'D'];

    return [
        'type' => 'picture_order',
        'skill_label' => 'Ordering a Conversation',
        'prompt' => 'Look at the pictures and put the conversation in the correct order.',
        'payload' => [
            'side_note' => "Look carefully at the pictures and read the sentences.\nThen drag them to the correct order.",
            'drag_hint' => 'Drag the pictures to put them in the right order (1 → 4).',
            'items' => [[
                'id' => 'i1',
                'question' => 'Put the conversation in the correct order.',
                'context' => $context,
                'cards' => array_map(
                    static fn (int $i, string $caption): array => ['id' => $letters[$i], 'image' => $images[$i], 'caption' => $caption],
                    array_keys($captions),
                    $captions,
                ),
                'order' => ['A', 'B', 'C', 'D'],
            ]],
        ],
    ];
};

$readingNote = "This is a real type of text you may see at work.\nChoose the best answer.";
$messageNote = "This is a real type of message you may receive at work.\nChoose the best answer.";
$situationNote = "Choose the most appropriate response for the situation.\nThink about what you would say at work.";
$lastNote = "This is the last question.\nGive your best answer and click\n“Finish Test” to complete the pre-test.";
$lastNotePost = "This is the last question.\nGive your best answer and click\n“Finish Test” to complete the post-test.";

$settings = [
    'time_limit_seconds' => 1200,
    'shuffle_questions' => false,
    'results_visibility' => 'score',
    'pass_score' => null,
    'on_timeout' => 'submit',
];

return [
    'pre' => [
        'title' => 'Reception Pre-test',
        'settings' => $settings,
        // photo_20
        'intro' => [
            'eyebrow' => 'WELCOME TO GHASIDO',
            'heading' => "Let's start with a short Pre-test",
            'paragraphs' => [
                'The pre-test helps us understand your current level of English. It is not a pass or fail test.',
                'Your answers will be used to give you the most relevant lessons and to see your progress later.',
            ],
            'facts' => [
                ['icon' => 'clock', 'label' => 'Time', 'text' => 'About 15–20 minutes'],
                ['icon' => 'list', 'label' => 'Question types', 'text' => 'Listening, reading, vocabulary and real-life situations'],
                ['icon' => 'target', 'label' => 'Number of questions', 'text' => 'Around 25 questions'],
                ['icon' => 'lock', 'label' => 'No feedback', 'text' => 'You will not see the correct answers during the test.'],
                ['icon' => 'chart', 'label' => 'Your results', 'text' => 'Your results are saved automatically and used only for your learning progress.'],
            ],
            'good_to_know_title' => 'Good to know',
            'good_to_know' => [
                'The test is short and practical.',
                'It includes real situations from hotel work.',
                'Take your time and do your best.',
                'You can use headphones for listening questions.',
                'If you have any problem, contact your supervisor.',
            ],
            'remember' => ['title' => 'Remember!', 'text' => 'This is the first step on your learning journey. Every step counts!'],
            'primary' => 'Start Pre-test',
            'secondary' => "I'll do it later",
            'script' => '“A small test today, a brighter you tomorrow!”',
        ],
        'questions' => [
            // 1
            $mc('Situation', 'Look at the situation and choose the best response.', 'A guest walks up to the reception desk and smiles at you. What would you say?', ['Good morning! Welcome. How can I help you?', 'What do you want?', 'The lift is over there.', 'We are closed.'], 'A', 'media:practice-guest-asking'),
            // 2
            $mc('Reading', 'Read the sign and choose the correct answer.', 'You see the sign "Reception" next to a bell. What is this place for?', ['It is where guests check in and ask for help.', 'It is the restaurant.', 'It is the swimming pool.', 'It is the car park.'], 'A', 'media:test-reception-sign', 'side', null, null, $readingNote),
            // 3
            $fill('Choose the correct word to complete the sentence.', 'Welcome to our ____. Do you have a reservation?', 'Welcome to our hotel. Do you have a reservation?', ['kitchen', 'hotel', 'car', 'garden'], 'B'),
            // 4
            $mc('Situation', 'Look at the situation and choose the best response.', 'A guest asks: "What time is breakfast?" What would you say?', ['I do not know.', 'Breakfast is served from 7 a.m. to 10 a.m.', 'Breakfast is not important.', 'Please ask the manager.'], 'B', 'media:test-order-a'),
            // 5
            $mc('Reading for Meaning', "Read the guest's message and choose the best answer.", 'What does the guest want?', ['She wants to cancel her stay.', 'She wants an extra pillow.', 'She wants a wake-up call.', 'She wants a taxi.'], 'B', null, 'grid', null, ['kind' => 'email', 'from' => 'maria.lopez@email.com', 'to' => 'hotel@guesvia.com', 'subject' => 'Extra pillow', 'body' => "Hello,\nCould you please bring one more pillow to room 302?\nThank you very much.\nMaria Lopez"], $messageNote),
            // 6
            $fill('Choose the correct word to complete the sentence.', 'Here is your ____. Your room is on the third floor.', 'Here is your key. Your room is on the third floor.', ['key', 'kitchen', 'coffee', 'car'], 'A'),
            // 7
            $mc('Real-life Situation', 'Look at the situation and choose the best response.', 'A guest says the air conditioning in her room is not working. What would you say?', ['That is not my problem.', 'You can open the window.', "I'm sorry for the inconvenience. I will call maintenance right away.", 'Please come back tomorrow.'], 'C', 'media:situation-complaint', 'side', 'What would you say?', null, $situationNote),
            // 8 — photo_21
            $mc('Situation', 'Look at the situation and choose the best response.', 'A guest is at the reception desk with a large suitcase. What would you say?', ['Good evening! Can I help you with your luggage?', 'The restaurant is over there.', 'Your room is on the first floor.', 'Please wait outside.'], 'A', 'media:test-suitcase-guest'),
            // 9
            $mc('Reading', 'Read the sign and choose the correct answer.', 'A notice at the pool says "No lifeguard on duty. Swim at your own risk." What does it mean?', ['The pool is closed.', 'There is nobody watching the pool, so be careful.', 'Children only.', 'Swimming is free today.'], 'B', null, 'grid', null, null, $readingNote),
            // 10
            $fill('Choose the correct word to complete the sentence.', 'Would you ____ to make a reservation?', 'Would you like to make a reservation?', ['like', 'liked', 'liking', 'likes'], 'A'),
            // 11
            $speak('A guest arrives at the desk and says he has a reservation.', 'media:dialogue-checkin'),
            // 12 — photo_22
            $mc('Reading', 'Read the sign and choose the correct answer.', 'What does this sign mean?', ['You can smoke here.', 'Smoking is not allowed in this area.', 'There is a smoking area in the hotel.', 'You must keep the windows open.'], 'B', 'media:test-no-smoking', 'side', null, null, $readingNote),
            // 13
            $mc('Situation', 'Look at the situation and choose the best response.', 'A guest asks where the spa is. What would you say?', ['It is on the second floor. I can show you the way.', 'I am busy.', 'Look for it yourself.', 'The spa is expensive.'], 'A', 'media:scenario-spa'),
            // 14
            $mc('Reading for Meaning', "Read the guest's message and choose the best answer.", 'What does the guest want?', ['He wants to check out later than 12.', 'He wants breakfast in his room.', 'He wants to change his room.', 'He wants to cancel his booking.'], 'A', null, 'grid', null, ['kind' => 'email', 'from' => 'ahmed.k@email.com', 'to' => 'hotel@guesvia.com', 'subject' => 'Late check-out', 'body' => "Dear Reception,\nMy flight is in the evening. Is it possible to check out at 2 p.m. instead of 12?\nBest regards,\nAhmed K."], $messageNote),
            // 15 — photo_23
            $fill('Choose the correct word to complete the sentence.', 'May I ____ your passport, please?', 'May I see your passport, please?', ['see', 'seeing', 'saw', 'sees'], 'A', 'media:test-reception-sign'),
            // 16
            $mc('Real-life Situation', 'Look at the situation and choose the best response.', 'A guest says: "I lost my room key." What would you say?', ['That is your problem.', "Don't worry. I will give you a new key right away.", 'You must pay for the whole room.', 'Please leave the hotel.'], 'B', 'media:practice-bell-welcome-key', 'side', 'What would you say?', null, $situationNote),
            // 17
            $fill('Choose the correct word to complete the sentence.', 'The room will be ____ at 3 p.m.', 'The room will be ready at 3 p.m.', ['ready', 'red', 'read', 'reading'], 'A'),
            // 18 — photo_24
            $speak('A guest is not happy because the room is not clean.', 'media:test-room-not-clean'),
            // 19
            $mc('Reading', 'Read the sign and choose the correct answer.', 'A sign in the lift says "Maximum 8 persons". What does it mean?', ['Only 8 people can use the lift at the same time.', 'The lift goes to the 8th floor.', 'The lift is closed.', 'Children cannot use the lift.'], 'A', null, 'grid', null, null, $readingNote),
            // 20
            $mc('Situation', 'Look at the situation and choose the best response.', 'A guest asks for two extra towels. What would you say?', ['Of course. I will send them to your room right away.', 'We have no towels.', 'Why do you need them?', 'Towels are extra.'], 'A', 'media:practice-towel'),
            // 21 — photo_25
            $mc('Reading for Meaning', "Read the guest's message and choose the best answer.", 'What does the guest want?', ['He wants to cancel his reservation.', 'He wants to arrive earlier.', 'He wants to keep his reservation.', 'He wants a wake-up call.'], 'C', null, 'grid', null, ['kind' => 'email', 'from' => 'john.smith@email.com', 'to' => 'hotel@guesvia.com', 'subject' => 'Late arrival', 'body' => "Dear Team,\nI will arrive at your hotel around 11 p.m. today.\nCould you please keep my reservation?\nThank you!\nJohn Smith"], $messageNote),
            // 22
            $fill('Choose the correct word to complete the sentence.', "I'm sorry for the ____. I will fix this right away.", "I'm sorry for the inconvenience. I will fix this right away.", ['inconvenience', 'invitation', 'information', 'invoice'], 'A'),
            // 23 — photo_26
            $order('A guest is asking for information about breakfast.', ['What time is breakfast served?', 'It is served from 7 a.m. to 10 a.m.', 'Great, thank you!', "You're welcome!"], ['media:test-order-a', 'media:test-order-b', 'media:test-order-c', 'media:test-order-d']),
            // 24 — photo_27
            $mc('Real-life Situation', 'Look at the situation and choose the best response.', 'A guest wants to check out.', ['Welcome to our hotel.', 'Would you like to check in now?', 'Here is your room key.', 'Of course. May I have your room number, please?'], 'D', 'media:test-check-out', 'side', 'What would you say?', null, $situationNote),
            // 25 — photo_28
            $mc('Reading for Detail', 'Read the text and choose the best answer.', 'Read the notice and answer the question.', ['Monday, 14 July.', 'Wednesday, 16 July.', 'Tuesday, 15 July.', 'Friday, 18 July.'], 'C', null, 'grid', null, ['kind' => 'notice', 'image' => 'media:test-spa-notice', 'question_below' => 'When is the spa closed?'], $lastNote),
        ],
    ],

    'post' => [
        'title' => 'Reception Post-test',
        'settings' => $settings,
        'intro' => [
            'eyebrow' => 'WELL DONE',
            'heading' => "Let's finish with a short Post-test",
            'paragraphs' => [
                'The post-test shows how much your English has improved since the pre-test. It is not a pass or fail test.',
                'Your answers will be compared with your pre-test to measure your progress and unlock your certificate.',
            ],
            'facts' => [
                ['icon' => 'clock', 'label' => 'Time', 'text' => 'About 15–20 minutes'],
                ['icon' => 'list', 'label' => 'Question types', 'text' => 'Listening, reading, vocabulary and real-life situations'],
                ['icon' => 'target', 'label' => 'Number of questions', 'text' => 'Around 25 questions'],
                ['icon' => 'lock', 'label' => 'No feedback', 'text' => 'You will not see the correct answers during the test.'],
                ['icon' => 'chart', 'label' => 'Your results', 'text' => 'Your results are saved automatically and used only for your learning progress.'],
            ],
            'good_to_know_title' => 'Good to know',
            'good_to_know' => [
                'The test is short and practical.',
                'It includes real situations from hotel work.',
                'Take your time and do your best.',
                'You can use headphones for listening questions.',
                'If you have any problem, contact your supervisor.',
            ],
            'remember' => ['title' => 'Remember!', 'text' => 'This is the last step of your training. Show what you have learned!'],
            'primary' => 'Start Post-test',
            'secondary' => "I'll do it later",
            'script' => '“Every conversation is a chance to shine!”',
        ],
        'questions' => [
            // 1
            $mc('Situation', 'Look at the situation and choose the best response.', 'A family arrives at the desk late in the evening. What would you say?', ['Good evening! Welcome. How can I help you?', 'We close at night.', 'Come back in the morning.', 'Where are your bags?'], 'A', 'media:practice-guest-asking'),
            // 2
            $mc('Reading', 'Read the sign and choose the correct answer.', 'A sign on a door says "Staff only". What does it mean?', ['Guests may not enter.', 'It is the guest lounge.', 'It is the restaurant entrance.', 'Everyone is welcome.'], 'A', null, 'grid', null, null, $readingNote),
            // 3
            $fill('Choose the correct word to complete the sentence.', 'Good morning. How can I ____ you?', 'Good morning. How can I help you?', ['helps', 'help', 'helping', 'helped'], 'B'),
            // 4
            $mc('Situation', 'Look at the situation and choose the best response.', 'A guest asks: "Is the Wi-Fi free?" What would you say?', ['Yes, it is free. The password is on your key card.', 'I do not use the internet.', 'Ask the other guests.', 'The Wi-Fi is broken forever.'], 'A', 'media:test-order-a'),
            // 5
            $mc('Reading for Meaning', "Read the guest's message and choose the best answer.", 'What does the guest want?', ['He wants a room with a sea view.', 'He wants to cancel his booking.', 'He wants a bigger bed.', 'He wants to pay in cash.'], 'A', null, 'grid', null, ['kind' => 'email', 'from' => 'paul.martin@email.com', 'to' => 'hotel@guesvia.com', 'subject' => 'Room request', 'body' => "Hello,\nIf possible, I would like a room with a sea view for my stay next week.\nKind regards,\nPaul Martin"], $messageNote),
            // 6
            $fill('Choose the correct word to complete the sentence.', 'Please sign here and I will give you your ____ card.', 'Please sign here and I will give you your key card.', ['key', 'kitchen', 'coffee', 'credit'], 'A'),
            // 7
            $mc('Real-life Situation', 'Look at the situation and choose the best response.', 'A guest says the shower has no hot water. What would you say?', ['Cold water is healthy.', 'I am sorry about that. I will send someone to check it immediately.', 'Try again later.', 'That happens every day.'], 'B', 'media:situation-complaint', 'side', 'What would you say?', null, $situationNote),
            // 8
            $mc('Situation', 'Look at the situation and choose the best response.', 'A guest is carrying two heavy bags and looks tired. What would you say?', ['Let me help you with your bags.', 'The stairs are over there.', 'You look tired.', 'Please hurry up.'], 'A', 'media:test-suitcase-guest'),
            // 9
            $mc('Reading', 'Read the sign and choose the correct answer.', 'A notice says "Breakfast: 6:30–10:30, Restaurant, ground floor". Where is breakfast served?', ['In the restaurant on the ground floor.', 'In your room.', 'By the pool.', 'In the lobby.'], 'A', null, 'grid', null, null, $readingNote),
            // 10
            $fill('Choose the correct word to complete the sentence.', 'Let me ____ that for you.', 'Let me check that for you.', ['check', 'checks', 'checking', 'checked'], 'A'),
            // 11
            $speak('A guest asks you to recommend a good restaurant nearby.', 'media:dialogue-checkin'),
            // 12
            $mc('Reading', 'Read the sign and choose the correct answer.', 'A sign in the corridor says "Quiet please – guests are resting". What does it mean?', ['You may play music.', 'Please do not make noise.', 'The rooms are empty.', 'The corridor is closed.'], 'B', 'media:test-no-smoking', 'side', null, null, $readingNote),
            // 13
            $mc('Situation', 'Look at the situation and choose the best response.', 'A guest asks how to get to the airport. What would you say?', ['I can book a taxi for you. It takes about 30 minutes.', 'The airport is far.', 'Take any bus.', 'I have never been there.'], 'A', 'media:scenario-information'),
            // 14
            $mc('Reading for Meaning', "Read the guest's message and choose the best answer.", 'What does the guest want?', ['She wants a wake-up call at 6 a.m.', 'She wants to sleep late.', 'She wants breakfast at 6 a.m.', 'She wants to change her flight.'], 'A', null, 'grid', null, ['kind' => 'email', 'from' => 'lina.b@email.com', 'to' => 'hotel@guesvia.com', 'subject' => 'Wake-up call', 'body' => "Good evening,\nI have an early flight tomorrow. Could you call my room at 6 a.m., please?\nThank you,\nLina B."], $messageNote),
            // 15
            $fill('Choose the correct word to complete the sentence.', 'Could I ____ your credit card, please?', 'Could I have your credit card, please?', ['have', 'having', 'had', 'has'], 'A', 'media:test-reception-sign'),
            // 16
            $mc('Real-life Situation', 'Look at the situation and choose the best response.', 'A guest says: "My room is too noisy." What would you say?', ['Buy earplugs.', 'I am sorry to hear that. Let me see if I can move you to a quieter room.', 'All rooms are noisy.', 'Noise is normal in a hotel.'], 'B', 'media:practice-bell-welcome-key', 'side', 'What would you say?', null, $situationNote),
            // 17
            $fill('Choose the correct word to complete the sentence.', 'Your room is on the fifth ____.', 'Your room is on the fifth floor.', ['floor', 'flower', 'flour', 'door'], 'A'),
            // 18
            $speak('A guest is upset because his taxi did not arrive on time.', 'media:test-room-not-clean'),
            // 19
            $mc('Reading', 'Read the sign and choose the correct answer.', 'A sign says "Check-out time: 12:00 noon". What does it mean?', ['Guests must leave their rooms by 12:00.', 'Guests may arrive at 12:00.', 'The hotel closes at 12:00.', 'Lunch is at 12:00.'], 'A', null, 'grid', null, null, $readingNote),
            // 20
            $mc('Situation', 'Look at the situation and choose the best response.', 'A guest asks for an extra pillow. What would you say?', ['Certainly. Housekeeping will bring one right away.', 'One pillow is enough.', 'Ask housekeeping yourself.', 'We do not have pillows.'], 'A', 'media:practice-pillow'),
            // 21
            $mc('Reading for Meaning', "Read the guest's message and choose the best answer.", 'What does the guest want?', ['He wants to book a table for dinner.', 'He wants to cancel dinner.', 'He wants breakfast in his room.', 'He wants the menu by email.'], 'A', null, 'grid', null, ['kind' => 'email', 'from' => 'omar.r@email.com', 'to' => 'hotel@guesvia.com', 'subject' => 'Dinner tonight', 'body' => "Hi,\nCould you reserve a table for two at your restaurant tonight at 8 p.m.?\nThanks,\nOmar R."], $messageNote),
            // 22
            $fill('Choose the correct word to complete the sentence.', 'I can ____ you the way to the restaurant.', 'I can show you the way to the restaurant.', ['show', 'shows', 'showing', 'showed'], 'A'),
            // 23
            $order('A guest is checking out at the reception desk.', ['I would like to check out, please.', 'Of course. Here is your bill.', 'Thank you. Here is my card.', 'Thank you for staying with us!'], ['media:test-order-a', 'media:test-order-b', 'media:test-order-c', 'media:test-order-d']),
            // 24
            $mc('Real-life Situation', 'Look at the situation and choose the best response.', 'A guest arrives with a reservation.', ['Welcome! May I have your name, please?', 'Please wait outside.', 'We are full tonight.', 'Here is your bill.'], 'A', 'media:test-check-out', 'side', 'What would you say?', null, $situationNote),
            // 25
            $mc('Reading for Detail', 'Read the text and choose the best answer.', 'Read the notice and answer the question.', ['Because of a private event.', 'Because of maintenance.', 'Because of the weather.', 'Because of a holiday.'], 'B', null, 'grid', null, ['kind' => 'notice', 'image' => 'media:test-spa-notice', 'question_below' => 'Why is the spa closed?'], $lastNotePost),
        ],
    ],
];
