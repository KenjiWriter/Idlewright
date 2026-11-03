<?php

return [
    'entries' => [
        'track' => [
            'label' => 'Track',
            'help' => 'The track whose thread is shown and changed. The other tracks stay as they are.',
            'tip' => 'Save before you switch to another track: the changes to the one you leave are not kept.',
        ],
        'lap_from' => [
            'label' => 'Lap from',
            'help' => 'Where the lap of a car on this track comes from: its statistics through the demands of the track (the formula every track has had so far), or a lap driven on the thread of the track. With the formula the thread only draws the track.',
            'tip' => 'Before moving a track to the thread, look at the table of cars under the line: it shows how much each lap would change. The lap of the reference car stays the base lap of the track either way.',
            'options' => [
                'stats' => ['label' => 'Formula (statistics)', 'help' => 'The lap is made from the statistics of the car and the demands of the track, as before. The thread, if the track has one, is only a drawing.'],
                'thread' => ['label' => 'Thread', 'help' => 'The lap is driven on the sections of the thread: the car sets off, accelerates on the straights, brakes for the corners and holds the surface.'],
            ],
        ],
        'mode' => [
            'label' => 'How it is driven',
            'help' => 'A circuit is a loop driven flying: the car crosses the line at the speed it left it. A run is driven from a standstill every time, like a drag strip or a sprint.',
            'options' => [
                'circuit' => ['label' => 'Circuit', 'help' => 'A loop; the car does not stop at the line.'],
                'run' => ['label' => 'Run', 'help' => 'Every lap starts from a standstill, and the way back to the start takes a set time.'],
            ],
        ],
        'return_s' => [
            'label' => 'Way back (s)',
            'help' => 'What the way back to the start adds to every lap of a run, in seconds, whatever the car. A circuit has no way back.',
            'range' => 'From 0 to 600 s (from the schema).',
            'tip' => 'It brings a drag strip to the lap you want without changing the sections; the longer it is, the less the cars differ in their laps.',
        ],
        'sections' => [
            'label' => 'Sections',
            'help' => 'The thread as a chain of sections in the order they are driven: straights and corners. Change the order with the arrows; the line next to it is drawn from them.',
        ],
        'type' => [
            'label' => 'Kind of section',
            'help' => 'A straight goes on; a corner turns the car by an angle on a radius.',
            'options' => [
                'straight' => ['label' => 'Straight', 'help' => 'Has a length.'],
                'corner' => ['label' => 'Corner', 'help' => 'Has a radius and an angle.'],
            ],
        ],
        'length' => [
            'label' => 'Length (m)',
            'help' => 'How long the straight is, in metres.',
            'range' => 'From 5 to 5000 m (from the schema).',
            'tip' => 'The length of the track is the sum of the sections, and it is written when you save.',
        ],
        'radius' => [
            'label' => 'Radius (m)',
            'help' => 'The radius of the corner, in metres. A hairpin is about 20 m, a tight corner 35, a wide bend over 100. The speed through it comes from the grip and the handling of the car.',
            'range' => 'From 8 to 1000 m (from the schema).',
        ],
        'angle' => [
            'label' => 'Angle (°)',
            'help' => 'How far the corner turns the car, in degrees: 90 is a right angle, 180 a hairpin.',
            'range' => 'From 5 to 360° (from the schema).',
            'tip' => 'The corners of a loop add up to about 360° (the left ones count against the right ones); otherwise the line does not meet itself in the drawing.',
        ],
        'turn' => [
            'label' => 'Turns',
            'help' => 'Which way the corner turns. It only draws the line: the lap does not depend on it.',
            'options' => [
                'left' => ['label' => 'Left', 'help' => 'The line bends to the left.'],
                'right' => ['label' => 'Right', 'help' => 'The line bends to the right.'],
            ],
        ],
        'surface' => [
            'label' => 'Surface',
            'help' => 'What the section is made of when it is not what the track is made of: a stretch of gravel in a rally stage. Empty means the surface of the track. The weather turns it wet, muddy or snowy, and the tires of a car grip each of these surfaces differently.',
            'options' => [
                'asphalt' => ['label' => 'Asphalt', 'help' => 'Dry asphalt; rain makes it wet asphalt, snow covers it.'],
                'gravel' => ['label' => 'Gravel', 'help' => 'Loose gravel; rain turns it into mud, snow covers it. It holds the top speed of a car back and asks for the right tires.'],
            ],
        ],
        'limit' => [
            'label' => 'Speed limit (km/h)',
            'help' => 'A speed that no car goes faster than in this section: a street, a pit lane, the road back. Empty means none.',
            'range' => 'From 20 to 400 km/h (from the schema).',
        ],
        'rough' => [
            'label' => 'Roughness',
            'help' => 'How rough the section is, on the scale of the statistic clearance: a car with at least that clearance goes over it at the speed of rough ground, one with less goes slower. Empty or 0 means smooth.',
            'range' => 'From 0 to 100 (from the schema).',
            'tip' => 'The speed over rough ground and how fast a low car slows down are in the studio (“Speed over a rough stretch”, “Slowing on a rough stretch”).',
        ],
        'scale_factor' => [
            'label' => 'Factor',
            'help' => 'The number every radius is multiplied by: 1.2 makes the corners 20% wider, 0.8 makes them tighter.',
            'range' => 'From 0.2 to 5.',
            'tip' => 'After the radii are scaled the loop usually stops meeting itself: close it again.',
        ],
        'stretch_length' => [
            'label' => 'Length (m)',
            'help' => 'The length, in metres, that the longest straight of the thread gets.',
            'range' => 'From 5 to 5000 m (from the schema).',
            'tip' => 'A longer straight makes the track faster and puts more weight on power and top speed.',
        ],
    ],
];
