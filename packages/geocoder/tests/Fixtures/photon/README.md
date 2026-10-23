# Photon dump sample

`slovakia-sample.jsonl` is a sample of 200 lines of the Photon dump of Slovakia
(`photon-dump-slovakia-1.0-latest.jsonl.zst` from https://download1.graphhopper.com/public/europe/slovakia/,
data timestamp 2026-10-17), format "Nominatim Dump File" 0.1.0. `slovakia-sample.jsonl.zst` is the
same file compressed with zstd.

It holds:

- the header, verbatim;
- the `CountryInfo` record, reduced to six countries and to the names in 11 languages;
- the village Viničky: its places, its streets (several split into many ways), its points of
  interest (selected ones and others), nameless points, a part of its house numbers (many of them
  "conscription/orientation" numbers like `196/14`) and the street "Viničky", known from its
  addresses and bus stops only;
- Košice (boundary relation and label node, about 2 km apart) and Žilina (relation of type
  `town`, node of type `city`), which the conversion deduplicates;
- a hamlet and a street named "Viničky" elsewhere, a building with two addresses (a record with
  two places), a node with a house number that is two points of interest, a postcode area
  (no OSM object).

To keep it small, the geometries, bounding boxes, `extra` and `categories` fields, the `county:*`
keys and the translations of the address in other languages were removed. Nothing else was changed,
except that the houses got back the one `extra` tag the library reads: `ref:minvskaddress` (the id
of the Slovak register of addresses), taken from the same dump for the 129 houses that have it.

`expected-documents.ndjson` and `expected-house-numbers.csv` are the output of the prototype
(`_lab/scripts/convert-i18n.php` with the house numbers of `convert.php`, variant D of the
documents) for this sample, sorted, in the canonical form of `Document::toArray()` — except that
the second document of the node `N13544973832` (two points of interest, the same id) is left out:
the library writes each document id once, keeping the first record. The fields the prototype did
not have were added from the library's output (checked to change nothing else): the `suburb` and
`county` of the documents, the `suburb` and `reference` of the house numbers.

## Mistagged towns

`mistagged-towns.jsonl` reproduces the wrong translations of towns of the first production import:
Photon takes the town of an address from the object's own `addr:city` but its translations
(`city:xx`) from the boundary the object lies in. Its first house is the address of a building
of the Slovak dump of 2026-10-17 (way 318758534, reduced to a few address keys, another id) in
Bytča tagged `addr:city=Bratislava`, with `city:sk=Bytča`; the other records are made up after
records of the same dump: houses and the records of Bytča and Bratislava (one house of Bratislava without the
translations of its town, as 54,000 of them are), a village whose addresses do not translate it,
two villages "Nová Ves" with different Hungarian names (one with more houses) and two villages
"Lehota", one of them not translated.

## Licence

The data is © OpenStreetMap contributors, available under the
[Open Database License](https://www.openstreetmap.org/copyright) (ODbL 1.0). The sample is a
derived database distributed under the same licence.
