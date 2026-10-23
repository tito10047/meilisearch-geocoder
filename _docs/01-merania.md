# Merania

Vývojové PC: 16 jadier, 30 GB RAM, Fedora, Docker 29.8, btrfs. Cieľový server (Tankovač):
8 jadier, 8 GB RAM (~5,4 GB voľných), bez swapu, Ubuntu, kontajner (vpsAdminOS / ZFS).

## Metodika

- Každá služba v Dockeri s **tvrdým limitom** `--memory=X --memory-swap=X`, `--cpus=2`.
- „Vlastná RAM“ = `anon` z `/sys/fs/cgroup/.../memory.stat` kontajnera (`_lab/scripts/mem.sh`).
  `file` = page cache (mmap súborov indexu) — jadro ju pri nedostatku vezme späť, preto
  sa nepočíta ako spotreba. `memory.peak` zahŕňa oboje.
- Pozor: page cache súboru, ktorý predtým čítal iný proces (napr. build), sa pripisuje tomu
  pôvodnému cgroupu — preto Valhalla ukazovala `file=0`. Na serveri sa pripíše pri prvom čítaní.
- Záťaž: `bench-meili.py` (prefixy reálnych dotazov, 4 súbežne, s dohľadaním čísla domu
  v SQLite), `bench-photon.py`, `bench-valhalla.py` (30 miest v regióne, 70 % s alternatívami,
  30 % so zastávkou, `admin_crossings`).

## Photon 1.3.0 (Java 21 + embedded OpenSearch) — vyradený

| | |
|---|---|
| Import SK z JSON výpisu | 3 min 5 s, DB 794 MB |
| Halda 256 MB, limit 600 MB | **anon ~380 MiB** pod záťažou, peak 403 MiB |
| Rýchlosť | medián 18 ms, p95 33 ms (400 dotazov, 4 súbežne) |
| Halda 128 MB, limit 300 MB | **OOM** hneď po štarte záťaže |

Kvalita: „Hlavná 12 Košice“ nevrátil číslo 12 (len ulice Hlavná inde). Pre 9 krajín by
pamäť ďaleko presiahla rozpočet.

## Meilisearch v1.35.1 + SQLite

Verzia 1.35.1 = verzia na produkčnom serveri Tankovača (lokálne je inak 1.48.3).

### Slovensko, prvý pokus (bez optimalizácie)

| | |
|---|---|
| Dokumentov | 251 815 (obce 105 tis. vrátane lokalít, POI 100 tis., ulice 48 tis.) |
| Indexovanie | 16 s, peak 764 MiB (vrátane cache), index 306 MB |
| Hľadanie, limit 1 GB | **anon 46 MiB**, medián 2,1 ms, p95 3,6 ms (600 dotazov) |
| SQLite | 1,63 mil. čísel domov, 77 MB (114 MB s indexom) |

### Pôvodný index `geo` — 8 krajín (bez IT), žiadna optimalizácia

| Po krajine | Dokumentov spolu | Index | anon po indexovaní |
|---|---|---|---|
| SK | 0,25 mil. | 306 MB | 358 MiB |
| + CZ | 0,63 mil. | 961 MB | 489 MiB |
| + AT | 1,15 mil. | 1,8 GB | 668 MiB |
| + HU | **OOM pri limite 1 GB** | | |
| (bez limitu) + HU, PL, HR, SI | 2,78 mil. | 4,2 GB | ~2,06 GB |
| + DE (12,6 min) | **6,37 mil.** | **11 GB** | 2,36 GB (peak 12,3 GB vrátane cache) |

`MEILI_MAX_INDEXING_MEMORY=400Mb` rast pamäte nezastavil.

### Optimalizovaný index — 9 krajín

| | `geo2` (filtrovanie obsahu + nastavenia) | **`geo4` (finálny: + dedup, mená, ranking)** |
|---|---|---|
| Dokumentov | 4,43 mil. | **4,42 mil.** |
| Indexovanie | 116 s | ~93–149 s |
| Index | 3,6 GB | **3,8 GB** |
| anon pri indexovaní | ~2,0 GB (peak 6 GB vrátane cache) | podobne |

