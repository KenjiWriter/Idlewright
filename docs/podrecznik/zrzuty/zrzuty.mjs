#!/usr/bin/env node
// Zrzuty podręcznika panelu, powtarzalne: jedno polecenie robi bazę z przykładowymi danymi, uruchamia serwer panelu, loguje się (z drugim składnikiem), wchodzi na każdą
// stronę z `strony.json`, wstrzykuje w nią numerowane znaczniki przy polach opisanych w legendzie, robi zrzut do `docs/img/podrecznik-<slug>.png` i wpisuje legendy
// (z katalogu pomocy panelu, nic napisanego osobno) do podręcznika `docs/podrecznik-panelu.md` między znacznikami <!-- legenda:<slug> --> i <!-- /legenda -->.
//
// Nie ma żadnych zależności: Node 22 lub nowszy (ma `WebSocket` i `fetch`), PHP z `api/`, przeglądarka Edge albo Chrome zainstalowana w systemie (sterowana protokołem
// DevTools). Użycie i opcje: `node docs/podrecznik/zrzuty/zrzuty.mjs --pomoc` albo docs/podrecznik/zrzuty/README.md.

import { spawn, spawnSync } from 'node:child_process';
import { createHmac } from 'node:crypto';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const tutaj = path.dirname(fileURLToPath(import.meta.url));
const korzen = path.resolve(tutaj, '../../..');
const api = path.join(korzen, 'api');
const katalogZrzutow = path.join(korzen, 'docs', 'img');
const podrecznik = path.join(korzen, 'docs', 'podrecznik-panelu.md');

// ---- opcje -------------------------------------------------------------------------------------------------------------------------------------------------------------

const opcje = { port: 8009, debug: 9333, baza: path.join(os.tmpdir(), 'idlewright-podrecznik.sqlite'), tylko: null, przygotuj: true, serwer: true, legendy: true, sonda: null, pomoc: false, przegladarka: process.env.PODRECZNIK_PRZEGLADARKA ?? null, szerokosc: 1440, wysokosc: 900 };

for (const argument of process.argv.slice(2)) {
    const [nazwa, wartosc] = argument.replace(/^--/, '').split('=');
    if (nazwa === 'pomoc') opcje.pomoc = true;
    else if (nazwa === 'bez-bazy') opcje.przygotuj = false;
    else if (nazwa === 'bez-serwera') opcje.serwer = false;
    else if (nazwa === 'bez-legend') opcje.legendy = false;
    else if (nazwa === 'tylko') opcje.tylko = wartosc.split(',');
    // Bez początkowego ukośnika (powłoka Git Bash zamienia go na ścieżkę dysku): admin/game, nie /admin/game.
    else if (nazwa === 'sonda') opcje.sonda = wartosc.split(',').map((strona) => '/' + strona.replace(/^.*?(admin)/, '$1').replace(/^\/+/, ''));
    else if (nazwa === 'port') opcje.port = Number(wartosc);
    else if (nazwa === 'baza') opcje.baza = path.resolve(wartosc);
    else if (nazwa === 'przegladarka') opcje.przegladarka = wartosc;
}

if (opcje.pomoc) {
    console.log(`Zrzuty podręcznika panelu.

  node docs/podrecznik/zrzuty/zrzuty.mjs [opcje]

  --tylko=a,b        tylko te ekrany (slug z strony.json)
  --bez-bazy         nie zakładaj bazy od nowa (użyj istniejącej, ${opcje.baza})
  --bez-serwera      nie uruchamiaj serwera (panel już działa pod --port)
  --bez-legend       nie wpisuj legend do podręcznika
  --sonda=admin/x    wypisz pola i przyciski strony (do pisania strony.json) i nic nie zapisuj
  --port=8009        port serwera panelu
  --baza=plik        plik bazy sqlite
  --przegladarka=exe Edge lub Chrome (domyślnie szuka sam; albo PODRECZNIK_PRZEGLADARKA)
`);
    process.exit(0);
}

