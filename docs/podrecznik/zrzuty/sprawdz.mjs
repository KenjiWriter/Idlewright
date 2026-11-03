#!/usr/bin/env node
// Sprawdza spójność podręcznika `docs/podrecznik-panelu.md`: spis treści prowadzi do nagłówków, każdy obrazek istnieje i jest użyty raz, każdy ekran z `strony.json` ma obrazek
// i wypełnioną legendę, numeracja rozdziałów jest ciągła, odnośniki do rozdziałów wskazują rozdziały, które są, a odnośniki do plików prowadzą do plików.
// Kończy się kodem 1 i listą, gdy coś się nie zgadza. Nie robi zrzutów i nie potrzebuje serwera: `node docs/podrecznik/zrzuty/sprawdz.mjs`.

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const tutaj = path.dirname(fileURLToPath(import.meta.url));
const docs = path.resolve(tutaj, '../..');
const plik = path.join(docs, 'podrecznik-panelu.md');
const tresc = fs.readFileSync(plik, 'utf8');
const manifest = JSON.parse(fs.readFileSync(path.join(tutaj, 'strony.json'), 'utf8'));
const bledy = [];

const kotwica = (tekst) => tekst.toLowerCase().replace(/[^\p{L}\p{N}\s-]/gu, '').replace(/\s/g, '-');

// Nagłówki i spis treści.
const naglowki = [...tresc.matchAll(/^(#{2,3}) (.+)$/gm)].map((m) => ({ poziom: m[1].length, tekst: m[2].trim() }));
const kotwice = new Set(naglowki.map((n) => kotwica(n.tekst)));
const rozdzialy = naglowki.filter((n) => n.poziom === 2 && /^\d+\. /.test(n.tekst)).map((n) => Number(n.tekst.match(/^(\d+)\./)[1]));
rozdzialy.forEach((numer, i) => { if (numer !== i + 1) bledy.push(`numeracja rozdziałów: na pozycji ${i + 1} stoi rozdział ${numer}`); });

for (const [, cel] of tresc.matchAll(/\]\(#([^)]+)\)/g)) {
    if (!kotwice.has(cel)) bledy.push(`odnośnik do nagłówka, którego nie ma: #${cel}`);
}
const spis = tresc.slice(tresc.indexOf('## Spis treści'), tresc.indexOf('## Najważniejsza zasada'));
for (const n of naglowki.filter((x) => x.poziom === 2 && (/^\d+\. /.test(x.tekst) || x.tekst.startsWith('Dodatek')))) {
    if (!spis.includes(`(#${kotwica(n.tekst)})`)) bledy.push(`rozdziału nie ma w spisie treści: ${n.tekst}`);
}

// Numery „rozdział N” w tekście wskazują rozdziały, które są (także „rozdziały N i M” i „N–M”).
const ile = rozdzialy.length;
for (const m of tresc.matchAll(/rozdział(?:y|ach|u|ów)? (\d+)(?:(?: i |–|, )(\d+))?/g)) {
    for (const numer of [m[1], m[2]].filter(Boolean).map(Number)) {
        if (numer < 1 || numer > ile) bledy.push(`odnośnik do rozdziału, którego nie ma: „${m[0]}”`);
    }
}

// Obrazki: istnieją, każdy użyty raz, a każdy ekran z manifestu ma obrazek i legendę.
const uzyte = new Map();
for (const [, sciezka] of tresc.matchAll(/!\[[^\]]*\]\((img\/[^)]+)\)/g)) {
    uzyte.set(sciezka, (uzyte.get(sciezka) ?? 0) + 1);
    if (!fs.existsSync(path.join(docs, sciezka))) bledy.push(`brak pliku obrazka: ${sciezka}`);
}
for (const [sciezka, razy] of uzyte) if (razy > 1) bledy.push(`obrazek użyty ${razy} razy: ${sciezka}`);

for (const ekran of manifest.ekrany) {
    const obrazek = `img/podrecznik-${ekran.slug}.png`;
    if (!fs.existsSync(path.join(docs, obrazek))) bledy.push(`ekran ${ekran.slug}: brak pliku ${obrazek}`);
    if (!uzyte.has(obrazek)) bledy.push(`ekran ${ekran.slug}: obrazka nie ma w podręczniku`);
    const legenda = tresc.match(new RegExp(`<!-- legenda:${ekran.slug} -->\\n([\\s\\S]*?)<!-- /legenda -->`));
    if (!legenda) { bledy.push(`ekran ${ekran.slug}: brak bloku legendy`); continue; }
    const wiersze = legenda[1].trim() === '' ? [] : legenda[1].trim().split('\n');
    const oczekiwane = (ekran.znaczniki ?? []).length;
    if (wiersze.length !== oczekiwane) bledy.push(`ekran ${ekran.slug}: legenda ma ${wiersze.length} wierszy, a znaczników jest ${oczekiwane}`);
    wiersze.forEach((wiersz, i) => { if (!wiersz.startsWith(`${i + 1}. `)) bledy.push(`ekran ${ekran.slug}: wiersz ${i + 1} legendy nie zaczyna się od numeru`); });
}
for (const [, slug] of tresc.matchAll(/^<!-- legenda:([a-z0-9-]+) -->$/gm)) {
    if (!manifest.ekrany.some((e) => e.slug === slug)) bledy.push(`legenda bez ekranu w manifeście: ${slug}`);
}

// Odnośniki do plików (poza obrazkami i kotwicami) prowadzą do plików.
for (const [, cel] of tresc.matchAll(/(?<!!)\]\(((?!https?:|#|mailto:)[^)#]+)(?:#[^)]*)?\)/g)) {
    if (!fs.existsSync(path.resolve(docs, cel))) bledy.push(`odnośnik do pliku, którego nie ma: ${cel}`);
}

// Pozostałości po pisaniu.
for (const wzor of [/TODO/, /XXX/, /\bFIXME\b/, /\.\*\*[^\s\n]/]) {
    const m = tresc.match(wzor);
    if (m) bledy.push(`podejrzany fragment (${wzor}): „${tresc.slice(Math.max(0, m.index - 30), m.index + 40).replace(/\n/g, ' ')}”`);
}

if (bledy.length) {
    console.error(`Podręcznik ma ${bledy.length} niezgodności:`);
    for (const blad of bledy) console.error(`  - ${blad}`);
    process.exit(1);
}
console.log(`Podręcznik jest spójny: ${ile} rozdziałów, ${uzyte.size} obrazków, ${manifest.ekrany.length} ekranów z legendami.`);
