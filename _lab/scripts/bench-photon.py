"""Type-ahead load on Photon: every query is a prefix of a real place, like the debounced picker."""
import json, random, sys, time, urllib.request, urllib.parse, concurrent.futures as cf, statistics
URL = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:2322/api"
N = int(sys.argv[2]) if len(sys.argv) > 2 else 300
CONC = int(sys.argv[3]) if len(sys.argv) > 3 else 4
WORDS = ["Bratislava","Košice","Žilina","Banská Bystrica","Hlavná 12 Košice","Mlynské nivy","Praha","Brno",
         "Wien","Graz","Budapest","Debrecen","Kraków","Warszawa","Zagreb","Split","Makarska","Ljubljana",
         "Piran","München","Berlin","Roma","Venezia","Lignano","Trnava","Poprad","Štúrovo","Zakopane",
         "Bibione","Rijeka","Ostrava","Olomouc","Nitra","Prešov","Hviezdoslavova","Obchodná 5"]
def req():
    w = random.choice(WORDS); q = w[:random.randint(3, len(w))]
    u = URL + "?" + urllib.parse.urlencode({"q": q, "limit": 7, "lang": "sk", "lat": 48.7, "lon": 19.7})
    t = time.perf_counter()
    try:
        ok = "features" in json.load(urllib.request.urlopen(u, timeout=30))
    except Exception:
        ok = False
    return time.perf_counter() - t, ok
with cf.ThreadPoolExecutor(CONC) as ex:
    res = list(ex.map(lambda _: req(), range(N)))
lat = sorted(x for x, _ in res); okc = sum(1 for _, o in res if o)
print(f"n={N} conc={CONC} ok={okc} median={statistics.median(lat)*1000:.0f}ms p95={lat[int(.95*len(lat))-1]*1000:.0f}ms max={lat[-1]*1000:.0f}ms")