const spij = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const adres = `http://127.0.0.1:${opcje.port}`;
const procesy = [];

function zatrzymaj() {
    for (const proces of procesy) {
        try {
            if (process.platform === 'win32') spawnSync('taskkill', ['/pid', String(proces.pid), '/T', '/F']);
            else proces.kill('SIGKILL');
        } catch {}
    }
}

process.on('exit', zatrzymaj);
process.on('SIGINT', () => process.exit(130));

// ---- baza i serwer -----------------------------------------------------------------------------------------------------------------------------------------------------

const srodowisko = {
    ...process.env,
    PAO_DISABLE: '1',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: opcje.baza,
    ACME_EMAIL: 'ops@example.com',
    SERVER_IPS: '203.0.113.10',
    APP_URL: adres,
    SESSION_DRIVER: 'file',
    CONTENT_SOURCE: 'database',
    PHP_CLI_SERVER_WORKERS: '4',
    PODRECZNIK_LEGENDY_WEJSCIE: path.join(tutaj, '.dane', 'legendy-wejscie.json'),
    PODRECZNIK_LEGENDY_WYJSCIE: path.join(tutaj, '.dane', 'legendy-wyjscie.json'),
};

function php(argumenty, dodatkowe = {}) {
    const wynik = spawnSync('php', argumenty, { cwd: api, env: { ...srodowisko, ...dodatkowe }, encoding: 'utf8' });
    if (wynik.status !== 0) {
        throw new Error(`php ${argumenty.join(' ')} nie wyszło:\n${wynik.stdout}\n${wynik.stderr}`);
    }
    return wynik.stdout;
}

fs.mkdirSync(path.join(tutaj, '.dane'), { recursive: true });

if (opcje.przygotuj) {
    console.log(`Zakładam bazę ${opcje.baza} i wypełniam ją przykładowymi danymi (to trwa, bo buduje paczkę przez bramki)...`);
    fs.writeFileSync(opcje.baza, '');
    php(['artisan', 'migrate:fresh', '--force']);
    console.log(php(['artisan', 'tinker', `--execute=require '${path.join(tutaj, 'przygotuj.php').replaceAll('\\', '/')}';`]).trim());
}

const seed = JSON.parse(fs.readFileSync(path.join(tutaj, '.dane', 'seed.json'), 'utf8'));

