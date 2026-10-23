"""Fire route requests at Valhalla like Tankovac does (2 points + alternates, or stops)."""
import json, random, sys, time, urllib.request, concurrent.futures as cf, statistics

URL = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8002/route"
N = int(sys.argv[2]) if len(sys.argv) > 2 else 200
CONC = int(sys.argv[3]) if len(sys.argv) > 3 else 4
TOWNS = [(48.1486,17.1077),(48.7164,21.2611),(49.2231,18.7394),(48.3069,18.0864),(49.0011,21.2393),
         (48.7363,19.1462),(48.3774,17.5872),(49.0606,20.2975),(50.0755,14.4378),(49.1951,16.6068),
         (49.8209,18.2625),(48.2082,16.3738),(47.0707,15.4395),(48.3069,14.2858),(47.8095,13.0550),
         (47.4979,19.0402),(47.5316,21.6273),(46.2530,20.1414),(50.0647,19.9450),(52.2297,21.0122),
         (51.1079,17.0385),(50.2649,19.0238),(45.8150,15.9819),(43.5081,16.4402),(45.3271,14.4422),
         (46.0569,14.5058),(45.5469,13.7294),(43.2965,17.0175),(54.3520,18.6466),(46.6247,14.3053)]
def req():
    a, b = random.sample(TOWNS, 2)
    locs = [{"lat": a[0], "lon": a[1]}, {"lat": b[0], "lon": b[1]}]
    body = {"locations": locs, "costing": "auto", "units": "kilometers", "directions_type": "none",
            "admin_crossings": True}
    if random.random() < 0.7:
        body["alternates"] = 2
    else:
        c = random.choice(TOWNS); locs.insert(1, {"lat": c[0], "lon": c[1], "type": "break"})
    t = time.perf_counter()
    try:
        r = urllib.request.urlopen(urllib.request.Request(URL, json.dumps(body).encode(), {"Content-Type": "application/json"}), timeout=60)
        d = json.load(r); ok = "trip" in d
    except Exception as e:
        ok = False
    return time.perf_counter() - t, ok
with cf.ThreadPoolExecutor(CONC) as ex:
    res = list(ex.map(lambda _: req(), range(N)))
lat = sorted(x for x, _ in res); okc = sum(1 for _, o in res if o)
print(f"n={N} conc={CONC} ok={okc} median={statistics.median(lat)*1000:.0f}ms p95={lat[int(.95*len(lat))-1]*1000:.0f}ms max={lat[-1]*1000:.0f}ms")
