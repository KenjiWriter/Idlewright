<?php

return [
    'entries' => [
        '/dynamics/power_ppm' => [
            'label' => 'Power at the road',
            'help' => 'The share of the power over mass of a car (its acceleration statistic, in kW per tonne) that reaches the road as acceleration when a lap is driven on a thread. 60% means that a car with 100 kW per tonne accelerates like a mass point with 60 W per kg of drive.',
            'range' => 'From 10% to 200% (from the schema).',
            'tip' => 'The higher it is, the more a lap is decided by acceleration, and the shorter every lap on a thread is. It does not move the lap of the reference car of a track, which is fixed by the base lap of the track, only how much faster or slower the other cars are than it.',
        ],
        '/dynamics/lateral_g_ppm' => [
            'label' => 'Cornering grip (g)',
            'help' => 'What a grip and a handling of 100 give sideways in a corner, in g, shown as a percent: 95% is 0.95 g. A corner is driven at the speed at which the sideways acceleration is this much times the grip and the handling of the car over 100.',
            'range' => 'From 30% to 250% (from the schema).',
            'tip' => 'It changes how much the corners matter against the straights: raise it and the cars that are held back by the corners gain on the ones that are held back by power.',
        ],
        '/dynamics/brake_g_ppm' => [
            'label' => 'Braking (g)',
            'help' => 'What a braking statistic of 100 gives on the brakes, in g, shown as a percent: 100% is 1 g. It sets how late a car can brake for a corner: the distance to slow down from a speed is the speed squared over twice the deceleration.',
            'range' => 'From 30% to 300% (from the schema).',
            'tip' => 'Raise it and every car brakes later, so a lap is decided less by braking and more by power and corners.',
        ],
        '/dynamics/launch_g_ppm' => [
            'label' => 'Grip off the line (g)',
            'help' => 'What a grip of 100 gives to the acceleration off the line and out of slow corners, in g, shown as a percent. Up to the speed at which the engine can no longer give more, the car accelerates with this much times its grip over 100; above it the power decides.',
            'range' => 'From 30% to 250% (from the schema).',
            'tip' => 'Raise it and powerful cars on poor tires or poor surfaces lose less off the line. On gravel and mud the tires multiply the grip, so the limit is lower there.',
        ],
        '/dynamics/agility/min_ppm' => [
            'label' => 'Handling share: wide bend',
            'help' => 'How much of the speed through a wide bend is down to handling and how much to grip, shown as a percent: 25% means a quarter handling and three quarters grip. A wide bend is one of the radius of the field “Wide-bend radius” or more.',
            'range' => 'From 0% to 100% (from the schema).',
            'tip' => 'The share in a corner of a radius between the two values falls in a straight line from the one for a hairpin to this one.',
        ],
        '/dynamics/agility/max_ppm' => [
            'label' => 'Handling share: hairpin',
            'help' => 'How much of the speed through a hairpin (a radius close to nothing) is down to handling and how much to grip, shown as a percent: 75% means three quarters handling.',
            'range' => 'From 0% to 100% (from the schema).',
            'tip' => 'A heavy car, with poor handling, loses the most in hairpins; raise this to make hairpins matter more to such cars.',
        ],
        '/dynamics/agility/radius_m' => [
            'label' => 'Wide-bend radius (m)',
            'help' => 'From this radius, in metres, a corner counts as a wide bend and only the lowest share of handling counts in it; below it the share rises in a straight line to the highest one in a hairpin.',
            'range' => 'From 20 to 2000 m (from the schema).',
            'tip' => 'A small radius makes most corners count as wide bends, where grip decides; a large one makes handling count in more of them.',
        ],
        '/dynamics/top_speed_ppm/asphalt_dry' => [
            'label' => 'Top speed on dry asphalt',
            'help' => 'What is left of the top speed of a car on dry asphalt, shown as a percent: 100% holds it back by nothing, less than that holds it back. There is one number for each surface the weather can make: dry asphalt, wet asphalt, dry gravel, mud and snow.',
            'range' => 'From 10% to 100% (from the schema).',
            'tip' => 'A car never goes faster on a surface than its top speed times this. Lower the mud and the snow to make a track in bad weather a place for slow cars.',
        ],
        '/dynamics/top_speed_ppm/asphalt_wet' => [
            'label' => 'Top speed on wet asphalt',
            'help' => 'What is left of the top speed of a car on wet asphalt, shown as a percent: 100% holds it back by nothing, less than that holds it back. There is one number for each surface the weather can make: dry asphalt, wet asphalt, dry gravel, mud and snow.',
            'range' => 'From 10% to 100% (from the schema).',
            'tip' => 'A car never goes faster on a surface than its top speed times this. Lower the mud and the snow to make a track in bad weather a place for slow cars.',
        ],
        '/dynamics/top_speed_ppm/gravel_dry' => [
            'label' => 'Top speed on dry gravel',
            'help' => 'What is left of the top speed of a car on dry gravel, shown as a percent: 100% holds it back by nothing, less than that holds it back. There is one number for each surface the weather can make: dry asphalt, wet asphalt, dry gravel, mud and snow.',
            'range' => 'From 10% to 100% (from the schema).',
            'tip' => 'A car never goes faster on a surface than its top speed times this. Lower the mud and the snow to make a track in bad weather a place for slow cars.',
        ],
        '/dynamics/top_speed_ppm/mud' => [
            'label' => 'Top speed on mud',
            'help' => 'What is left of the top speed of a car on mud, shown as a percent: 100% holds it back by nothing, less than that holds it back. There is one number for each surface the weather can make: dry asphalt, wet asphalt, dry gravel, mud and snow.',
            'range' => 'From 10% to 100% (from the schema).',
            'tip' => 'A car never goes faster on a surface than its top speed times this. Lower the mud and the snow to make a track in bad weather a place for slow cars.',
        ],
        '/dynamics/top_speed_ppm/snow' => [
            'label' => 'Top speed on snow',
            'help' => 'What is left of the top speed of a car on snow, shown as a percent: 100% holds it back by nothing, less than that holds it back. There is one number for each surface the weather can make: dry asphalt, wet asphalt, dry gravel, mud and snow.',
            'range' => 'From 10% to 100% (from the schema).',
            'tip' => 'A car never goes faster on a surface than its top speed times this. Lower the mud and the snow to make a track in bad weather a place for slow cars.',
        ],
        '/dynamics/rough/speed_kmh' => [
            'label' => 'Speed over a rough stretch (km/h)',
            'help' => 'The speed, in km/h, at which a car with all the clearance a rough stretch asks for goes over it. No car goes faster over a stretch marked as rough, however good it is; a car with less clearance goes slower.',
            'range' => 'From 20 to 400 km/h (from the schema).',
            'tip' => 'The roughness of a stretch is set on the stretch of the thread of a track, on the scale of the statistic clearance.',
        ],
        '/dynamics/rough/exponent_ppm' => [
            'label' => 'Slowing on a rough stretch',
            'help' => 'How fast a car with too little clearance slows down on a rough stretch, shown as a percent of an exponent: its speed over the stretch is the speed above times (its clearance over what the stretch asks for) to this power. 50% is the square root: a car with a quarter of the clearance goes half as fast.',
            'range' => 'From 5% to 300% (from the schema).',
            'tip' => 'Raise it to punish low cars more, lower it to let them through almost as fast as the high ones.',
        ],
    ],
];
