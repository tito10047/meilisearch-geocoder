# Photon API responses

Answers of Photon 1.3.0 (`photon-1.3.0.jar serve`) on the database of Slovakia imported from
`photon-dump-slovakia-1.0-latest.jsonl.zst` (data timestamp 2026-10-17), captured on 2026-10-23
and pretty-printed; nothing else was changed. `FeatureTest` compares the shape of the features of
the library with them (property names, order and JSON types).

| File | Request |
|---|---|
| `city.json` | `/api?q=bratislava&limit=1` |
| `street-and-poi.json` | `/api?q=Hlavná 12 Košice&limit=3` |
| `poi-en.json` | `/api?q=hotel devin&limit=1&lang=en` |
| `house.json` | `/api?q=Hlavná 12 Košice&layer=house&limit=1` |
| `error-unknown-parameter.json` | `/api?q=bratislava&foo=1` (400) |
| `error-layer.json` | `/api?q=bratislava&layer=bogus` (400) |

## Licence

The data is © OpenStreetMap contributors, available under the
[Open Database License](https://www.openstreetmap.org/copyright) (ODbL 1.0).