if (opcje.serwer) {
    const serwer = spawn('php', ['-S', `127.0.0.1:${opcje.port}`, path.join(api, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], { cwd: path.join(api, 'public'), env: srodowisko, stdio: 'ignore' });
    procesy.push(serwer);
}

for (let proba = 0; proba < 60; proba++) {
    try {
        const odpowiedz = await fetch(`${adres}/admin/login`, { redirect: 'manual' });
        if (odpowiedz.status < 500) break;
    } catch {}
    if (proba === 59) throw new Error(`Serwer panelu nie odpowiada pod ${adres}.`);
    await spij(500);
}

// ---- przeglądarka i protokół DevTools ---------------------------------------------------------------------------------------------------------------------------

function znajdzPrzegladarke() {
    if (opcje.przegladarka) return opcje.przegladarka;
    const kandydaci = [
        'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
        'C:/Program Files/Microsoft/Edge/Application/msedge.exe',
        'C:/Program Files/Google/Chrome/Application/chrome.exe',
        'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
        '/usr/bin/google-chrome',
        '/usr/bin/chromium',
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    ];
    const jest = kandydaci.find((plik) => fs.existsSync(plik));
    if (!jest) throw new Error('Nie znalazłem Edge ani Chrome: podaj --przegladarka=<plik> albo PODRECZNIK_PRZEGLADARKA.');
    return jest;
}

const profil = fs.mkdtempSync(path.join(os.tmpdir(), 'podrecznik-profil-'));
procesy.push(spawn(znajdzPrzegladarke(), ['--headless=new', `--remote-debugging-port=${opcje.debug}`, `--user-data-dir=${profil}`, '--no-first-run', '--no-default-browser-check', '--hide-scrollbars', '--disable-gpu', `--window-size=${opcje.szerokosc},${opcje.wysokosc}`, 'about:blank'], { stdio: 'ignore' }));

let cel = null;

for (let proba = 0; proba < 60 && !cel; proba++) {
    try {
        const lista = await (await fetch(`http://127.0.0.1:${opcje.debug}/json`)).json();
        cel = lista.find((wpis) => wpis.type === 'page') ?? null;
    } catch {}
    if (!cel) await spij(300);
}

if (!cel) throw new Error('Przeglądarka nie odpowiada na protokole DevTools.');

const gniazdo = new WebSocket(cel.webSocketDebuggerUrl);
await new Promise((resolve, reject) => {
    gniazdo.addEventListener('open', resolve, { once: true });
    gniazdo.addEventListener('error', reject, { once: true });
});

let numer = 0;
const oczekujace = new Map();
gniazdo.addEventListener('message', (wiadomosc) => {
    const dane = JSON.parse(wiadomosc.data);
    if (dane.id && oczekujace.has(dane.id)) {
        const { resolve, reject } = oczekujace.get(dane.id);
        oczekujace.delete(dane.id);
        dane.error ? reject(new Error(`${dane.error.message}`)) : resolve(dane.result);
    }
});

const wyslij = (metoda, parametry = {}) =>
    new Promise((resolve, reject) => {
        const id = ++numer;
        oczekujace.set(id, { resolve, reject });
        gniazdo.send(JSON.stringify({ id, method: metoda, params: parametry }));
    });

const oblicz = async (wyrazenie) => {
    const wynik = await wyslij('Runtime.evaluate', { expression: wyrazenie, awaitPromise: true, returnByValue: true });
    if (wynik.exceptionDetails) throw new Error(`JS: ${wynik.exceptionDetails.exception?.description ?? wynik.exceptionDetails.text}`);
    return wynik.result.value;
};

await wyslij('Page.enable');
await wyslij('Runtime.enable');
await wyslij('Emulation.setDeviceMetricsOverride', { width: opcje.szerokosc, height: opcje.wysokosc, deviceScaleFactor: 1, mobile: false });

async function otworz(sciezka) {
    await wyslij('Page.navigate', { url: sciezka.startsWith('http') ? sciezka : adres + sciezka });
    await spij(600);
    for (let proba = 0; proba < 80; proba++) {
        if ((await oblicz('document.readyState')) === 'complete') break;
        await spij(150);
    }
    await spij(900);
}

async function czekaj(selektor, limit = 20000) {
    const start = Date.now();
    while (Date.now() - start < limit) {
        if (await oblicz(`!!document.querySelector(${JSON.stringify(selektor)})`)) return true;
        await spij(200);
    }
    const strona = await oblicz("location.pathname + ' | ' + document.body.innerText.replace(/\s+/g, ' ').slice(0, 240)").catch(() => '?');
    throw new Error(`Nie doczekałem się elementu ${selektor}. Strona: ${strona}`);
}

async function wpisz(tekst) {
    for (const znak of tekst) {
        await wyslij('Input.dispatchKeyEvent', { type: 'keyDown', text: znak, unmodifiedText: znak, key: znak });
        await wyslij('Input.dispatchKeyEvent', { type: 'keyUp', key: znak });
        await spij(25);
    }
}

async function enter() {
    for (const typ of ['keyDown', 'keyUp']) {
        await wyslij('Input.dispatchKeyEvent', { type: typ, key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13, text: typ === 'keyDown' ? '\r' : undefined });
    }
}

// ---- hasło jednorazowe (RFC 6238, SHA-1, 30 s, 6 cyfr) ---------------------------------------------------------------------------------------------------

function kod2fa(sekret) {
    const alfabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bity = '';
    for (const znak of sekret.replace(/=+$/, '')) bity += alfabet.indexOf(znak).toString(2).padStart(5, '0');
    const bajty = Buffer.from(bity.match(/.{8}/g).map((osiem) => parseInt(osiem, 2)));
    const licznik = Buffer.alloc(8);
    licznik.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30000)));
    const skrot = createHmac('sha1', bajty).update(licznik).digest();
    const przesuniecie = skrot[skrot.length - 1] & 0xf;
    const liczba = ((skrot[przesuniecie] & 0x7f) << 24) | (skrot[przesuniecie + 1] << 16) | (skrot[przesuniecie + 2] << 8) | skrot[przesuniecie + 3];
    return String(liczba % 1_000_000).padStart(6, '0');
}

