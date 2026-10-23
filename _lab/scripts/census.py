"""Count what a Photon dump holds: places, streets, named POIs, house numbers."""
import json, sys, collections
c = collections.Counter(); keys = collections.Counter(); streets = set()
for line in sys.stdin:
    o = json.loads(line)
    if o.get("type") != "Place": continue
    for p in o["content"]:
        k = p.get("osm_key"); a = p.get("address", {}); named = "name" in (p.get("name") or {})
        if p.get("housenumber"): c["housenumber"] += 1
        if k == "place" and named: c["place"] += 1
        elif k == "highway" and named:
            c["highway_rows"] += 1; streets.add((p.get("country_code"), a.get("city") or a.get("town") or a.get("village"), p["name"]["name"]))
        elif named: c["named_other"] += 1; keys[k] += 1
        else: c["unnamed_other"] += 1
c["streets_dedup"] = len(streets)
print(dict(c)); print(keys.most_common(25))
