"""Ranking check: does the expected place come first (or in the top 3) while typing?"""
import json, sys, urllib.request

PORT = sys.argv[1] if len(sys.argv) > 1 else "7712"
INDEX = sys.argv[2] if len(sys.argv) > 2 else "geo2"
HOME = sys.argv[3] if len(sys.argv) > 3 else None  # e.g. SK: federated search, home country weighted
WEIGHT = float(sys.argv[4]) if len(sys.argv) > 4 else 1.2
# (typed text, expected name, expected country) — the expected hit for a trip planner
CASES = [
    ("kosic", "Košice", "SK"), ("Košice", "Košice", "SK"), ("brati", "Bratislava", "SK"),
    ("zilina", "Žilina", "SK"), ("banska bys", "Banská Bystrica", "SK"), ("poprad", "Poprad", "SK"),
    ("nitra", "Nitra", "SK"), ("presov", "Prešov", "SK"), ("trnava", "Trnava", "SK"),
    ("piestany", "Piešťany", "SK"), ("stary smok", "Starý Smokovec", "SK"), ("bystre", "Bystré", "SK"),
    ("praha", "Praha", "CZ"), ("brno", "Brno", "CZ"), ("ostrava", "Ostrava", "CZ"),
    ("wien", "Wien", "AT"), ("viedeň", "Wien", "AT"), ("vieden", "Wien", "AT"), ("graz", "Graz", "AT"),
    ("budapest", "Budapest", "HU"), ("budapešť", "Budapest", "HU"), ("krakow", "Kraków", "PL"),
    ("zakopane", "Zakopane", "PL"), ("warszawa", "Warszawa", "PL"), ("varšava", "Warszawa", "PL"),
    ("zagreb", "Zagreb", "HR"), ("split", "Split", "HR"), ("makarska", "Makarska", "HR"),
    ("ljubljana", "Ljubljana", "SI"), ("münchen", "München", "DE"), ("munchen", "München", "DE"),
    ("mníchov", "München", "DE"), ("berlin", "Berlin", "DE"), ("roma", "Roma", "IT"), ("rím", "Roma", "IT"),
    ("venezia", "Venezia", "IT"), ("benátky", "Venezia", "IT"), ("lignano", "Lignano Sabbiadoro", "IT"),
    ("bibione", "Bibione", "IT"), ("tatralandia", "Tatralandia", "SK"), ("letisko bratislava", None, "SK"),
    ("mlynske nivy", "Mlynské nivy", "SK"), ("hlavna kosice", "Hlavná", "SK"),
]


def post(path, body):
    r = urllib.request.Request(f"http://127.0.0.1:{PORT}{path}", json.dumps(body).encode(),
                               {"Content-Type": "application/json", "Authorization": "Bearer osmlab-test-key-123456"})
    return json.load(urllib.request.urlopen(r, timeout=30))


def search(q):
    attrs = ["name", "city", "cc", "kind", "type"]
    if HOME is None:
        return post(f"/indexes/{INDEX}/search", {"q": q, "limit": 5, "attributesToRetrieve": attrs})["hits"]
    return post("/multi-search", {"federation": {"limit": 5}, "queries": [
        {"indexUid": INDEX, "q": q, "filter": f"cc = {HOME}", "attributesToRetrieve": attrs,
         "federationOptions": {"weight": WEIGHT}},
        {"indexUid": INDEX, "q": q, "filter": f"cc != {HOME}", "attributesToRetrieve": attrs},
    ]})["hits"]


top1 = top3 = 0
for q, name, cc in CASES:
    hits = search(q)
    pos = next((i for i, h in enumerate(hits) if (name is None or h["name"] == name) and h["cc"] == cc), None)
    top1 += pos == 0
    top3 += pos is not None and pos < 3
    mark = "OK " if pos == 0 else (f"#{pos + 1} " if pos is not None else "-- ")
    print(f"{mark} {q:20} -> " + " | ".join(f"{h['name']} ({h['city'] or '-'}, {h['cc']}, {h['type']})" for h in hits[:3]))
print(f"top1 {top1}/{len(CASES)}  top3 {top3}/{len(CASES)}")
