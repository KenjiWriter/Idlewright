<?php

return [
    'entries' => [
        '/dynamics/power_ppm' => [
            'label' => 'Leistung auf der Straße',
            'help' => 'Der Anteil der Leistung pro Masse eines Autos (sein Beschleunigungswert, in kW pro Tonne), der als Beschleunigung auf die Straße kommt, wenn eine Runde auf einem Streckenverlauf gefahren wird. 60 % heißt, dass ein Auto mit 100 kW pro Tonne wie ein Massepunkt mit 60 W pro kg Antrieb beschleunigt.',
            'range' => 'Von 10 % bis 200 % (aus dem Schema).',
            'tip' => 'Je höher, desto mehr entscheidet die Beschleunigung über die Runde und desto kürzer ist jede Runde auf einem Streckenverlauf. Die Runde des Referenzautos einer Strecke bleibt unberührt, sie ist durch die Basisrunde der Strecke festgelegt; es ändert sich nur, wie viel schneller oder langsamer die anderen Autos sind.',
        ],
        '/dynamics/lateral_g_ppm' => [
            'label' => 'Kurvenhaftung (g)',
            'help' => 'Was eine Haftung und ein Fahrverhalten von 100 in einer Kurve seitlich ergeben, in g, als Prozent angezeigt: 95 % sind 0,95 g. Eine Kurve wird mit der Geschwindigkeit gefahren, bei der die Querbeschleunigung dieser Wert mal Haftung und Fahrverhalten des Autos geteilt durch 100 ist.',
            'range' => 'Von 30 % bis 250 % (aus dem Schema).',
            'tip' => 'Es ändert, wie viel die Kurven gegenüber den Geraden zählen: Erhöhe es, und die Autos, die von den Kurven gebremst werden, holen auf die auf, die von der Leistung gebremst werden.',
        ],
        '/dynamics/brake_g_ppm' => [
            'label' => 'Bremsen (g)',
            'help' => 'Was ein Bremswert von 100 an den Bremsen ergibt, in g, als Prozent angezeigt: 100 % sind 1 g. Er legt fest, wie spät ein Auto vor einer Kurve bremsen kann: Der Weg zum Abbremsen aus einer Geschwindigkeit ist die Geschwindigkeit im Quadrat geteilt durch die doppelte Verzögerung.',
            'range' => 'Von 30 % bis 300 % (aus dem Schema).',
            'tip' => 'Erhöhe ihn, und jedes Auto bremst später, sodass eine Runde weniger durch das Bremsen und mehr durch Leistung und Kurven entschieden wird.',
        ],
        '/dynamics/launch_g_ppm' => [
            'label' => 'Haftung beim Anfahren (g)',
            'help' => 'Was eine Haftung von 100 der Beschleunigung beim Anfahren und aus langsamen Kurven heraus gibt, in g, als Prozent angezeigt. Bis zu der Geschwindigkeit, bei der der Motor nicht mehr hergibt, beschleunigt das Auto mit diesem Wert mal seiner Haftung geteilt durch 100; darüber entscheidet die Leistung.',
            'range' => 'Von 30 % bis 250 % (aus dem Schema).',
            'tip' => 'Erhöhe ihn, und starke Autos auf schlechten Reifen oder schlechtem Belag verlieren beim Anfahren weniger. Auf Schotter und Schlamm multiplizieren die Reifen die Haftung, die Grenze liegt dort also niedriger.',
        ],
        '/dynamics/agility/min_ppm' => [
            'label' => 'Anteil des Fahrverhaltens: weite Kurve',
            'help' => 'Wie viel der Geschwindigkeit in einer weiten Kurve vom Fahrverhalten und wie viel von der Haftung abhängt, als Prozent angezeigt: 25 % heißt ein Viertel Fahrverhalten und drei Viertel Haftung. Eine weite Kurve hat den Radius des Feldes „Radius der weiten Kurve“ oder mehr.',
            'range' => 'Von 0 % bis 100 % (aus dem Schema).',
            'tip' => 'Der Anteil in einer Kurve mit einem Radius zwischen den beiden Werten sinkt linear von dem der Kehre auf diesen.',
        ],
        '/dynamics/agility/max_ppm' => [
            'label' => 'Anteil des Fahrverhaltens: Kehre',
            'help' => 'Wie viel der Geschwindigkeit in einer Kehre (ein Radius nahe null) vom Fahrverhalten und wie viel von der Haftung abhängt, als Prozent angezeigt: 75 % heißt drei Viertel Fahrverhalten.',
            'range' => 'Von 0 % bis 100 % (aus dem Schema).',
            'tip' => 'Ein schweres Auto mit schwachem Fahrverhalten verliert in Kehren am meisten; erhöhe ihn, damit Kehren für solche Autos mehr zählen.',
        ],
        '/dynamics/agility/radius_m' => [
            'label' => 'Radius der weiten Kurve (m)',
            'help' => 'Ab diesem Radius, in Metern, ist eine Kurve eine weite Kurve, und es zählt nur der kleinste Anteil des Fahrverhaltens; darunter steigt der Anteil linear bis zum größten in der Kehre.',
            'range' => 'Von 20 bis 2000 m (aus dem Schema).',
            'tip' => 'Ein kleiner Radius macht die meisten Kurven zu weiten Kurven, in denen die Haftung entscheidet; ein großer lässt das Fahrverhalten in mehr davon zählen.',
        ],
        '/dynamics/top_speed_ppm/asphalt_dry' => [
            'label' => 'Höchstgeschwindigkeit: trockener Asphalt',
            'help' => 'Was von der Höchstgeschwindigkeit eines Autos auf diesem Belag (trockener Asphalt) bleibt, als Prozent angezeigt: 100 % bremst sie gar nicht, weniger bremst sie. Es gibt eine Zahl für jeden Belag, den das Wetter machen kann: trockener Asphalt, nasser Asphalt, trockener Schotter, Schlamm und Schnee.',
            'range' => 'Von 10 % bis 100 % (aus dem Schema).',
            'tip' => 'Ein Auto fährt auf einem Belag nie schneller als seine Höchstgeschwindigkeit mal diese Zahl. Senke Schlamm und Schnee, damit eine Strecke bei schlechtem Wetter ein Ort für langsame Autos wird.',
        ],
        '/dynamics/top_speed_ppm/asphalt_wet' => [
            'label' => 'Höchstgeschwindigkeit: nasser Asphalt',
            'help' => 'Was von der Höchstgeschwindigkeit eines Autos auf diesem Belag (nasser Asphalt) bleibt, als Prozent angezeigt: 100 % bremst sie gar nicht, weniger bremst sie. Es gibt eine Zahl für jeden Belag, den das Wetter machen kann: trockener Asphalt, nasser Asphalt, trockener Schotter, Schlamm und Schnee.',
            'range' => 'Von 10 % bis 100 % (aus dem Schema).',
            'tip' => 'Ein Auto fährt auf einem Belag nie schneller als seine Höchstgeschwindigkeit mal diese Zahl. Senke Schlamm und Schnee, damit eine Strecke bei schlechtem Wetter ein Ort für langsame Autos wird.',
        ],
        '/dynamics/top_speed_ppm/gravel_dry' => [
            'label' => 'Höchstgeschwindigkeit: trockener Schotter',
            'help' => 'Was von der Höchstgeschwindigkeit eines Autos auf diesem Belag (trockener Schotter) bleibt, als Prozent angezeigt: 100 % bremst sie gar nicht, weniger bremst sie. Es gibt eine Zahl für jeden Belag, den das Wetter machen kann: trockener Asphalt, nasser Asphalt, trockener Schotter, Schlamm und Schnee.',
            'range' => 'Von 10 % bis 100 % (aus dem Schema).',
            'tip' => 'Ein Auto fährt auf einem Belag nie schneller als seine Höchstgeschwindigkeit mal diese Zahl. Senke Schlamm und Schnee, damit eine Strecke bei schlechtem Wetter ein Ort für langsame Autos wird.',
        ],
        '/dynamics/top_speed_ppm/mud' => [
            'label' => 'Höchstgeschwindigkeit: Schlamm',
            'help' => 'Was von der Höchstgeschwindigkeit eines Autos auf diesem Belag (Schlamm) bleibt, als Prozent angezeigt: 100 % bremst sie gar nicht, weniger bremst sie. Es gibt eine Zahl für jeden Belag, den das Wetter machen kann: trockener Asphalt, nasser Asphalt, trockener Schotter, Schlamm und Schnee.',
            'range' => 'Von 10 % bis 100 % (aus dem Schema).',
            'tip' => 'Ein Auto fährt auf einem Belag nie schneller als seine Höchstgeschwindigkeit mal diese Zahl. Senke Schlamm und Schnee, damit eine Strecke bei schlechtem Wetter ein Ort für langsame Autos wird.',
        ],
        '/dynamics/top_speed_ppm/snow' => [
            'label' => 'Höchstgeschwindigkeit: Schnee',
            'help' => 'Was von der Höchstgeschwindigkeit eines Autos auf diesem Belag (Schnee) bleibt, als Prozent angezeigt: 100 % bremst sie gar nicht, weniger bremst sie. Es gibt eine Zahl für jeden Belag, den das Wetter machen kann: trockener Asphalt, nasser Asphalt, trockener Schotter, Schlamm und Schnee.',
            'range' => 'Von 10 % bis 100 % (aus dem Schema).',
            'tip' => 'Ein Auto fährt auf einem Belag nie schneller als seine Höchstgeschwindigkeit mal diese Zahl. Senke Schlamm und Schnee, damit eine Strecke bei schlechtem Wetter ein Ort für langsame Autos wird.',
        ],
        '/dynamics/rough/speed_kmh' => [
            'label' => 'Tempo auf einem rauen Abschnitt (km/h)',
            'help' => 'Das Tempo, in km/h, mit dem ein Auto, das die ganze Bodenfreiheit hat, die ein rauer Abschnitt verlangt, ihn befährt. Kein Auto fährt auf einem als rau markierten Abschnitt schneller, so gut es auch ist; ein Auto mit weniger Bodenfreiheit fährt langsamer.',
            'range' => 'Von 20 bis 400 km/h (aus dem Schema).',
            'tip' => 'Die Rauheit eines Abschnitts wird am Abschnitt des Streckenverlaufs einer Strecke eingestellt, auf der Skala des Wertes Bodenfreiheit.',
        ],
        '/dynamics/rough/exponent_ppm' => [
            'label' => 'Verlangsamung auf einem rauen Abschnitt',
            'help' => 'Wie stark ein Auto mit zu wenig Bodenfreiheit auf einem rauen Abschnitt langsamer wird, als Prozent eines Exponenten angezeigt: Sein Tempo auf dem Abschnitt ist das Tempo von oben mal (seine Bodenfreiheit geteilt durch das, was der Abschnitt verlangt) hoch diese Potenz. 50 % ist die Quadratwurzel: Ein Auto mit einem Viertel der Bodenfreiheit fährt halb so schnell.',
            'range' => 'Von 5 % bis 300 % (aus dem Schema).',
            'tip' => 'Erhöhe ihn, um niedrige Autos stärker zu bestrafen, senke ihn, um sie fast so schnell durchzulassen wie die hohen.',
        ],
    ],
];
