<?php

/*
 * The seven practice activities of "Handling Guest Complaints" (spec 0003
 * G.3, payload contracts B.9), in the hub's order. Copy is transcribed from
 * desginphotos/employ/photo_7 … photo_14.
 *
 * Every payload is `{items: [...]}` per B.9. The extra top-level keys
 * (`heading`, `task`, `tip`, `side_image`) carry the side-panel copy the
 * mockups show around the items; a shaper that does not know them ignores
 * them. `media:<name>` is resolved to a media_assets id by the seeder.
 */

return [

    // 1. Listen & Choose (photo_8). Item 1 is the mockup.
    [
        'key' => 'hgc-listen-choose',
        'type' => 'listen_choose',
        'title' => 'Listen & Choose',
        'skill_label' => 'Listening',
        'prompt' => 'Listen to the word or sentence and choose the correct picture.',
        'payload' => [
            'side_image' => 'media:practice-bell-good-service',
            'tip' => 'Listen carefully and look at the pictures. You can play the audio as many times as you need.',
            'items' => [
                ['id' => 'i1', 'audio_text' => 'Towel', 'options' => [
                    ['id' => 'a', 'label' => 'Towel', 'image' => 'media:practice-towel'],
                    ['id' => 'b', 'label' => 'Pillow', 'image' => 'media:practice-pillow'],
                    ['id' => 'c', 'label' => 'Room key', 'image' => 'media:practice-room-key'],
                ], 'correct' => 'a'],
                ['id' => 'i2', 'audio_text' => 'Hair dryer', 'options' => [
                    ['id' => 'a', 'label' => 'Slippers', 'image' => 'media:practice-slippers'],
                    ['id' => 'b', 'label' => 'Hair dryer', 'image' => 'media:practice-hair-dryer'],
                    ['id' => 'c', 'label' => 'Toiletries', 'image' => 'media:practice-toiletries'],
                ], 'correct' => 'b'],
                ['id' => 'i3', 'audio_text' => 'Passport', 'options' => [
                    ['id' => 'a', 'label' => 'Credit card', 'image' => 'media:practice-credit-card'],
                    ['id' => 'b', 'label' => 'Room key', 'image' => 'media:practice-room-key'],
                    ['id' => 'c', 'label' => 'Passport', 'image' => 'media:practice-passport'],
                ], 'correct' => 'c'],
                ['id' => 'i4', 'audio_text' => 'Air conditioning', 'options' => [
                    ['id' => 'a', 'label' => 'Air conditioning', 'image' => 'media:vocab-air-conditioning'],
                    ['id' => 'b', 'label' => 'Thermometer', 'image' => 'media:vocab-temperature'],
                    ['id' => 'c', 'label' => 'Tools', 'image' => 'media:vocab-maintenance'],
                ], 'correct' => 'a'],
                ['id' => 'i5', 'audio_text' => 'Slippers', 'options' => [
                    ['id' => 'a', 'label' => 'Pillow', 'image' => 'media:practice-pillow'],
                    ['id' => 'b', 'label' => 'Slippers', 'image' => 'media:practice-slippers'],
                    ['id' => 'c', 'label' => 'Towel', 'image' => 'media:practice-towel'],
                ], 'correct' => 'b'],
            ],
        ],
    ],

    // 2. Look & Listen (photo_9). Item 2 is the mockup (the room key photo).
    [
        'key' => 'hgc-look-listen',
        'type' => 'look_listen',
        'title' => 'Look & Listen',
        'skill_label' => 'Listening',
        'prompt' => 'Look at the picture and choose the correct audio.',
        'payload' => [
            'side_image' => 'media:practice-bell-welcome-key',
            'heading' => 'Which audio matches the picture?',
            'tip' => 'Look carefully at the picture and listen to all the audios before you choose.',
            'items' => [
                ['id' => 'i1', 'image' => 'media:practice-towel', 'options' => [
                    ['id' => 'a', 'audio_text' => 'Pillow'],
                    ['id' => 'b', 'audio_text' => 'Towel'],
                    ['id' => 'c', 'audio_text' => 'Hair dryer'],
                ], 'correct' => 'b'],
                ['id' => 'i2', 'image' => 'media:practice-room-key', 'options' => [
                    ['id' => 'a', 'audio_text' => 'Room key'],
                    ['id' => 'b', 'audio_text' => 'Towel'],
                    ['id' => 'c', 'audio_text' => 'Slippers'],
                ], 'correct' => 'a'],
                ['id' => 'i3', 'image' => 'media:practice-passport', 'options' => [
                    ['id' => 'a', 'audio_text' => 'Credit card'],
                    ['id' => 'b', 'audio_text' => 'Signature'],
                    ['id' => 'c', 'audio_text' => 'Passport'],
                ], 'correct' => 'c'],
                ['id' => 'i4', 'image' => 'media:practice-toiletries', 'options' => [
                    ['id' => 'a', 'audio_text' => 'Toiletries'],
                    ['id' => 'b', 'audio_text' => 'Slippers'],
                    ['id' => 'c', 'audio_text' => 'Pillow'],
                ], 'correct' => 'a'],
                ['id' => 'i5', 'image' => 'media:vocab-air-conditioning', 'options' => [
                    ['id' => 'a', 'audio_text' => 'Temperature'],
                    ['id' => 'b', 'audio_text' => 'Air conditioning'],
                    ['id' => 'c', 'audio_text' => 'Maintenance'],
                ], 'correct' => 'b'],
            ],
        ],
    ],

    // 3. Best Response (photo_10). Item 2 is the mockup.
    [
        'key' => 'hgc-best-response',
        'type' => 'best_response',
        'title' => 'Best Response',
        'skill_label' => 'Speaking',
        'prompt' => 'Listen to the guest and choose the most appropriate response.',
        'payload' => [
            'side_image' => 'media:practice-guest-asking',
            'heading' => "Listen to the guest.\nWhat is the best response?",
            'tip' => 'Listen carefully. Think about a polite and helpful response.',
            'items' => [
                ['id' => 'i1', 'situation' => 'A guest arrives at the reception desk.', 'guest_audio_text' => 'Hello, I have a reservation for tonight.', 'options' => [
                    ['id' => 'a', 'text' => 'Welcome! May I have your name, please?', 'image' => 'media:practice-response-a'],
                    ['id' => 'b', 'text' => 'We are full tonight.', 'image' => 'media:practice-response-b'],
                    ['id' => 'c', 'text' => 'Please come back tomorrow.', 'image' => 'media:practice-response-c'],
                ], 'correct' => 'a'],
                ['id' => 'i2', 'situation' => 'A guest is asking if there are any available rooms.', 'guest_audio_text' => 'Do you have any rooms available tonight?', 'options' => [
                    ['id' => 'a', 'text' => "Of course. I'll check for you right away.", 'image' => 'media:practice-response-a'],
                    ['id' => 'b', 'text' => "No, we don't have any rooms available.", 'image' => 'media:practice-response-b'],
                    ['id' => 'c', 'text' => 'You can come back later.', 'image' => 'media:practice-response-c'],
                ], 'correct' => 'a'],
                ['id' => 'i3', 'situation' => 'A guest says the air conditioning is not working.', 'guest_audio_text' => "The air conditioning in my room isn't working.", 'options' => [
                    ['id' => 'a', 'text' => "That's not my job.", 'image' => 'media:practice-response-c'],
                    ['id' => 'b', 'text' => "I'm sorry for the inconvenience. I will call maintenance right away.", 'image' => 'media:practice-response-a'],
                    ['id' => 'c', 'text' => 'Open the window.', 'image' => 'media:practice-response-b'],
                ], 'correct' => 'b'],
                ['id' => 'i4', 'situation' => 'A guest asks about breakfast.', 'guest_audio_text' => 'What time is breakfast?', 'options' => [
                    ['id' => 'a', 'text' => 'I have no idea.', 'image' => 'media:practice-response-b'],
                    ['id' => 'b', 'text' => 'Breakfast is not important.', 'image' => 'media:practice-response-c'],
                    ['id' => 'c', 'text' => 'Breakfast is served from 7 to 10 a.m. in the main restaurant.', 'image' => 'media:practice-response-a'],
                ], 'correct' => 'c'],
                ['id' => 'i5', 'situation' => 'A guest asks for directions to the spa.', 'guest_audio_text' => 'Excuse me, where is the spa?', 'options' => [
                    ['id' => 'a', 'text' => "It's on the second floor. I can show you the way.", 'image' => 'media:practice-response-a'],
                    ['id' => 'b', 'text' => 'Look for it yourself.', 'image' => 'media:practice-response-c'],
                    ['id' => 'c', 'text' => 'The spa is closed forever.', 'image' => 'media:practice-response-b'],
                ], 'correct' => 'a'],
            ],
        ],
    ],

    // 4. Listen & Match (photo_11). Item 1 is the mockup: five words, six pictures.
    [
        'key' => 'hgc-listen-match',
        'type' => 'listen_match',
        'title' => 'Listen & Match',
        'skill_label' => 'Vocabulary',
        'prompt' => 'Listen to the word and match it with the correct picture.',
        'payload' => [
            'side_image' => 'media:practice-bell-small-details',
            'heading' => 'Listen to the words:',
            'task' => 'Listen to each word and click the matching picture.',
            'tip' => 'Listen carefully. You can play the audio as many times as you need.',
            'items' => [
                ['id' => 'i1',
                    'prompts' => [
                        ['id' => '1', 'audio_text' => 'Towel'],
                        ['id' => '2', 'audio_text' => 'Pillow'],
                        ['id' => '3', 'audio_text' => 'Hair dryer'],
                        ['id' => '4', 'audio_text' => 'Room key'],
                        ['id' => '5', 'audio_text' => 'Slippers'],
                    ],
                    'targets' => [
                        ['id' => 'a', 'label' => 'Towel', 'image' => 'media:practice-towel'],
                        ['id' => 'b', 'label' => 'Pillow', 'image' => 'media:practice-pillow'],
                        ['id' => 'c', 'label' => 'Hair dryer', 'image' => 'media:practice-hair-dryer'],
                        ['id' => 'd', 'label' => 'Room key', 'image' => 'media:practice-room-key'],
                        ['id' => 'e', 'label' => 'Slippers', 'image' => 'media:practice-slippers'],
                        ['id' => 'f', 'label' => 'Toiletries', 'image' => 'media:practice-toiletries'],
                    ],
                    'pairs' => ['1' => 'a', '2' => 'b', '3' => 'c', '4' => 'd', '5' => 'e'],
                ],
                ['id' => 'i2',
                    'prompts' => [
                        ['id' => '1', 'audio_text' => 'Passport'],
                        ['id' => '2', 'audio_text' => 'Credit card'],
                        ['id' => '3', 'audio_text' => 'Room key'],
                        ['id' => '4', 'audio_text' => 'Toiletries'],
                        ['id' => '5', 'audio_text' => 'Pillow'],
                    ],
                    'targets' => [
                        ['id' => 'a', 'label' => 'Credit card', 'image' => 'media:practice-credit-card'],
                        ['id' => 'b', 'label' => 'Passport', 'image' => 'media:practice-passport'],
                        ['id' => 'c', 'label' => 'Pillow', 'image' => 'media:practice-pillow'],
                        ['id' => 'd', 'label' => 'Toiletries', 'image' => 'media:practice-toiletries'],
                        ['id' => 'e', 'label' => 'Room key', 'image' => 'media:practice-room-key'],
                        ['id' => 'f', 'label' => 'Towel', 'image' => 'media:practice-towel'],
                    ],
                    'pairs' => ['1' => 'b', '2' => 'a', '3' => 'e', '4' => 'd', '5' => 'c'],
                ],
                ['id' => 'i3',
                    'prompts' => [
                        ['id' => '1', 'audio_text' => 'Air conditioning'],
                        ['id' => '2', 'audio_text' => 'Temperature'],
                        ['id' => '3', 'audio_text' => 'Maintenance'],
                        ['id' => '4', 'audio_text' => 'Broken'],
                        ['id' => '5', 'audio_text' => 'Repair'],
                    ],
                    'targets' => [
                        ['id' => 'a', 'label' => 'Repair', 'image' => 'media:vocab-repair'],
                        ['id' => 'b', 'label' => 'Broken', 'image' => 'media:vocab-broken'],
                        ['id' => 'c', 'label' => 'Maintenance', 'image' => 'media:vocab-maintenance'],
                        ['id' => 'd', 'label' => 'Temperature', 'image' => 'media:vocab-temperature'],
                        ['id' => 'e', 'label' => 'Air conditioning', 'image' => 'media:vocab-air-conditioning'],
                        ['id' => 'f', 'label' => 'Room key', 'image' => 'media:practice-room-key'],
                    ],
                    'pairs' => ['1' => 'e', '2' => 'd', '3' => 'c', '4' => 'b', '5' => 'a'],
                ],
                ['id' => 'i4',
                    'prompts' => [
                        ['id' => '1', 'audio_text' => 'Reservation'],
                        ['id' => '2', 'audio_text' => 'Clock'],
                        ['id' => '3', 'audio_text' => 'Map'],
                        ['id' => '4', 'audio_text' => 'Key'],
                        ['id' => '5', 'audio_text' => 'Receptionist'],
                    ],
                    'targets' => [
                        ['id' => 'a', 'label' => 'Clock', 'image' => 'media:expr-room-ready'],
                        ['id' => 'b', 'label' => 'Reservation', 'image' => 'media:expr-reservation'],
                        ['id' => 'c', 'label' => 'Receptionist', 'image' => 'media:expr-how-can-i-help'],
                        ['id' => 'd', 'label' => 'Map', 'image' => 'media:expr-show-the-way'],
                        ['id' => 'e', 'label' => 'Key', 'image' => 'media:expr-let-me-check'],
                        ['id' => 'f', 'label' => 'Slippers', 'image' => 'media:practice-slippers'],
                    ],
                    'pairs' => ['1' => 'b', '2' => 'a', '3' => 'd', '4' => 'e', '5' => 'c'],
                ],
                ['id' => 'i5',
                    'prompts' => [
                        ['id' => '1', 'audio_text' => 'Slippers'],
                        ['id' => '2', 'audio_text' => 'Hair dryer'],
                        ['id' => '3', 'audio_text' => 'Towel'],
                        ['id' => '4', 'audio_text' => 'Passport'],
                        ['id' => '5', 'audio_text' => 'Pillow'],
                    ],
                    'targets' => [
                        ['id' => 'a', 'label' => 'Pillow', 'image' => 'media:practice-pillow'],
                        ['id' => 'b', 'label' => 'Passport', 'image' => 'media:practice-passport'],
                        ['id' => 'c', 'label' => 'Towel', 'image' => 'media:practice-towel'],
                        ['id' => 'd', 'label' => 'Hair dryer', 'image' => 'media:practice-hair-dryer'],
                        ['id' => 'e', 'label' => 'Slippers', 'image' => 'media:practice-slippers'],
                        ['id' => 'f', 'label' => 'Credit card', 'image' => 'media:practice-credit-card'],
                    ],
                    'pairs' => ['1' => 'e', '2' => 'd', '3' => 'c', '4' => 'b', '5' => 'a'],
                ],
            ],
        ],
    ],

    // 5. Watch & Respond (photo_12). Item 3 is the mockup. No video files
    // are seeded (`video` null): the player shows the poster.
    [
        'key' => 'hgc-watch-respond',
        'type' => 'watch_respond',
        'title' => 'Watch & Respond',
        'skill_label' => 'Real-life Situation',
        'prompt' => 'Watch the short video and choose what to say or do next.',
        'payload' => [
            'tip' => 'Watch carefully. Think about a polite and professional response.',
            'items' => [
                ['id' => 'i1', 'video' => null, 'poster' => 'media:video-poster-reception', 'subtitle' => 'Guest: Hello, I have a reservation for tonight.', 'question' => 'What should you say now?', 'hint' => 'Choose the best response.', 'options' => [
                    ['id' => 'a', 'text' => 'Welcome! May I have your name, please?'],
                    ['id' => 'b', 'text' => 'We are very busy today.'],
                    ['id' => 'c', 'text' => 'Wait there.'],
                ], 'correct' => 'a'],
                ['id' => 'i2', 'video' => null, 'poster' => 'media:practice-guest-asking', 'subtitle' => 'Guest: Excuse me, what time is breakfast?', 'question' => 'What should you say now?', 'hint' => 'Choose the best response.', 'options' => [
                    ['id' => 'a', 'text' => 'Ask someone else, please.'],
                    ['id' => 'b', 'text' => 'Breakfast is served from 7 to 10 a.m. in the main restaurant.'],
                    ['id' => 'c', 'text' => 'I am busy right now.'],
                ], 'correct' => 'b'],
                ['id' => 'i3', 'video' => null, 'poster' => 'media:practice-video-poster-not-clean', 'subtitle' => "Guest: I'm sorry, but my room isn't clean yet.", 'question' => 'What should you say now?', 'hint' => 'Choose the best response.', 'options' => [
                    ['id' => 'a', 'text' => "I'm very sorry for the inconvenience.\nLet me check this for you right away."],
                    ['id' => 'b', 'text' => "I don't know. Maybe later."],
                    ['id' => 'c', 'text' => 'You can wait in the lobby.'],
                ], 'correct' => 'a'],
                ['id' => 'i4', 'video' => null, 'poster' => 'media:dialogue-checkin', 'subtitle' => 'Guest: Could I have a late check-out tomorrow?', 'question' => 'What should you say now?', 'hint' => 'Choose the best response.', 'options' => [
                    ['id' => 'a', 'text' => 'No. Check-out is at 12.'],
                    ['id' => 'b', 'text' => "That's impossible."],
                    ['id' => 'c', 'text' => 'Let me check that for you. A late check-out until 2 p.m. is possible.'],
                ], 'correct' => 'c'],
                ['id' => 'i5', 'video' => null, 'poster' => 'media:situation-complaint', 'subtitle' => 'Guest: The air conditioning in my room is broken.', 'question' => 'What should you say now?', 'hint' => 'Choose the best response.', 'options' => [
                    ['id' => 'a', 'text' => "I'm sorry to hear that. I will send maintenance to your room right away."],
                    ['id' => 'b', 'text' => 'It is not very hot today.'],
                    ['id' => 'c', 'text' => 'You should have told us earlier.'],
                ], 'correct' => 'a'],
            ],
        ],
    ],

    // 6. Words & Sentences (photo_13). Item 3 is the mockup.
    [
        'key' => 'hgc-words-sentences',
        'type' => 'words_sentences',
        'title' => 'Words & Sentences',
        'skill_label' => 'Vocabulary in Context',
        'prompt' => 'Choose the correct word to complete the sentence.',
        'payload' => [
            'side_image' => 'media:practice-bell-great-guests',
            'heading' => 'Listen to the sentence.',
            'task' => 'Read the sentence and choose the correct word.',
            'tip' => 'Think about the meaning. You can listen to the sentence if you need to.',
            'items' => [
                ['id' => 'i1', 'sentence' => 'Here is your ____. Your room is 215.', 'audio_text' => 'Here is your key. Your room is 215.', 'image' => null, 'options' => [
                    ['id' => 'a', 'label' => 'key', 'image' => 'media:practice-room-key'],
                    ['id' => 'b', 'label' => 'pillow', 'image' => 'media:practice-pillow'],
                    ['id' => 'c', 'label' => 'passport', 'image' => 'media:practice-passport'],
                ], 'correct' => 'a'],
                ['id' => 'i2', 'sentence' => 'Could we have two extra ____, please?', 'audio_text' => 'Could we have two extra towels, please?', 'image' => null, 'options' => [
                    ['id' => 'a', 'label' => 'keys', 'image' => 'media:practice-room-key'],
                    ['id' => 'b', 'label' => 'towels', 'image' => 'media:practice-towel'],
                    ['id' => 'c', 'label' => 'passports', 'image' => 'media:practice-passport'],
                ], 'correct' => 'b'],
                ['id' => 'i3', 'sentence' => 'May I see your ____, please?', 'audio_text' => 'May I see your passport, please?', 'image' => null, 'options' => [
                    ['id' => 'a', 'label' => 'passport', 'image' => 'media:practice-passport'],
                    ['id' => 'b', 'label' => 'towel', 'image' => 'media:practice-towel'],
                    ['id' => 'c', 'label' => 'credit card', 'image' => 'media:practice-credit-card'],
                ], 'correct' => 'a'],
                ['id' => 'i4', 'sentence' => 'The ____ in my room is not working.', 'audio_text' => 'The air conditioning in my room is not working.', 'image' => null, 'options' => [
                    ['id' => 'a', 'label' => 'towel', 'image' => 'media:practice-towel'],
                    ['id' => 'b', 'label' => 'slippers', 'image' => 'media:practice-slippers'],
                    ['id' => 'c', 'label' => 'air conditioning', 'image' => 'media:vocab-air-conditioning'],
                ], 'correct' => 'c'],
                ['id' => 'i5', 'sentence' => 'Could I have a ____ for the deposit?', 'audio_text' => 'Could I have a credit card for the deposit?', 'image' => null, 'options' => [
                    ['id' => 'a', 'label' => 'hair dryer', 'image' => 'media:practice-hair-dryer'],
                    ['id' => 'b', 'label' => 'credit card', 'image' => 'media:practice-credit-card'],
                    ['id' => 'c', 'label' => 'pillow', 'image' => 'media:practice-pillow'],
                ], 'correct' => 'b'],
            ],
        ],
    ],

    // 7. Put the Dialogue in Order (photo_14). Item 1 is the mockup; the
    // sentences are listed in the shuffled order the mockup shows.
    [
        'key' => 'hgc-dialogue-order',
        'type' => 'dialogue_order',
        'title' => 'Put the Dialogue in Order',
        'skill_label' => 'Listening',
        'prompt' => 'Listen and put the sentences in the correct order.',
        'payload' => [
            'side_image' => 'media:practice-warm-welcome-key',
            'heading' => 'Listen to the dialogue.',
            'bank_label' => 'Drag the sentences to the correct order:',
            'order_label' => 'Your order:',
            'slot_placeholder' => 'Drag a sentence here',
            'task' => 'Listen to the dialogue and put the sentences in the correct order.',
            'tip' => 'Think about how a natural conversation usually starts and continues.',
            'items' => [
                ['id' => 'i1', 'audio_text' => "Good afternoon. Welcome to La Gazelle d'Or. I have a reservation under the name Ben Ali. Here is your key. Your room is 215. Thank you very much. Enjoy your stay.", 'sentences' => [
                    ['id' => 's1', 'text' => 'Here is your key. Your room is 215.'],
                    ['id' => 's2', 'text' => "Good afternoon. Welcome to La Gazelle d'Or."],
                    ['id' => 's3', 'text' => 'Thank you very much.'],
                    ['id' => 's4', 'text' => 'I have a reservation under the name Ben Ali.'],
                    ['id' => 's5', 'text' => 'Enjoy your stay.'],
                ], 'order' => ['s2', 's4', 's1', 's3', 's5']],
                ['id' => 'i2', 'audio_text' => 'Good morning. How can I help you? The air conditioning in my room is not working. I am sorry for the inconvenience. I will call maintenance right away. Thank you. You are welcome.', 'sentences' => [
                    ['id' => 's1', 'text' => 'I am sorry for the inconvenience. I will call maintenance right away.'],
                    ['id' => 's2', 'text' => 'Thank you.'],
                    ['id' => 's3', 'text' => 'Good morning. How can I help you?'],
                    ['id' => 's4', 'text' => 'You are welcome.'],
                    ['id' => 's5', 'text' => 'The air conditioning in my room is not working.'],
                ], 'order' => ['s3', 's5', 's1', 's2', 's4']],
                ['id' => 'i3', 'audio_text' => 'Excuse me, what time is breakfast? Breakfast is served from 7 to 10 a.m. Where is the restaurant? It is on the first floor. I can show you the way. Great, thank you!', 'sentences' => [
                    ['id' => 's1', 'text' => 'It is on the first floor. I can show you the way.'],
                    ['id' => 's2', 'text' => 'Excuse me, what time is breakfast?'],
                    ['id' => 's3', 'text' => 'Great, thank you!'],
                    ['id' => 's4', 'text' => 'Breakfast is served from 7 to 10 a.m.'],
                    ['id' => 's5', 'text' => 'Where is the restaurant?'],
                ], 'order' => ['s2', 's4', 's5', 's1', 's3']],
                ['id' => 'i4', 'audio_text' => 'Good evening. Do you have any rooms available tonight? Of course. Let me check that for you. We have a double room. Perfect, I will take it. May I see your passport, please? Here you are.', 'sentences' => [
                    ['id' => 's1', 'text' => 'Perfect, I will take it.'],
                    ['id' => 's2', 'text' => 'Here you are.'],
                    ['id' => 's3', 'text' => 'Good evening. Do you have any rooms available tonight?'],
                    ['id' => 's4', 'text' => 'May I see your passport, please?'],
                    ['id' => 's5', 'text' => 'Of course. Let me check that for you. We have a double room.'],
                ], 'order' => ['s3', 's5', 's1', 's4', 's2']],
                ['id' => 'i5', 'audio_text' => 'Good morning. I would like to check out, please. Of course. May I have your room number? Room 215. Here is your bill. Would you like a receipt? Yes, please. Thank you.', 'sentences' => [
                    ['id' => 's1', 'text' => 'Here is your bill. Would you like a receipt?'],
                    ['id' => 's2', 'text' => 'Good morning. I would like to check out, please.'],
                    ['id' => 's3', 'text' => 'Yes, please. Thank you.'],
                    ['id' => 's4', 'text' => 'Of course. May I have your room number?'],
                    ['id' => 's5', 'text' => 'Room 215.'],
                ], 'order' => ['s2', 's4', 's5', 's1', 's3']],
            ],
        ],
    ],
];
