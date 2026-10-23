# Meilisearch: index a ranking

## Finálne nastavenia (`_lab/scripts/geo4-settings.json`)

```json
{
  "searchableAttributes": ["names", "city"],
  "displayedAttributes": ["id", "kind", "type", "name", "city", "region", "cc", "postcode", "_geo", "rank"],
  "filterableAttributes": [{"attributePatterns": ["cc", "kind"],
                            "features": {"facetSearch": false, "filter": {"equality": true, "comparison": false}}}],
  "sortableAttributes": [],
  "rankingRules": ["words", "typo", "proximity", "rank:desc", "attribute", "exactness"],
  "proximityPrecision": "byAttribute",
  "facetSearch": false,
  "typoTolerance": {"disableOnNumbers": true},
  "pagination": {"maxTotalHits": 20}
}
```

**Pre knižnicu (2026-10-23, variant D z `01-merania.md`)**: `searchableAttributes: ["names",
"cities"]` (`cities` = obec + jej preklady), `city` len na zobrazenie, `names_i18n` na zobrazenie;
po stavbe kompakcia indexu.

Nastavenia **pred** prvými dokumentmi — zmena searchable/filterable neskôr = celé preindexovanie.
Zmena poradia ranking rules je lacná (~3 s, bez preindexovania).

Prečo:
| Nastavenie | Dôvod |
|---|---|
| `region` nie je searchable | kraj sa len zobrazuje; menší index |
| bez `_geo` filter/sort | geo R-strom netreba; triedenie podľa vzdialenosti prebije relevanciu |
| `rank` nemusí byť sortable | vlastné ranking pravidlo `rank:desc` funguje aj tak |
| `proximityPrecision: byAttribute` | výrazne menší index a rýchlejšie indexovanie; názvy sú krátke |
| `facetSearch: false`, filter len equality | nepoužívané štruktúry |
| `disableOnNumbers` | čísla (PSČ, „Praha 11“) bez preklepov |
| `prefixSearch` ostáva `indexingTime` | nutné pre písanie s návrhmi |

Výsledok: 4,42 mil. dokumentov, 3,8 GB (pôvodne 6,37 mil. / 11 GB len pre 8 krajín).

## Test kvality (`_lab/scripts/quality.py`)

43 dotazov tak, ako ich píše človek plánujúci cestu (s diakritikou aj bez, neúplné,
slovenské exonymá: viedeň, mníchov, rím, benátky, varšava, budapešť), s očakávaným prvým
výsledkom. Spustenie: `python3 -I scripts/quality.py <port> <index> [HOME_CC] [váha]`.

| Krok | Top 1 | Top 3 |
|---|---|---|
| `geo2`: filtrovanie obsahu, pôvodné ranking `words, typo, proximity, attribute, exactness, rank:desc` | 35/43 | 37/43 |
| `geo3`: všetky mená v `names`, dedup obcí do 5 km | 37/43 | 38/43 |
| `geo3` + federované hľadanie s váhou domácej krajiny 1,2 | **31/43** (horšie) | 36/43 |
| `geo3` + **`rank:desc` pred `attribute`** | 41/43 | 43/43 |
| **`geo4`**: + `importance` v `rank` | **42/43** | **43/43** |

Zostávajúca chyba: „bystre“ → Bystré (CZ, town) pred Bystré (SK, village) — potrebné
uprednostnenie krajiny/jazyka používateľa.

## Slepé uličky a príčiny

- **„kosic“ → chorvátska osada Kosić pred Košicami**: `exactness` (presná zhoda slova
  „kosić“ po normalizácii) bola pred `rank`. Vyriešil `rank` pred `attribute`/`exactness`.
- **„viedeň“ → ulica Viedenská pred Viedňou**: pravidlo `attribute` v 1.35 počíta aj pozíciu
  slova v poli (`queryWordDistanceScore`), preklad na 2. mieste poľa `names` prehral s
  ulicou, ktorej meno je na 1. mieste. Rozdelené pravidlá `attributeRank` / `wordPosition`
  **1.35.1 nepozná** („Valid ranking rules are words, typo, sort, proximity, attribute,
  exactness and custom“). Novšie verzie áno — v knižnici voliteľne podľa verzie.
- **Duplicitné obce** (Košice 2×, Žilina city + town): relácia + bod. Dedup do 5 km.
- **Federované hľadanie s váhou** (`/multi-search`, `federation`, dva dotazy `cc = SK`
  s `weight 1.2` a `cc != SK`) zhoršilo výsledok: váha násobí skóre relevancie, ktoré o `rank`
  nevie → slovenské ulice Viedenská, Budapeštianska, Berlínska prebili hlavné mestá.
  Nápady: bonus za domácu krajinu len pri rovnakom mene; `rank` bonus podľa jazyka pri
  indexovaní (index na jazyk?); alebo preusporiadanie top N v PHP.
- Typo tolerancia: „Bansk Bystrica“ funguje; slová < 5 znakov bez preklepov (default).

## Pamäť

Hľadanie: anon ~50 MiB pri limite 150–300 MB. Indexovanie: viď `01-merania.md` — pri
väčšom indexe gigabajty; `MAX_INDEXING_MEMORY` nepomáha. Odporúčanie: stavať index na
inom stroji a preniesť `data.ms` (rovnaká verzia Meilisearchu, LMDB súbory sú prenositeľné
na rovnakej architektúre), alebo snapshot/dump. Na zdieľanom Meilisearchu (iné projekty
na tej istej inštancii) sa `data.ms` vymeniť nedá — potom samostatná inštancia, alebo
indexovanie po malých dávkach s nižším súbehom (neoverené, či to drží pamäť).

Overené: kópia `data.ms` (reflink/`cp -a`) do iného adresára a štart 1.35.1 nad ňou funguje.