`geo4`, hľadanie (`bench-meili.py`, 4 súbežne, číslo domu z `houses.sqlite` 44 mil. riadkov):

| Limit | anon | Medián | p95 | max |
|---|---|---|---|---|
| 300 MB | 48 MiB | 2,5 ms | 6,9 ms | 268 ms |
| 200 MB (súbežne s Valhallou) | 48 MiB | 2,4 ms | 20,7 ms | 83 ms |
| **150 MB** | 53 MiB | 2,6 ms | 11,9 ms | 80 ms |

Pri 150 MB je p95 horší len kvôli menšej page cache. Spodnú hranicu som nehľadal.

### Konverzia (`convert.php`, PHP 8.5, z `zstd -dc` cez stdin)

| Krajina | Obce | POI | Ulice | Čísla domov | PHP peak |
|---|---|---|---|---|---|
| SK | 5 974 | 7 387 | 48 393 | 1 633 630 | 49 MiB |
| CZ | 15 123 | 16 457 | 138 997 | 4 136 980 | 133 MiB |
| AT | 23 476 | 28 048 | 238 781 | 2 543 097 | 219 MiB |
| HU | 8 234 | 12 932 | 145 676 | 797 897 | 124 MiB |
| PL | 114 215 | 33 879 | 448 620 | 8 647 983 | 551 MiB |
| HR | 20 682 | 8 696 | 49 153 | 268 813 | 78 MiB |
| SI | 7 083 | 3 246 | 28 000 | 446 161 | 34 MiB |
| DE | 99 646 | 115 873 | 1 628 103 | 21 034 017 | 1 328 MiB |
| IT | 75 370 | 71 785 | 1 031 014 | 4 687 430 | 867 MiB |

Pamäť PHP rastie s počtom ulíc (držia sa v poli do konca kvôli deduplikácii) — v knižnici
treba riešiť inak (napr. dočasná SQLite tabuľka alebo zoradený výpis).
Čas: ~20 s SK, ~1 min väčšie krajiny, DE ~5 min. Všetkých 9 paralelne ~6 min.

Spojenie 9 SQLite do jednej + index `(street_key, number)`: 56 s, **2,9 GB, 44 196 008 riadkov**.

## Valhalla (trasy) — viď `06-valhalla.md`

9 krajín: build 34 min, `tiles.tar` 13 GB; beh s 1 vláknom v limite 300 MB, peak 251 MiB.

## Obe služby naraz

Valhalla (limit 300 MB, 300 dlhých trás) + Meilisearch (limit 200 MB, 3000 dotazov), záťaž
súčasne: Meilisearch medián 2,4 ms / p95 20,7 ms, Valhalla medián 336 ms (fronta), **bez OOM,
spolu ~450 MB**.

## Preklady popiskov (2026-10-23)

Otázka: koľko stojí vrátiť meno (a obec, kraj) v jazyku používateľa („Stephansplatz, Viedeň“).
`names` je len zoznam bez jazykov, preto sa porovnali tri varianty dokumentov, 9 jazykov
(`_lab/scripts/convert-i18n.php`, `build-variant.sh`; dáta `_data/exp-i18n/`). Polia sú len
na zobrazenie (nie searchable/filterable), prázdne sa vynechávajú. Každý index postavený nanovo
rovnako: v1.35.1, limit 4 GB, 4 CPU, nastavenia `geo4`.

| Variant | NDJSON | `rawDocumentDbSize` | `data.ms` | Indexovanie | anon pri indexovaní |
|---|---|---|---|---|---|
| A `geo4` | 1,062 GB | 1,33 GB (293 B/dok.) | 3,3 GB | 77 s | 2,04 GB |
| **B + `names_i18n`** | 1,065 GB (+0,2 %) | 1,41 GB (311 B) | **3,4 GB (+3 %)** | 98 s | 1,98 GB |
| C + `city_i18n`, `region_i18n` | 1,804 GB (+70 %) | 2,25 GB (501 B) | 4,0 GB (+21 %) | 115 s | 2,05 GB |

- `names_i18n` má len **55 893** zo 4,42 mil. dokumentov (obce a známe POI; ulice preklady
  takmer nemajú). `region_i18n` by bol v 4,2 mil. dokumentoch, `city_i18n` v 1,2 mil.
