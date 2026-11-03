<?php

// Wypełnia pustą bazę tym, co pokazują zrzuty podręcznika panelu: treść gry z repozytorium jako paczka na żywo, administrator, który publikuje (z drugim składnikiem
// logowania; sekret jest tylko do zrzutów), druga gra z szablonu, gracze, dwa szkice, paczka zbudowana i czekająca na uruchomienie, odpowiedź AI do wklejenia na stronie „Most AI”.
//
// Uruchamia się go z katalogu `api` po `php artisan migrate:fresh`, przez `php artisan tinker --execute="require '<ścieżka>';"` (robi to `zrzuty.mjs`). Dane, które musi
// znać skrypt zrzutów (kod instrukcji i tekst odpowiedzi), zapisuje do `.dane/seed.json` obok siebie.

use App\Content\Bridge\BriefRecorder;
use App\Content\Bridge\BriefSelection;
use App\Content\FileContentSource;
use App\Models\Admin;
use App\Models\ContentDefinition;
use App\Models\User;
use App\Services\ContentDrafts;
use App\Services\ContentPacks;
use App\Services\ContentPublisher;
use App\Services\GameCreator;

const PODRECZNIK_SEKRET_2FA = 'JBSWY3DPEHPK3PXP';

// 1. Treść gry z repozytorium jest paczką na żywo.
app(ContentPacks::class)->import(new FileContentSource((string) config('ride.content_path')), reason: 'podręcznik', activate: true, checkTables: false);

// 2. Administrator, który publikuje, w każdej grze.
$admin = Admin::query()->updateOrCreate(['email' => 'boss@example.com'], ['name' => 'Maciej', 'password' => 'a-long-enough-password', 'role' => Admin::ROLE_PUBLISHER, 'all_games' => true, 'is_active' => true]);
$admin->saveAppAuthenticationSecret(PODRECZNIK_SEKRET_2FA);
$admin->saveAppAuthenticationRecoveryCodes(['abcd-1234']);

// 3. Druga gra z szablonu, żeby sieć miała co pokazać.
try {
    app(GameCreator::class)->create(['slug' => 'statki', 'name' => 'Statki', 'domain' => 'statki.example.test', 'locale' => 'pl', 'theme_color' => '#0284c7'], GameCreator::START_TEMPLATE, by: $admin);
} catch (Throwable $problem) {
    fwrite(STDERR, 'Druga gra się nie założyła: '.$problem->getMessage()."\n");
}

// 4. Gracze.
User::factory()->count(14)->create();

// 5. Dwa szkice: cena silnika i cena konta VIP.
$drafts = app(ContentDrafts::class);
$part = ContentDefinition::query()->where('kind', 'part')->where('slug', 'engine-v6-2-5-t3')->firstOrFail();
$document = $part->workingDocument();
$document['price_cents'] = intdiv($document['price_cents'] * 11, 10);
$drafts->save($part, $document, $admin);

$rules = ContentDefinition::query()->where('kind', 'rules')->where('slug', 'starter')->firstOrFail();
$document = $rules->workingDocument();
$document['premium']['vip']['coins'] = 120;
$drafts->save($rules, $document, $admin);

// 6. Paczka zbudowana z tych szkiców, która czeka na uruchomienie (trwa kilkadziesiąt sekund: to bramki i symulatory).
if (getenv('PODRECZNIK_BEZ_PACZKI') !== '1') {
    $publisher = app(ContentPublisher::class);
    $publisher->build($publisher->queue($admin, 'Przykładowa paczka do podręcznika'));
}

// 6b. Trzeci szkic, zapisany PO zrobieniu paczki: cena Rdzaka GT-4 o 5% wyżej. „Zmiany do publikacji” porównują szkic z ostatnio opublikowaną wersją dokumentu, więc dwa pierwsze
// szkice (które paczka już zawiera) pokazują „bez różnic”, a ten pokazuje prawdziwą zmianę „było → będzie”.
$vehicle = ContentDefinition::query()->where('kind', 'vehicle')->where('slug', 'rdzak-gt-4')->firstOrFail();
$document = $vehicle->workingDocument();
$document['price_cents'] = intdiv($document['price_cents'] * 105, 100);
$drafts->save($vehicle, $document, $admin);

// 7. Instrukcja dla AI i odpowiedź, którą asystent mógłby na nią dać (do wklejenia na stronie „Most AI”).
$brief = app(BriefRecorder::class)->prepare(new BriefSelection(locale: 'pl'), $admin);
$game = \App\Models\Game::query()->where('is_default', true)->firstOrFail();
$answer = "Rozumiem: wszystkie silniki tieru 3 mają mieć 12% więcej mocy, a konto VIP ma kosztować 110 monet. Oto blok:\n\n```ride-changes\n".json_encode([
    'format' => 'ride-changes/1',
    'game' => $game->slug,
    'base' => ['pack' => $brief->pack, 'snapshot' => $brief->snapshot, 'brief' => $brief->code],
    'summary' => 'Silniki tieru 3 mocniejsze o 12%, VIP za 110 monet',
    'ops' => [
        ['op' => 'scale', 'id' => 'silniki', 'select' => ['kind' => 'part', 'where' => ['slot' => 'engine', 'tier' => 3]], 'path' => '/stats/power_kw', 'percent' => 12],
        ['op' => 'set', 'id' => 'vip', 'target' => ['rules' => 'starter'], 'path' => '/premium/vip/coins', 'value' => 110, 'unit' => 'plain'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n```\n";

$wrong = str_replace('"unit": "plain"', '"unit": "percent"', $answer);

$directory = __DIR__.'/.dane';

if (! is_dir($directory)) {
    mkdir($directory, 0777, true);
}

file_put_contents($directory.'/seed.json', json_encode(['secret' => PODRECZNIK_SEKRET_2FA, 'odpowiedz' => $answer, 'odpowiedz_z_bledem' => $wrong, 'brief' => $brief->code], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Przygotowane: administrator boss@example.com, gra ".$game->slug.", instrukcja {$brief->code}.\n";
