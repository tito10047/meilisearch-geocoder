"""quality.py with the home-country preference done in PHP's place: fetch the top 15, then among
results of the same name and kind the preferred country goes first — unless it would jump a place
ranked more than MAX_GAP higher (a city over a village); nothing else moves.

python3 -I quality-rerank.py PORT INDEX HOME_CC [MAX_GAP] [FETCH]"""
import ast, json, pathlib, sys, unicodedata, urllib.request

PORT, INDEX, HOME = sys.argv[1], sys.argv[2], sys.argv[3]
MAX_GAP = int(sys.argv[4]) if len(sys.argv) > 4 else 25000
FETCH = int(sys.argv[5]) if len(sys.argv) > 5 else 15
# the same 43 cases as quality.py
src = pathlib.Path(__file__).with_name("quality.py").read_text()
CASES = next(ast.literal_eval(n.value) for n in ast.parse(src).body
             if isinstance(n, ast.Assign) and getattr(n.targets[0], "id", "") == "CASES")


def search(q):
    body = {"q": q, "limit": FETCH, "attributesToRetrieve": ["name", "city", "cc", "kind", "type", "rank"]}
    r = urllib.request.Request(f"http://127.0.0.1:{PORT}/indexes/{INDEX}/search", json.dumps(body).encode(),
                               {"Content-Type": "application/json", "Authorization": "Bearer osmlab-test-key-123456"})
    return json.load(urllib.request.urlopen(r, timeout=30))["hits"]


def key(h):
    name = unicodedata.normalize("NFKD", h["name"].casefold())
    return "".join(c for c in name if not unicodedata.combining(c)), h["kind"]


def rerank(hits, home):
    groups = {}
    for h in hits:
        groups.setdefault(key(h), []).append(h)
    out, done = [], set()
    for h in hits:
        k = key(h)
        if k not in done:
            done.add(k)
            group = groups[k]
            best = next((x for x in group if x["cc"] == home), None)
            if best is not None and group[0]["rank"] - best["rank"] <= MAX_GAP:
                group = [best] + [x for x in group if x is not best]
            out += group
    return out


for label, fn in (("bez preferencie", lambda h: h), (f"preferencia {HOME}", lambda h: rerank(h, HOME))):
    top1 = top3 = 0
    changed = []
    for q, name, cc in CASES:
        raw = search(q)
        hits = fn(raw)[:5]
        pos = next((i for i, h in enumerate(hits) if (name is None or h["name"] == name) and h["cc"] == cc), None)
        top1 += pos == 0
        top3 += pos is not None and pos < 3
        if hits[:1] != raw[:1]:
            changed.append(f"{q!r}: {raw[0]['name']} ({raw[0]['cc']}) -> {hits[0]['name']} ({hits[0]['cc']})")
    print(f"{label}: top1 {top1}/{len(CASES)}  top3 {top3}/{len(CASES)}")
    for c in changed:
        print("   zmena 1. miesta:", c)