async function zaloguj() {
    await otworz('/admin/login');
    await czekaj('input[type=email]');
    await oblicz("document.querySelector('input[type=email]').focus()");
    await wpisz('boss@example.com');
    await oblicz("document.querySelector('input[type=password]').focus()");
    await wpisz('a-long-enough-password');
    await enter();
    // Drugi składnik: sześć pól na znak; blisko końca trzydziestosekundowego okna czekamy na następne, żeby kod nie wygasł w trakcie.
    await czekaj('input[autocomplete=one-time-code], input[aria-label*="Znak 2"], input[aria-label*="Character 2"]');
    if (Date.now() % 30000 > 26000) await spij(30000 - (Date.now() % 30000) + 500);
    await oblicz("(document.querySelector('input[aria-label*=\"Znak 2\"]') ? document.querySelector('input[aria-label*=\"Znak 2\"]').closest('div').parentElement.querySelector('input') : document.querySelector('input[autocomplete=one-time-code]')).focus()");
    await wpisz(kod2fa(seed.secret));
    await enter();
    for (let proba = 0; proba < 60; proba++) {
        if (!(await oblicz('location.pathname')).includes('login')) return;
        await spij(300);
    }
    throw new Error('Nie udało się zalogować (drugi składnik?).');
}

// ---- znaczniki i zrzut --------------------------------------------------------------------------------------------------------------------------------------------------

const wstrzyknijZnaczniki = (znaczniki) => `(() => {
    document.querySelectorAll('[data-podrecznik]').forEach((e) => e.remove());
    const wynik = [];
    const widoczny = (e) => { const r = e.getBoundingClientRect(); return r.width > 0 && r.height > 0 && getComputedStyle(e).visibility !== 'hidden'; };
    ${JSON.stringify(znaczniki)}.forEach((z, i) => {
        let kandydaci = [...document.querySelectorAll(z.selektor)].filter(widoczny);
        // "dokladnie": cały tekst elementu to ten napis (nagłówek, a nie okno, które go zawiera, ani wiersz, który tylko go powtarza).
        if (z.zawiera) kandydaci = kandydaci.filter((e) => { const tekst = (e.innerText || e.value || '').trim().toLowerCase(); return z.dokladnie ? tekst === z.zawiera.toLowerCase() : tekst.includes(z.zawiera.toLowerCase()); });
        // "najglebszy": element z tym tekstem, który nie ma w sobie innego z tym tekstem (nagłówek, a nie okno, które go zawiera).
        const e = z.najglebszy ? kandydaci[kandydaci.length - 1 - (z.ktory ?? 0)] : kandydaci[z.ktory ?? 0];
        if (!e) { wynik.push({ numer: i + 1, brak: true }); return; }
        const r = e.getBoundingClientRect();
        const x = r.left + scrollX, y = r.top + scrollY;
        const ramka = document.createElement('div');
        ramka.setAttribute('data-podrecznik', '1');
        ramka.style.cssText = 'position:absolute;pointer-events:none;z-index:99999;box-sizing:border-box;border:3px solid #ef4444;border-radius:6px;left:' + (x - 3) + 'px;top:' + (y - 3) + 'px;width:' + (r.width + 6) + 'px;height:' + (r.height + 6) + 'px;';
        const znak = document.createElement('div');
        znak.setAttribute('data-podrecznik', '1');
        znak.textContent = String(i + 1);
        znak.style.cssText = 'position:absolute;pointer-events:none;z-index:100000;width:26px;height:26px;border-radius:50%;background:#ef4444;color:#fff;font:700 14px/26px system-ui,sans-serif;text-align:center;box-shadow:0 1px 4px rgba(0,0,0,.5);left:' + Math.max(2, x - 14) + 'px;top:' + Math.max(2, y - 14) + 'px;';
        document.body.append(ramka, znak);
        wynik.push({ numer: i + 1, tekst: (e.innerText || e.value || e.getAttribute('aria-label') || '').trim().slice(0, 80) });
    });
    return wynik;
})()`;

