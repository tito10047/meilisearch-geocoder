# Valhalla fixtures

Answers of Valhalla's `/route` recorded on 2026-10-23 from a local server:

- image `ghcr.io/valhalla/valhalla-scripted@sha256:eaad372b8ef31e3e4592c442875aeb1375fbf5aeaa0d259b469e3b46583ab10d`
  (Valhalla 3.9.1-618cad5eb), the one pinned in `resources/docker/compose.yaml`, run by its
  `serve.sh` (1 thread, 20 MB cache);
- tiles built on 2026-10-22 from the Geofabrik extracts of 9 countries (AT, CZ, DE, HR, HU, IT,
  PL, SI, SK).

`<name>.request.json` is the body `RequestEncoder` sends for the request of the same name in
`../Fixture.php`, `<name>.response.json` the body Valhalla answered with, unchanged.

| Name | Request | Answer |
|---|---|---|
| `ba-kosice-alternatives` | Bratislava → Košice, 3 routes | 200, 3 routes (the third through Hungary) |
| `ba-split` | Bratislava → Split | 200, SK, HU, SI, HU, HR with tolls |
| `stephansplatz-without-radius` | Stephansplatz in Vienna → Bratislava, radius 0 | 400, error 442 "No path could be found for input" |
| `stephansplatz-with-radius` | the same, radius 200 m | 200 |
| `outside-data` | Paris → Bratislava | 400, error 171 "No suitable edges near location" |
| `multi-stop` | Bratislava → Stephansplatz → Brno, 3 routes asked | 200, 1 route of 2 legs (no alternatives with stops) |

To record them again, run Valhalla (see `resources/docker/README.md`), encode each request of
`Fixture::requests()` with `RequestEncoder`, post it to `/route` and save both bodies; then
update the numbers asserted in `ResponseParserTest`.

The answers are derived from OpenStreetMap data, © OpenStreetMap contributors, available under
the [Open Database License](https://www.openstreetmap.org/copyright).
