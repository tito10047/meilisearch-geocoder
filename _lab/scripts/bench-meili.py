"""Type-ahead load on the Meilisearch geocoder: prefix queries, a house number goes to SQLite."""
import json, random, re, sqlite3, sys, time, urllib.request, concurrent.futures as cf, statistics, threading

N = int(sys.argv[1]) if len(sys.argv) > 1 else 400
CONC = int(sys.argv[2]) if len(sys.argv) > 2 else 4
DBS = sys.argv[3:] or ["out/sk.sqlite"]
WORDS = ["Bratislava", "Košice", "Žilina", "Banská Bystrica", "Hlavná 12 Košice", "Mlynské nivy 5 Bratislava",
         "Praha", "Brno", "Wien", "Graz", "Budapest", "Debrecen", "Kraków", "Warszawa", "Zagreb", "Split",
         "Makarska", "Ljubljana", "Piran", "München", "Berlin", "Roma", "Venezia", "Lignano", "Trnava",
         "Poprad", "Štúrovo", "Zakopane", "Bibione", "Rijeka", "Ostrava", "Olomouc", "Nitra", "Prešov",
         "Hviezdoslavova 3 Trnava", "Obchodná 5 Bratislava", "Kosice", "Zilina", "Bansk Bystrica", "Lidl Poprad"]
NUM = re.compile(r"^\d+[a-zA-Z]?(/\d+[a-zA-Z]?)?$")
local = threading.local()

def db():
    if not hasattr(local, "c"):
        local.c = [sqlite3.connect(f"file:{p}?mode=ro", uri=True, check_same_thread=False) for p in DBS]
    return local.c

def search(q):
    body = {"q": q, "limit": 7, "sort": ["_geoPoint(48.7, 19.7):asc"] if False else None,
            "attributesToRetrieve": ["id", "kind", "name", "city", "cc", "_geo"]}
    body = {k: v for k, v in body.items() if v is not None}
    r = urllib.request.Request("http://127.0.0.1:7712/indexes/geo4/search", json.dumps(body).encode(),
                               {"Content-Type": "application/json", "Authorization": "Bearer osmlab-test-key-123456"})
    return json.load(urllib.request.urlopen(r, timeout=30))["hits"]

def req():
    w = random.choice(WORDS)
    q = w[:random.randint(3, len(w))]
    t = time.perf_counter()
    tokens = q.split()
    number = next((x for x in tokens if NUM.match(x)), None)
    rest = " ".join(x for x in tokens if x != number)
    hits = search(rest)
    houses = []
    if number:
        for h in hits:
            if h["kind"] == "street":
                key = int(h["id"][1:])
                for c in db():
                    houses += c.execute("SELECT number, lat, lon FROM house WHERE street_key = ? AND number LIKE ? LIMIT 5",
                                        (key, number + "%")).fetchall()
    return time.perf_counter() - t, (q, hits[:2], houses[:2])

with cf.ThreadPoolExecutor(CONC) as ex:
    res = list(ex.map(lambda _: req(), range(N)))
lat = sorted(x for x, _ in res)
print(f"n={N} conc={CONC} median={statistics.median(lat)*1000:.1f}ms p95={lat[int(.95*len(lat))-1]*1000:.1f}ms max={lat[-1]*1000:.1f}ms")
if "-v" in sys.argv[0:1] or True:
    seen = set()
    for _, (q, h, hs) in res:
        if q in seen or len(seen) > 12: continue
        seen.add(q); print(f"  {q!r:28} -> {[(x['name'], x['city'], x['kind']) for x in h]} {hs}")