async function krok(akcja) {
    if (akcja.kliknij) {
        await czekaj(akcja.kliknij);
        if (akcja.zawiera) await oblicz(`[...document.querySelectorAll(${JSON.stringify(akcja.kliknij)})].find((e) => (e.innerText || '').toLowerCase().includes(${JSON.stringify(akcja.zawiera.toLowerCase())}))?.click()`);
        else await oblicz(`document.querySelector(${JSON.stringify(akcja.kliknij)}).click()`);
    } else if (akcja.wpisz) {
        await czekaj(akcja.wpisz.selektor);
        await oblicz(`document.querySelector(${JSON.stringify(akcja.wpisz.selektor)}).focus()`);
        await oblicz(`document.querySelector(${JSON.stringify(akcja.wpisz.selektor)}).select?.()`);
        await wpisz(akcja.wpisz.tekst === '@odpowiedz' ? seed.odpowiedz : akcja.wpisz.tekst === '@odpowiedz_z_bledem' ? seed.odpowiedz_z_bledem : akcja.wpisz.tekst);
    } else if (akcja.ustaw) {
        await czekaj(akcja.ustaw.selektor);
        const tekst = akcja.ustaw.tekst === '@odpowiedz' ? seed.odpowiedz : akcja.ustaw.tekst === '@odpowiedz_z_bledem' ? seed.odpowiedz_z_bledem : akcja.ustaw.tekst;
        await oblicz(`(() => { const e = document.querySelector(${JSON.stringify(akcja.ustaw.selektor)}); e.value = ${JSON.stringify(tekst)}; e.dispatchEvent(new Event('input', { bubbles: true })); e.dispatchEvent(new Event('change', { bubbles: true })); })()`);
    } else if (akcja.czekaj) {
        await czekaj(akcja.czekaj);
    } else if (akcja.pauza) {
        await spij(akcja.pauza);
    } else if (akcja.js) {
        await oblicz(akcja.js);
    } else if (akcja.przewin) {
        await oblicz(`document.querySelector(${JSON.stringify(akcja.przewin)})?.scrollIntoView({ block: 'center' })`);
    }
    await spij(akcja.pauza_po ?? 500);
}

const braki = [];
const wszystkieLegendy = new Map();

