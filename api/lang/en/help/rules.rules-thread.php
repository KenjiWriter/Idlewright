<?php

return [
    'entries' => [
        '/tracks/*/thread/return_ms' => [
            'label' => 'Way back of a run (s)',
            'help' => 'What the way back to the start adds to every lap of a track that is driven as a run (a drag strip, a sprint), in seconds, whatever the car. A circuit has none.',
            'range' => 'From 0 to 600 s (from the schema).',
            'tip' => 'It brings a run to the lap you want without changing its sections; the longer it is, the less the cars differ in their laps. It matters only when the lap is driven on the thread.',
        ],
        '/tracks/*/thread/sections/*/length_m' => [
            'label' => 'Straight: length (m)',
            'help' => 'How long a straight of the thread of the track is, in metres. The length of the track is the sum of the sections, each rounded to a metre, a corner counting as its arc.',
            'range' => 'From 5 to 5000 m (from the schema).',
            'tip' => 'Change the length of a straight and the length of the track must change with it: the validator says when they differ by more than a metre. The thread page does it for you.',
        ],
        '/tracks/*/thread/sections/*/radius_m' => [
            'label' => 'Corner: radius (m)',
            'help' => 'The radius of a corner of the thread of the track, in metres. A hairpin is about 20 m, a tight corner 35, a wide bend over 100. The speed through it comes from the grip and the handling of the car.',
            'range' => 'From 8 to 1000 m (from the schema).',
            'tip' => 'The speed in a corner is the square root of the sideways acceleration times the radius, so a corner twice as wide is taken about 40% faster.',
        ],
        '/tracks/*/thread/sections/*/angle_deg' => [
            'label' => 'Corner: angle (°)',
            'help' => 'How far a corner of the thread turns the car, in degrees: 90 is a right angle, 180 a hairpin.',
            'range' => 'From 5 to 360° (from the schema).',
            'tip' => 'The corners of a loop add up to about 360°, the left ones counting against the right ones; the validator warns when they do not, because the line would not meet itself.',
        ],
        '/tracks/*/thread/sections/*/rough' => [
            'label' => 'Section: roughness',
            'help' => 'How rough a section of the thread is, on the scale of the statistic clearance: a car with at least that clearance goes over it at the speed of rough ground, one with less goes slower. Smooth sections have none.',
            'range' => 'From 0 to 100 (from the schema).',
            'tip' => 'The speed over rough ground and how fast a low car slows down are in the studio (“Speed over a rough stretch”, “Slowing on a rough stretch”).',
        ],
    ],
];
