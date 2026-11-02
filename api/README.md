# Idlewright: API

Laravel 13: logika gry, rozliczanie, API pod `/api/v1` i panel operatora (Filament 5) pod `/admin`.

- Opis projektu, uruchomienie lokalne, testy i komendy: [README projektu](../README.md).
- Punkty końcowe i zasady API: [`docs/api-runs.md`](../docs/api-runs.md), rozliczanie: [`docs/settlement.md`](../docs/settlement.md), konto: [`docs/konto.md`](../docs/konto.md).
- Wskazówki dla agentów (Laravel Boost): [`CLAUDE.md`](CLAUDE.md) i [`AGENTS.md`](AGENTS.md).

```sh
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan serve        # http://127.0.0.1:8000
vendor/bin/pest          # testy, domyślnie SQLite w pamięci
```
