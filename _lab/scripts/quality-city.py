"""Street + town typed in another language: does the street in that town come first (or top 3)?

python3 -I quality-city.py PORT INDEX"""
import json, sys, urllib.request

PORT, INDEX = sys.argv[1], sys.argv[2]
# (typed text, expected name, expected town, country); the first query of each group is the control
CASES = [
    ("stephansplatz wien", "Stephansplatz", "Wien", "AT"), ("stephansplatz viedeň", "Stephansplatz", "Wien", "AT"),
    ("stephansplatz vieden", "Stephansplatz", "Wien", "AT"), ("stephansplatz vídeň", "Stephansplatz", "Wien", "AT"),
    ("stephansplatz wiedeń", "Stephansplatz", "Wien", "AT"), ("stephansplatz bécs", "Stephansplatz", "Wien", "AT"),
    ("stephansplatz vienna", "Stephansplatz", "Wien", "AT"), ("stephansplatz vied", "Stephansplatz", "Wien", "AT"),
    ("marienplatz münchen", "Marienplatz", "München", "DE"), ("marienplatz mníchov", "Marienplatz", "München", "DE"),
    ("marienplatz mnichov", "Marienplatz", "München", "DE"), ("marienplatz munich", "Marienplatz", "München", "DE"),
    ("marienplatz monachium", "Marienplatz", "München", "DE"),
    ("via del corso roma", "Via del Corso", "Roma", "IT"), ("via del corso rím", "Via del Corso", "Roma", "IT"),
    ("via del corso rome", "Via del Corso", "Roma", "IT"), ("via del corso rzym", "Via del Corso", "Roma", "IT"),
    ("václavské náměstí praha", "Václavské náměstí", "Praha", "CZ"),
    ("václavské náměstí prague", "Václavské náměstí", "Praha", "CZ"),
    ("vaclavske namesti praga", "Václavské náměstí", "Praha", "CZ"),
    ("andrássy út budapest", "Andrássy út", "Budapest", "HU"), ("andrássy út budapešť", "Andrássy út", "Budapest", "HU"),
    ("andrassy ut budapeszt", "Andrássy út", "Budapest", "HU"),
    ("floriańska kraków", "Floriańska", "Kraków", "PL"), ("florianska krakov", "Floriańska", "Kraków", "PL"),
    ("floriańska cracow", "Floriańska", "Kraków", "PL"),
    ("obchodná bratislava", "Obchodná", "Bratislava", "SK"), ("obchodná pozsony", "Obchodná", "Bratislava", "SK"),
    ("obchodna pressburg", "Obchodná", "Bratislava", "SK"),
    ("hlavná košice", "Hlavná", "Košice", "SK"), ("hlavná kassa", "Hlavná", "Košice", "SK"),
    ("hlavna koszyce", "Hlavná", "Košice", "SK"),
    ("ilica zagreb", "Ilica", "Zagreb", "HR"), ("ilica záhreb", "Ilica", "Zagreb", "HR"),
    ("ilica zágráb", "Ilica", "Zagreb", "HR"),
    ("prešernov trg ljubljana", "Prešernov trg", "Ljubljana", "SI"), ("prešernov trg lublaň", "Prešernov trg", "Ljubljana", "SI"),
    ("presernov trg lubiana", "Prešernov trg", "Ljubljana", "SI"),
]


def search(q):
    body = {"q": q, "limit": 5, "attributesToRetrieve": ["name", "city", "cc", "kind"]}
    r = urllib.request.Request(f"http://127.0.0.1:{PORT}/indexes/{INDEX}/search", json.dumps(body).encode(),
                               {"Content-Type": "application/json", "Authorization": "Bearer osmlab-test-key-123456"})
    return json.load(urllib.request.urlopen(r, timeout=30))["hits"]


top1 = top3 = 0
for q, name, city, cc in CASES:
    hits = search(q)
    pos = next((i for i, h in enumerate(hits) if h["name"] == name and h["city"] == city and h["cc"] == cc), None)
    top1 += pos == 0
    top3 += pos is not None and pos < 3
    mark = "OK " if pos == 0 else (f"#{pos + 1} " if pos is not None else "-- ")
    print(f"{mark} {q:28} -> " + " | ".join(f"{h['name']} ({h['city'] or '-'}, {h['cc']})" for h in hits[:3]))
print(f"top1 {top1}/{len(CASES)}  top3 {top3}/{len(CASES)}")
