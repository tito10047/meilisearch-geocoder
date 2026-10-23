# Návrh knižnice (náčrt, nič nie je rozhodnuté)

Toto je východisko na diskusiu s používateľom, nie odsúhlasený návrh. Pred stavbou
potvrdiť rozsah, názvy a licenciu.

## Ciele

- PHP 8.4+/8.5, bez frameworku v jadre; **Symfony bundle** ako samostatný balík alebo
  podadresár (autor robí Symfony bundles: viď `~/phpProjects/contrib/*-bundle`,
  `bundle-skeleton`).
- Funguje s Meilisearchom, ktorý projekt už má (oficiálny `meilisearch/meilisearch-php`
  alebo vlastný tenký klient cez PSR-18 / Symfony HttpClient).
- Čísla domov cez **PDO** (SQLite predvolene, MariaDB/PostgreSQL voliteľne).
- Malá pamäť pri hľadaní; import a indexovanie mysliteľné na inom stroji.

## Komponenty

1. **Import** — `PhotonDumpReader` (stream JSON Lines zo súboru/rúry/`ext-zstd`),
   `DumpDownloader` (URL podľa krajiny, ETag/`data_timestamp`, atomická výmena),
   `Converter` (pravidlá z `02-data-a-format.md`: výber obcí/POI, dedup, `rank`,
   `names`, ulice + čísla domov). Pravidlá výberu POI a typy obcí **konfigurovateľné**.
   Dedup ulíc bez držania všetkého v pamäti (dočasná SQLite tabuľka / zoradenie).
2. **Indexer** — vytvorí index s nastaveniami (pred dokumentmi), posiela dávky NDJSON,
   čaká na úlohy, **swap index** (`/swap-indexes`) pre výmenu bez výpadku; nastavenia
   podľa verzie Meilisearchu (`attributeRank` od istej verzie).
3. **Hľadanie** — `Geocoder::suggest(string $text, ?SearchContext $ctx): list<Suggestion>`
   (písanie s návrhmi; kontext: jazyk, krajina/poloha používateľa, filtre krajín, limit),
   `geocode()` (jednorazovo), neskôr `reverse()` (bod → najbližšia adresa; potrebuje geo
   index — nateraz vypnutý kvôli veľkosti). Rozpoznanie čísla domu, dohľadanie v PDO,
   popisok v jazyku (`name:sk`), uprednostnenie domácej krajiny.
4. **Integrácie** — provider pre `geocoder-php/Geocoder`; voliteľne HTTP endpoint
   kompatibilný s Photonom (`/api?q=…&lang=…&lat=…&lon=…&limit=…`, GeoJSON); Symfony bundle
   (konfigurácia, príkazy `geocoder:import`, `geocoder:index`, služby, UX Autocomplete
   autocompleter ako v Tankovači `PlaceAutocompleter`).

## Otvorené otázky

- Názov balíka a namespace (pracovne `tito10047/meilisearch-geocoder`).
- Licencia kódu (MIT ako ostatné autorove?) + jasná atribúcia ODbL pre dáta.
- Jeden index na všetky krajiny, alebo index na krajinu (menšie, paralelné, ľahšia aktualizácia)?
- Uprednostnenie domácej krajiny/jazyka — ako (viď `03`), bez zhoršenia ostatných výsledkov.
- Orientačné čísla (`606/42`): hľadať aj podľa časti za lomkou.
- Reverse geocoding: či vôbec a ako lacno (SQLite R-tree v tabuľke domov? GeoNames?).
- Aktualizácie: týždenné výpisy → plný reimport a swap; čiastočné aktualizácie neexistujú
  (Photon ich vie len z Nominatim DB).
- Ako zaručiť pamäť pri indexovaní na zdieľanom Meilisearchu (malé dávky? samostatná inštancia?).
- Testy: malé fixture výpisy (pár stoviek riadkov zo SK) + Meilisearch v Dockeri v CI.

## Tankovač ako prvý používateľ

V Tankovači je `GeocoderInterface` (`#[AutoconfigureTag]`, `#[AsTaggedItem(index, priority)]`)
a fasáda `PlaceSearch::suggest()` s cache a fallbackom; nový geokóder by bol provider
s prioritou 200, GraphHopper ostáva záloha. Tankovač potrebuje: návrhy od 3 znakov
(debounce 300 ms), popisok, súradnice (`PlaceCode` = `lat,lon|label`), jazyky sk/en/cs/pl.