async function zrobEkran(ekran) {
    console.log(`- ${ekran.slug}`);
    if (!ekran.bez_logowania) await otworz(ekran.url);
    else {
        await wyslij('Network.enable');
        await wyslij('Network.clearBrowserCookies');
        await otworz(ekran.url);
    }
    if (ekran.czekaj) await czekaj(ekran.czekaj);
    for (const akcja of ekran.kroki ?? []) await krok(akcja);
    const znaczniki = (ekran.znaczniki ?? []).map((z) => ({ selektor: z.selektor, zawiera: z.zawiera ?? null, ktory: z.ktory ?? 0, najglebszy: z.najglebszy ?? false, dokladnie: z.dokladnie ?? false }));
    const wynik = znaczniki.length ? await oblicz(wstrzyknijZnaczniki(znaczniki)) : [];
    for (const wpis of wynik) {
        if (wpis.brak) braki.push(`${ekran.slug}: znacznik ${wpis.numer} (${ekran.znaczniki[wpis.numer - 1].selektor}) nie znalazł elementu`);
    }
    wszystkieLegendy.set(ekran.slug, { ekran, wynik });
    const wysokosc = Math.min(await oblicz('Math.max(document.documentElement.scrollHeight, document.body.scrollHeight)'), ekran.maks_wysokosc ?? 1900);
    const zrzut = await wyslij('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true, clip: { x: 0, y: 0, width: opcje.szerokosc, height: Math.max(wysokosc, opcje.wysokosc), scale: 1 } });
    fs.mkdirSync(katalogZrzutow, { recursive: true });
    fs.writeFileSync(path.join(katalogZrzutow, `podrecznik-${ekran.slug}.png`), Buffer.from(zrzut.data, 'base64'));
}

// ---- sonda: co jest na stronie (do pisania strony.json) --------------------------------------------------------------------------------------------------

if (opcje.sonda) {
    await zaloguj();
    for (const strona of opcje.sonda) {
    await otworz(strona);
    console.log('=== ' + strona);
    const opis = await oblicz(`JSON.stringify({
        tytul: document.title,
        pola: [...document.querySelectorAll('[data-help]')].map((e) => ({ pomoc: e.getAttribute('data-help'), tag: e.tagName.toLowerCase(), tekst: (e.innerText || '').trim().slice(0, 40) })),
        przyciski: [...document.querySelectorAll('button, a[role=button], .fi-btn')].map((e) => (e.innerText || e.getAttribute('aria-label') || '').trim()).filter(Boolean).slice(0, 40),
        zakladki: [...document.querySelectorAll('[role=tab]')].map((e) => (e.innerText || '').trim()),
        naglowki: [...document.querySelectorAll('h1, h2, h3')].map((e) => (e.innerText || '').trim()).filter(Boolean).slice(0, 20),
        tabela: [...document.querySelectorAll('table th')].map((e) => (e.innerText || '').trim()).filter(Boolean).slice(0, 20),
    }, null, 1)`);
    console.log(opis);
    }
    process.exit(0);
}

// ---- zrzuty -----------------------------------------------------------------------------------------------------------------------------------------------------------------

const manifest = JSON.parse(fs.readFileSync(path.join(tutaj, 'strony.json'), 'utf8'));

// Znacznik z kluczem pomocy nie potrzebuje selektora: pole formularza ma w stronie atrybut data-help z tym kluczem.
for (const ekran of manifest.ekrany) {
    for (const znacznik of ekran.znaczniki ?? []) {
        if (znacznik.pomoc && !znacznik.selektor) znacznik.selektor = `[data-help="${znacznik.pomoc}"]`;
    }
}

const wybrane = manifest.ekrany.filter((ekran) => !opcje.tylko || opcje.tylko.includes(ekran.slug));
const bezLogowania = wybrane.filter((ekran) => ekran.bez_logowania);
const zLogowaniem = wybrane.filter((ekran) => !ekran.bez_logowania);

console.log('Robię zrzuty:');
for (const ekran of bezLogowania) {
    try { await zrobEkran(ekran); } catch (blad) { braki.push(`${ekran.slug}: ${blad.message}`); }
}

if (zLogowaniem.length) {
    await wyslij('Network.enable');
    await wyslij('Network.clearBrowserCookies');
    await zaloguj();
    for (const ekran of zLogowaniem) {
        try { await zrobEkran(ekran); } catch (blad) { braki.push(`${ekran.slug}: ${blad.message}`); }
    }
}

// ---- legendy: z katalogu pomocy, do podręcznika -------------------------------------------------------------------------------------------------------------------

if (opcje.legendy) {
    const klucze = { help: new Set(), lang: new Set() };
    for (const { ekran } of wszystkieLegendy.values()) {
        for (const znacznik of ekran.znaczniki ?? []) {
            if (znacznik.pomoc) klucze.help.add(znacznik.pomoc);
            if (znacznik.tlumaczenie) klucze.lang.add(znacznik.tlumaczenie);
        }
    }
    fs.writeFileSync(srodowisko.PODRECZNIK_LEGENDY_WEJSCIE, JSON.stringify({ help: [...klucze.help], lang: [...klucze.lang] }));
    php(['artisan', 'tinker', `--execute=require '${path.join(tutaj, 'legendy.php').replaceAll('\\', '/')}';`]);
    const katalog = JSON.parse(fs.readFileSync(srodowisko.PODRECZNIK_LEGENDY_WYJSCIE, 'utf8'));

    const zdanie = (tekst) => String(tekst).trim().replace(/\s+/g, ' ');
    // Zdanie kończy się kropką (opisy w katalogu pomocy bywają bez niej), a opis przycisku zaczyna się wielką literą.
    const kropka = (tekst) => (/[.!?…:]$/.test(zdanie(tekst)) ? zdanie(tekst) : `${zdanie(tekst)}.`);
    const wielka = (tekst) => zdanie(tekst).replace(/^./, (litera) => litera.toLocaleUpperCase('pl'));
    let tresc = fs.existsSync(podrecznik) ? fs.readFileSync(podrecznik, 'utf8') : '';

    for (const [slug, { ekran, wynik }] of wszystkieLegendy) {
        const wiersze = (ekran.znaczniki ?? []).map((znacznik, indeks) => {
            const znaleziony = wynik[indeks];
            if (!znaleziony || znaleziony.brak) return null;
            if (znacznik.pomoc) {
                const wpis = katalog.help[znacznik.pomoc];
                if (!wpis) { braki.push(`${slug}: brak wpisu pomocy ${znacznik.pomoc}`); return null; }
                const czesci = [`**${zdanie(wpis.label)}.** ${kropka(wpis.help ?? '')}`];
                if (wpis.range) czesci.push(`Zakres: ${kropka(wpis.range)}`);
                if (wpis.example) czesci.push(`Przykład: ${kropka(wpis.example)}`);
                if (wpis.tip) czesci.push(`Wskazówka: ${kropka(wpis.tip)}`);
                return `${indeks + 1}. ${czesci.join(' ')}`;
            }
            if (znacznik.tlumaczenie) {
                const tekst = katalog.lang[znacznik.tlumaczenie];
                if (!tekst) { braki.push(`${slug}: brak tłumaczenia ${znacznik.tlumaczenie}`); return null; }
                return `${indeks + 1}. ${znacznik.nazwa ? `**${zdanie(znacznik.nazwa)}.** ` : ''}${kropka(tekst)}`;
            }
            return `${indeks + 1}. **${zdanie(znacznik.nazwa ?? znaleziony.tekst)}.**${znacznik.opis ? ` ${kropka(wielka(znacznik.opis))}` : ''}`;
        }).filter(Boolean);

        const wzorzec = new RegExp(`(<!-- legenda:${slug} -->)[\\s\\S]*?(<!-- /legenda -->)`);
        if (wzorzec.test(tresc)) tresc = tresc.replace(wzorzec, (_, otwarcie, zamkniecie) => `${otwarcie}\n${wiersze.join('\n')}\n${zamkniecie}`);
        else console.log(`  (w podręczniku nie ma miejsca na legendę ${slug}: dodaj <!-- legenda:${slug} --> i <!-- /legenda -->)`);
    }

    if (tresc !== '') fs.writeFileSync(podrecznik, tresc);
}

if (braki.length) {
    console.error('\nCo się nie udało (strona się zmieniła? popraw strony.json):');
    for (const brak of braki) console.error(`  - ${brak}`);
    process.exitCode = 1;
} else {
    console.log(`\nGotowe: ${wybrane.length} zrzutów w docs/img/podrecznik-*.png.`);
}

process.exit(process.exitCode ?? 0);
