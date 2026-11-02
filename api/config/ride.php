<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Obsługiwane języki
    |--------------------------------------------------------------------------
    |
    | Kody publiczne (API, panel, paczki treści) są w formacie BCP 47: pl, en,
    | fr, de, pt-BR. Laravel używa wewnętrznie pt_BR, więc zamiana kodu
    | odbywa się w App\Support\Locale. Brazylijski portugalski to pt-BR,
    | a skrót "br" jest aliasem, tak jak "pt".
    |
    */

    'locales' => ['pl', 'en', 'fr', 'de', 'pt-BR'],

    'default_locale' => 'en',

    // Języki, w których treść z paczek musi być uzupełniona, żeby paczka przeszła walidację.
    'required_content_locales' => ['en', 'pl'],

    // Klucze zapisujemy małymi literami.
    'locale_aliases' => [
        'br' => 'pt-BR',
        'pt' => 'pt-BR',
        'pt-br' => 'pt-BR',
        'en-us' => 'en',
        'en-gb' => 'en',
    ],

    // Nazwy języków w ich własnym języku, do wyboru w interfejsie.
    'locale_names' => [
        'pl' => 'Polski',
        'en' => 'English',
        'fr' => 'Français',
        'de' => 'Deutsch',
        'pt-BR' => 'Português (Brasil)',
    ],

    // Kolejność szukania tłumaczenia treści, gdy brakuje wybranego języka.
    'content_fallbacks' => ['en', 'pl'],

    /*
    |--------------------------------------------------------------------------
    | Konta gościa
    |--------------------------------------------------------------------------
    |
    | Gość bez aktywności przez tyle dni jest usuwany (konto gościa nie ma
    | danych osobowych ani sposobu kontaktu).
    |
    */

    'guest_expiry_days' => 90,

    /*
    |--------------------------------------------------------------------------
    | Paczki treści
    |--------------------------------------------------------------------------
    |
    | Katalog content/ w repozytorium: schema/ ze schematami JSON i packs/ z
    | paczkami (auta, części). Walidacja: php artisan content:validate.
    |
    */

    'content_path' => env('CONTENT_PATH', dirname(base_path()).DIRECTORY_SEPARATOR.'content'),

    // The texts that the web app brings (web/src/i18n/locales/<language>.json): what the vocabulary of a pack changes. The panel shows them next to the words that
    // a pack puts in their place, and the validator checks that a message of the vocabulary is one of them; when the folder is not there (an API on its own),
    // neither is done.
    'web_locales_path' => env('WEB_LOCALES_PATH', dirname(base_path()).DIRECTORY_SEPARATOR.'web'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'i18n'.DIRECTORY_SEPARATOR.'locales'),

    // The build of the web app (web/dist): the page that a robot is given for the address of a game is the page of the app with the tags of the preview of the game in
    // its head (GET /api/v1/meta/share), so it is read from there; where the folder is not there (an API on its own), the page is the tags alone.
    'web_dist_path' => env('WEB_DIST_PATH', dirname(base_path()).DIRECTORY_SEPARATOR.'web'.DIRECTORY_SEPARATOR.'dist'),

    // The admin panel (F4): the host it answers on (admin.example.com; then it is the whole of that host), or nothing, and it is /admin on the host of the
    // application, which is how a development machine has it. The language of the panel is the one of the people who run it.
    'admin_domain' => env('ADMIN_DOMAIN') ?: null,
    'admin_locale' => env('ADMIN_LOCALE', 'pl'),

    // The network of games: the address that Let's Encrypt writes to about the certificates of the domains of the games (it is given to the worker on the server that sets a
    // domain up), and the public addresses of this server, comma separated, which the domain of a game has to point to (empty: the address that the host of WEB_URL resolves to).
    'network' => [
        'acme_email' => env('ACME_EMAIL') ?: null,
        'server_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('SERVER_IPS', ''))))),
    ],

    // Where the game reads its content from: `files` (the content/ folder, which is what a development machine and the tests use) or `database` (the pack that
    // was made live with content:activate; a deploy installs the one of the release the first time, with content:check --install).
    'content_source' => env('CONTENT_SOURCE', 'files'),

    // Version of the set of rules (prices, rewards, anchors) that runs are frozen with when the content is read from files. A pack in the database has its own
    // number, which the runs are frozen with and compared by: when it changes, every run is frozen again at its next settlement.
    'ruleset_version' => env('RIDE_RULESET_VERSION', 'dev'),

    /*
    |--------------------------------------------------------------------------
    | Konta: linki w e-mailach i powrót z logowania przez dostawcę
    |--------------------------------------------------------------------------
    |
    | Linki z e-maili i przekierowania po logowaniu prowadzą do aplikacji web,
    | więc API musi znać jej adres (WEB_URL, bez ukośnika na końcu).
    |
    */

    'web_url' => rtrim((string) env('WEB_URL', 'http://localhost:5173'), '/'),

    'account' => [
        // How long the link of an e-mail works, in seconds. It works once.
        'link_ttl_seconds' => 1800,

        // How long a driver counts as having just proved who they are, on the device that did it. Needed to unlink a sign-in.
        'reauth_ttl_seconds' => 600,

        // The pause after a change of the e-mail of the account before the next one can be asked for, in days.
        'email_change_cooldown_days' => 7,

        // How long the link "this was not me" in the notice to the old address works, in days. It can undo a change that was made.
        'email_undo_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Regulamin i polityka prywatności
    |--------------------------------------------------------------------------
    |
    | Teksty są w aplikacji web, a stąd biorą się liczby i dane, które się w nich
    | zmieniają. Próg wieku to decyzja prawna, nie techniczna: 16 lat to
    | ostrożna wartość robocza (najwyższy próg zgody cyfrowej w RODO), do
    | potwierdzenia po przeglądzie prawnym. Dane operatora uzupełnia wdrażający;
    | puste pola strony pokazują jako brakujące.
    |
    */

    'legal' => [
        // The age a driver confirms to have reached to have an account.
        'min_age' => (int) env('LEGAL_MIN_AGE', 16),

        // The day the texts were last changed. Bump it together with them: it is saved with the consent.
        'version' => env('LEGAL_VERSION', '2026-10-23'),

        // The day of the last change of the texts that needs a new consent. A driver who accepted an earlier version is asked again;
        // a smaller change bumps only the version above. Empty: every change of the version asks.
        'consent_version' => env('LEGAL_CONSENT_VERSION'),

        // Who runs the game. The terms and the privacy policy name them and say where to write to.
        'operator' => [
            'name' => env('LEGAL_OPERATOR_NAME'),
            'address' => env('LEGAL_OPERATOR_ADDRESS'),
            'email' => env('LEGAL_CONTACT_EMAIL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Web push
    |--------------------------------------------------------------------------
    |
    | The server sends to the address a browser gives it when it subscribes, so it only takes addresses on the hosts of the push services of the browsers (a host and every
    | name under it). PUSH_HOSTS replaces the list (comma separated) for a service that is not here.
    */
    'push' => [
        'hosts' => array_filter(array_map('trim', explode(',', (string) env('PUSH_HOSTS', 'fcm.googleapis.com,android.googleapis.com,push.services.mozilla.com,push.apple.com,notify.windows.com')))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Płatności za prawdziwe pieniądze
    |--------------------------------------------------------------------------
    |
    | Dostawca płatności, który sprzedaje paczki monet i przepustkę nieskończonego
    | tankowania. Żadnego jeszcze nie ma (krok P3 planu płatnych funkcji, potrzebny
    | jest użytkownik), więc `provider` jest pusty, a gra nie pokazuje przycisku
    | kupna przepustki (`purchasable: false` w GET /premium). Przepustkę może dać
    | obsługa poleceniem `premium:pass`.
    |
    */

    'premium' => [
        // `stripe` (the card provider, stage S2) or `fake` (a provider that takes no money, for development and tests; never in production). Empty: nothing is sold.
        'provider' => env('PREMIUM_PROVIDER'),

        'payments' => [
            // Test money in production (a demonstration installation), and live money outside it, are refused unless this says so: the mode comes from the key (sk_test_ / sk_live_).
            'allow_test' => (bool) env('PAYMENTS_ALLOW_TEST', false),

            // A page of a provider that nobody paid in this long is over, in minutes. Stripe allows 30 minutes to 24 hours after the page is made, so this is a little over the least.
            'attempt_minutes' => 35,

            // A driver who came back from the provider's page and was not told yet that they paid makes the game ask the provider after this many seconds.
            'reconcile_after_seconds' => 15,

            // Purchases are kept this many calendar years after the year they were made in (the books and the time claims can be made); then `purchases:prune` removes them.
            'retention_years' => 6,

            // What a game that says nothing about it has. `withdrawal`: `until_used` (a driver may withdraw within 14 days while the coins are not spent) or `waived` (the driver
            // agrees to get the coins at once and loses the right of withdrawal). That is the lawyer's choice, and the safer one for the shop is the first.
            'defaults' => [
                'withdrawal' => 'until_used',
                'limits' => ['per_day_minor' => 50_000, 'per_month_minor' => 150_000, 'max_open_attempts' => 3],
            ],

            'stripe' => [
                // The key of the account (a restricted key is enough). Never in the repository: the environment of the server only.
                'secret' => env('STRIPE_SECRET'),
                // The secret of the webhook endpoint (whsec_...); several, comma separated, while a secret is being changed.
                'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
                // How old a signed event may be, in seconds. Never 0.
                'webhook_tolerance' => 300,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Misje: granice wartości nagród
    |--------------------------------------------------------------------------
    |
    | Przedziały [od, do] dla miesiąca, w którym kierowca zagląda codziennie i robi
    | wszystkie misje wszystkich zestawów (średnia z losowania dla poziomu
    | `level`): ile z gotówki i ile z XP 30 baków to daje. Dolna granica pilnuje,
    | żeby misje były warte zrobienia (ponad szósta część tego, co zarabiają auta),
    | górna, żeby nie były całą grą (poniżej całości, a XP, które jest bramką gry,
    | poniżej połowy). To te same granice, których od 2026-10-30 pilnuje test misji
    | (`MissionsContentTest`), a nie osobna decyzja właściciela; zatwierdził on
    | poziomy 10% / 6% wartości pracy dla zestawów i 15% / 9% dla misji głównych.
    | `missions:budget --strict` pilnuje ich jako bramka publikacji (`PackGates`);
    | te same liczby w panelu to `MissionBudget`. Zmiana granic to decyzja o
    | ekonomii gry, nie o jednej paczce.
    |
    */

    'missions' => [
        'budget' => [
            'level' => 12,
            'cash_share' => [0.15, 1.0],
            'xp_share' => [0.05, 0.5],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Studio parametrów: tryb prosty
    |--------------------------------------------------------------------------
    |
    | Liczby reguł, które studio pokazuje w trybie prostym: te, które ustawiają
    | tempo i pieniądze gry. To wskazania w dokumencie (gwiazdka to jedna
    | nazwa: tor, produkt, szczebel), a wybór jest moją decyzją roboczą do
    | poprawienia. W trybie zaawansowanym są wszystkie liczby.
    |
    */

    'studio' => [
        'simple' => [
            '/fuel/price_cents_per_liter',
            '/repair/cost_cents',
            '/breakdown/chance_per_lap_ppm',
            '/tow/delay_ms',
            '/start/head_start_ms',
            '/clock/night_lap_ppm',
            '/player/other_cars_xp_ppm',
            '/player/max_level',
            '/car/xp_per_lap',
            '/car/xp_per_level',
            '/car/reward_ppm_from_tier/*',
            '/garage/capacity',
            '/garage/price_cents',
            '/travel/speed_share_ppm',
            '/travel/fuel_ml_per_km',
            '/wear/service/every_km',
            '/wear/repair_cost_ppm_of_price',
            '/bots/reward_ppm_by_position/*',
            '/market/resale_ppm',
            '/market/scrap_cents_per_kg',
            '/market/exchange/listings',
            '/market/exchange/refresh_ms',
            '/market/storage/units/*/price_cents',
            '/workshop/max_queue',
            '/workshop/time_cut_ppm_per_level',
            '/workshop/upgrade/base_time_ms',
            '/workshop/upgrade/cost_ppm_of_price',
            '/workshop/merge/base_ppm/*',
            '/workshop/bays/price_cents/*',
            '/workshop/mechanics/price_cents/*',
            '/tracks/*/min_player_level',
            '/tracks/*/speed_kmh',
            '/tracks/*/pay_cents_per_hour',
            '/tracks/*/xp_per_hour',
            '/track_model/fuel_ml_per_s/*',
            '/shop/crates/odds/standard/*',
            '/premium/speedup/minutes_per_coin',
            '/premium/products/*/coins',
            '/premium/products/*/price_minor',
            '/missions/periods/daily/bonus_coins',
            '/missions/periods/weekly/bonus_coins',
            '/missions/periods/monthly/bonus_coins',
            '/missions/periods/daily/cash_ppm',
            '/missions/periods/weekly/cash_ppm',
            '/missions/periods/monthly/cash_ppm',
            '/missions/periods/daily/xp_ppm',
            '/missions/periods/weekly/xp_ppm',
            '/missions/periods/monthly/xp_ppm',
            '/missions/main/cash_ppm',
            '/missions/main/xp_ppm',
        ],
    ],

];
