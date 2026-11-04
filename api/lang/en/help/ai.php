<?php

return [
    'entries' => [
        'include' => [
            'label' => 'What to include',
            'help' => 'What the assistant is to know about the game: the cars, the parts, the numbers of the rules and the tutorial. An answer may change only what you tick here.',
            'example' => 'Only the parts, when you want to change their prices: untick the cars and the rules.',
            'tip' => 'The less you tick, the shorter the instruction and the more exact the conversation.',
            'options' => [
                'vehicles' => ['label' => 'Cars', 'help' => 'A table of the cars with their numbers and the fields they can be picked by (drivetrain, class).'],
                'parts' => ['label' => 'Parts', 'help' => 'One table for each slot (engine, tires, …), with the numbers of the parts.'],
                'rules' => ['label' => 'Numbers of the rules', 'help' => 'The numbers of the studio with their limits and a description of each.'],
                'tutorial' => ['label' => 'Tutorial', 'help' => 'The steps of the tutorial with their keys, the device of the mentor and its parts (choices), the numbers of the first run, of the rewards and of the story, and every text and word of the tutorial. The assistant can change them and add a step, but not delete a step, change their order or a picture.'],
            ],
        ],
        'simple' => [
            'label' => 'Only the most important numbers',
            'help' => 'On: only the numbers of the simple mode of the studio (prices, rewards, times, levels) go into the instruction. Off: every number of the sections you chose.',
            'tip' => 'An instruction with all the numbers is several times longer.',
        ],
        'locale' => [
            'label' => 'Language of the descriptions',
            'help' => 'The language of the labels and descriptions of the numbers in the instruction. The rules of the conversation and the format of the block are always in English, and the assistant answers in the language you write to it in.',
            'options' => [
                'pl' => ['label' => 'Polski', 'help' => 'Descriptions in Polish.'],
                'en' => ['label' => 'English', 'help' => 'Descriptions in English.'],
                'fr' => ['label' => 'Français', 'help' => 'Descriptions in French.'],
                'de' => ['label' => 'Deutsch', 'help' => 'Descriptions in German.'],
                'pt-BR' => ['label' => 'Português (Brasil)', 'help' => 'Descriptions in Brazilian Portuguese.'],
            ],
        ],
        'sections' => [
            'label' => 'Sections of the rules',
            'help' => 'Which parts of the numbers of the rules to include (the same as in the studio). None ticked: all of them.',
            'example' => 'Only “Paid features” (premium), when you talk about the price of the VIP.',
            'tip' => 'An answer may change only the sections you tick.',
        ],
        'texts' => [
            'label' => 'Include the texts',
            'help' => 'On: the names and descriptions of the cars and parts, and the texts of the sections of the rules you chose, go into the instruction in every language (a few thousand tokens more). Without it the assistant cannot see the texts, so it cannot change or translate any.',
            'tip' => 'Turn it on when you want to rename or translate.',
        ],
        'design' => [
            'label' => 'Design this game from scratch',
            'help' => 'On: the assistant does not change single numbers but works the design of the whole game out with you in stages (words, cars, parts, numbers, tracks and products), and each stage ends in one block. The texts are in the instruction whatever the switch “Include the texts” says.',
            'tip' => 'Use it in a new game that the wizard made from the template. The instruction is longer then.',
        ],
    ],
];
