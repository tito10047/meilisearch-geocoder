# Dáta a skripty v tomto priečinku

Všetko z experimentu je tu, aby sa nemuselo znova sťahovať ani počítať. Dáta sú
kópie (btrfs reflink — nezaberajú miesto navyše, kým sa nezmenia) z pôvodného
`~/osm-lab`, ktorý sa môže zmazať. **`_data/` nepatrí do gitu** (`.gitignore`), ~39 GB.

## `_data/`

| Cesta | Čo | Veľkosť |
|---|---|---|
| `pbf/*.osm.pbf` | Geofabrik extrakty 9 krajín (2026-10-22), pre Valhallu | 12 GB |
| `photon-dump/*.jsonl.zst` | Photon JSON výpisy 1.0 (2026-10-17/19) 9 krajín — **vstup knižnice** | 4,5 GB |
| `ndjson/*.ndjson` | finálne dokumenty pre Meilisearch (výstup `convert.php`, index `geo4`) | 1,0 GB |
| `houses/houses.sqlite` | 44,2 mil. čísel domov 9 krajín, s indexom | 2,8 GB |
| `meilisearch/data.ms` | hotová DB Meilisearch **v1.35.1** s indexom `geo4` | 3,8 GB |
| `valhalla/all/` | `tiles.tar` 9 krajín, `admins.sqlite`, `build.log`, config | 13 GB |
| `valhalla/sk/` | `tiles.tar` len SK (rýchle testy) | 0,35 GB |
| `photon/` | `photon-1.3.0.jar` + DB SK (`sk/photon_data`) na porovnanie | 0,9 GB |
| `reference/` | klony geors a open-geocode (Rust) na štúdium | 46 MB |
| `exp-i18n/` | z merania prekladov (2026-10-23) ostalo: `d/` = NDJSON variantu D (finálne dokumenty pre knižnicu, `cities` + `names_i18n`), `meili-d/` = DB v1.35.1 s indexom `geo` z nich (skompaktovaný, nastavenia `SEARCHABLE='["names","cities"]'`), `snapshot-geo4/data.ms.snapshot` = snapshot `geo4` | 4,8 GB |

Master kľúč Meilisearch DB: `osmlab-test-key-123456` (len testovací).

Spustenie hotového indexu:
```bash
docker run -d --name mg --user $(id -u):$(id -g) --memory=200m -p 127.0.0.1:7713:7700 \
  -v $PWD/_data/meilisearch:/meili_data -e MEILI_MASTER_KEY=osmlab-test-key-123456 \
  -e MEILI_NO_ANALYTICS=true getmeili/meilisearch:v1.35.1
curl -s localhost:7713/indexes/geo4/search -H 'Authorization: Bearer osmlab-test-key-123456' \
  -H 'Content-Type: application/json' -d '{"q":"viedeň","limit":3}'
```
(overené: vráti Wien)

Valhalla:
```bash
docker run -d --name vh --memory=300m -e THREADS=1 -e CACHE=20000000 -p 127.0.0.1:8002:8002 \
  -v $PWD/_data/valhalla/all:/data:ro -v $PWD/_lab/scripts:/scripts:ro \
  --entrypoint /scripts/serve-valhalla.sh ghcr.io/valhalla/valhalla-scripted:latest
```

## `_lab/scripts/`

Prototypy z experimentu (Python 3 a PHP; Python spúšťať s `-I`). Pôvodne bežali
z `~/osm-lab` s podadresármi `scripts/`, `pbf/`, `photon-dump/`, `out*/`, `meili-data*/` —
cesty v nich sú relatívne k tomu, pri opakovaní ich treba upraviť na `_data/…`.

| Skript | Čo |
|---|---|
| `download.sh <adr>` | stiahne PBF + Photon výpisy 7 krajín (DE, IT sa sťahovali zvlášť rovnakými URL) |
| `census.py` | spočíta obsah Photon výpisu (stdin) |
| `convert.php` | **Photon výpis → NDJSON + SQLite** (finálne pravidlá) |
| `geo4-settings.json` | finálne nastavenia indexu |
| `meili.sh`, `load.sh` | volanie API, nahratie NDJSON po 50 tis. riadkov (env `PORT`, `INDEX`, `DATA`, `CONTAINER`) |
| `mem.sh <kontajner>` | anon / file / peak / OOM z cgroup |
| `bench-meili.py N CONC houses.sqlite` | záťaž geokódera (port 7712, index `geo4` — upraviť) |
| `quality.py PORT INDEX [CC] [váha]` | test kvality rankingu (43 dotazov) |
| `quality-rerank.py PORT INDEX CC [MAX_GAP]` | to isté s uprednostnením domácej krajiny (rerank rovnakých mien) |
| `quality-city.py PORT INDEX` | ulica + obec v inom jazyku (38 dotazov) |
| `convert-i18n.php`, `[SEARCHABLE=…] build-variant.sh NAME DIR PORT` | variant konverzie s prekladmi; stavba a meranie indexu jedného variantu |
| `bench-photon.py`, `bench-valhalla.py` | záťaž Photonu / Valhally |
| `build-valhalla.sh`, `serve-valhalla.sh` | build a beh Valhally v kontajneri (env `CONC`, `THREADS`, `CACHE`, `LRU`, `GLOBAL`, `LABELS`, `ARENAS`) |

Opakovanie celého reťazca (orientačne):
```bash
for c in slovakia czech-republic austria hungary poland croatia slovenia germany italy; do
  zstd -dc _data/photon-dump/$c.jsonl.zst | php -d memory_limit=6G _lab/scripts/convert.php out/$c.ndjson out/$c.sqlite &
done; wait
# index: vytvoriť, PATCH settings z geo4-settings.json, potom load.sh pre každý NDJSON
```

## Zdroje

- Photon výpisy: https://download1.graphhopper.com/public/europe/
- Geofabrik: https://download.geofabrik.de/europe.html
- Photon: https://github.com/komoot/photon (docs/usage.md, docs/api-v1.md, json-dump-format-0.1.0.md)
- Valhalla route API: https://valhalla.github.io/valhalla/api/route/api-reference/
- Meilisearch: https://www.meilisearch.com/docs