- Hľadanie A vs. B, zahriate, striedavo, 2000 prefixov: medián **3,40 vs. 3,47 ms**, p95 6,06 vs.
  6,05 ms, anon 57 vs. 56 MiB — bez rozdielu.
- Unikátnych prekladov obcí je **31 047 obcí / 43 252 riadkov**, krajov **311 / 2 357** ⇒ to, čo
  variant C opakuje v každom dokumente, sa zmestí do **~45 600 riadkov** SQL tabuľky.

~~Záver: B v indexe + preklady obcí a krajov v PDO tabuľke.~~ Prekonané variantom D nižšie:
B nevie nájsť ulicu, keď je obec napísaná v inom jazyku.

`du` adresára `data.ms` je nespoľahlivé (voľné stránky LMDB; D vyšiel menší ako A). Spoľahlivé
je porovnanie **po kompakcii** (`POST /indexes/geo/compact`, v1.35.1 ho má, 5–8 s):

| | A `geo4` | B + `names_i18n` | D + `cities` |
|---|---|---|---|
| pred kompakciou | 3,28 GiB | 3,34 GiB | 2,89 GiB |
| **po kompakcii** | **2,63 GiB** | **2,68 GiB (+2 %)** | **2,78 GiB (+6 %)** |

⇒ Indexer má po stavbe vždy spustiť kompakciu (−20 %), aj pred snapshotom.

### Variant D: obec hľadateľná aj v prekladoch

Problém (B): `names` má preklady mena, ale ulica má v hľadateľnom `city` len miestny názov
obce. „stephansplatz viedeň“ → Stephansplatz v Großkrute; „marienplatz mníchov“ → rakúske
dediny; „via del corso rím“ → Matera. Fungovalo len to, čo prejde ako preklep (budapešť, záhreb).

D: `city` ostáva na zobrazenie, nové hľadateľné pole `cities` = miestny názov + `city:xx`
z adresy (9 jazykov, bez duplicít), `searchableAttributes: ["names", "cities"]`; preklady krajov
nie. NDJSON 1,20 GB (+13 % voči B).

| | B | **D** |
|---|---|---|
| `quality-city.py` (38 dotazov ulica + obec v 7 jazykoch, 12 kontrolných s miestnym názvom) | top1 **17/38**, top3 25/38 | top1 **36/38**, top3 37/38 |
| `quality.py` (43 pôvodných) | 42/43 | 42/43 (s preferenciou SK 43/43) |
| Indexovanie | 98 s | 90 s |
| Hľadanie (striedavo, 2000 prefixov vrátane „ulica obec“) | medián 4,58 ms, p95 10,1 ms | medián 4,64 ms, p95 10,3 ms |
| anon pri hľadaní | — | 57 MiB |

Zostávajú: „obchodna pressburg“ (nemecké exonymum Bratislavy pri adresách vo výpise nie je)
a „floriańska cracow“ na 2. mieste (pred ňou tá istá ulica s obcou „Krakow“ — chyba v dátach).
Funguje aj písanie: „stephansplatz vied“ → Wien.

Zamietnutá alternatíva: prekladať obec v PHP pred dotazom (tabuľka prekladov „viedeň“ → „Wien“)
— horšie pri neúplnom slove, viacslovných obciach a nejednoznačných menách; D to rieši
prefixovým hľadaním Meilisearchu za +4 % indexu.

**Záver: D** — `names` + `cities` hľadateľné, `names_i18n` na zobrazenie; preklady obcí
a krajov **na popisok** (podľa jazyka) v malej PDO tabuľke (~45 600 riadkov).

## Snapshot na prenos indexu (2026-10-23)

- `POST /snapshots` na kópii `geo4` (3,8 GB): **145 s**, `data.ms.snapshot` **937 MB**.
- Nová inštancia v1.35.1 `--import-snapshot`, limit **300 MB**: pripravená za **20 s**, anon
  **53 MiB**, file 244 MiB, bez OOM, `data.ms` po importe 2,7 GB; „viedeň“ → Wien.
- Import len do prázdnej inštancie pri štarte, rovnaká verzia ⇒ samostatná inštancia geokódera.
