<?php
// Photon dump (JSONL on stdin) -> Meilisearch NDJSON (places, streets, POIs) + SQLite house numbers.
// Usage: zstd -dc x.jsonl.zst | php convert.php out.ndjson houses.sqlite

declare(strict_types=1);

[$_, $ndjsonPath, $sqlitePath] = $argv;

const LANGS = ['sk', 'en', 'de', 'hu', 'cs', 'pl', 'hr', 'sl', 'it'];
// Only what a trip can head for; null = every value of the key
const POI = [
    'tourism' => ['hotel', 'guest_house', 'hostel', 'motel', 'apartment', 'chalet', 'alpine_hut', 'camp_site',
        'caravan_site', 'attraction', 'museum', 'theme_park', 'zoo', 'aquarium'],
    'leisure' => ['water_park', 'stadium', 'resort', 'marina', 'golf_course'],
    'aeroway' => ['aerodrome'],
    'railway' => ['station'],
    'amenity' => ['hospital', 'university', 'college', 'ferry_terminal', 'bus_station'],
    'historic' => ['castle'],
];
const POI_KEYS = ['tourism', 'leisure', 'aeroway', 'railway', 'amenity', 'historic'];
const PLACE_RANK = ['city' => 90, 'town' => 70, 'village' => 50, 'suburb' => 45, 'borough' => 45, 'quarter' => 40,
    'neighbourhood' => 30, 'hamlet' => 30];

$out = fopen($ndjsonPath, 'a');
$db = new PDO('sqlite:' . $sqlitePath);
$db->exec('PRAGMA journal_mode=OFF; PRAGMA synchronous=OFF');
$db->exec('CREATE TABLE IF NOT EXISTS house (street_key INTEGER NOT NULL, number TEXT NOT NULL, postcode TEXT, lat REAL NOT NULL, lon REAL NOT NULL)');
$insert = $db->prepare('INSERT INTO house VALUES (?, ?, ?, ?, ?)');
$db->beginTransaction();

$seen = [];
$places = []; // "cc|name" => docs, deduplicated at the end
$streets = []; // key => doc, written at the end (one per street + town)
$counts = ['place' => 0, 'poi' => 0, 'house' => 0];

$streetKey = static fn (string $cc, string $city, string $street): int
    => (int) hexdec(substr(hash('xxh64', "$cc|$city|$street"), 0, 15));

// every name in one searchable field, so a translation weighs as much as the local name
$names = static function (array $name): array {
    $alt = isset($name['name']) ? [$name['name']] : [];
    foreach (LANGS as $l) {
        if (isset($name["name:$l"]) && $name["name:$l"] !== ($name['name'] ?? null)) {
            $alt[] = $name["name:$l"];
        }
    }
    return array_values(array_unique($alt));
};

while (($line = fgets(STDIN)) !== false) {
    $o = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
    if ($o['type'] !== 'Place') {
        continue;
    }
    foreach ($o['content'] as $p) {
        $a = $p['address'] ?? [];
        $cc = strtoupper($p['country_code'] ?? '');
        $city = $a['city'] ?? $a['town'] ?? $a['village'] ?? $a['locality'] ?? '';
        [$lon, $lat] = $p['centroid'];
        $name = $p['name']['name'] ?? null;
        $key = $p['osm_key'];

        if (isset($p['housenumber'])) {
            $street = $a['street'] ?? $a['place'] ?? '';
            $insert->execute([$streetKey($cc, $city, $street), $p['housenumber'], $p['postcode'] ?? null, $lat, $lon]);
            $counts['house']++;
            if ($street !== '' && !isset($streets[$k = "$cc|$city|$street"])) {
                // a street known only from its houses still has to be findable
                $streets[$k] = [$street, $cc, $city, $a['state'] ?? '', $lat, $lon, [$street]];
            }
            if ($name === null || !in_array($p['osm_value'], POI[$key] ?? [], true)) {
                continue;
            }
        }
        if ($name === null) {
            continue;
        }
        if ($key === 'highway') {
            $k = "$cc|$city|$name";
            if (!isset($streets[$k]) || count($streets[$k][6]) < 2) {
                $streets[$k] = [$name, $cc, $city, $a['state'] ?? '', $lat, $lon, $names($p['name'])];
            }
            continue;
        }
        $isPlace = $key === 'place' && isset(PLACE_RANK[$p['osm_value']]);
        if (!$isPlace && !in_array($key, POI_KEYS, true)) {
            continue;
        }
        if (!$isPlace && !in_array($p['osm_value'], POI[$key], true)) {
            continue;
        }
        // a town comes both as its boundary relation and as its label node, ~same centroid
        $dupKey = sprintf('%s|%s|%s|%.2f|%.2f', $cc, $name, $p['osm_value'], $lat, $lon);
        if (isset($seen[$dupKey])) {
            continue;
        }
        $seen[$dupKey] = true;
        $doc = [
            'id' => $p['object_type'] . $p['object_id'],
            'kind' => $isPlace ? 'place' : 'poi',
            'type' => $p['osm_value'],
            'name' => $name,
            'names' => $names($p['name']),
            'city' => $isPlace ? ($a['city'] ?? $a['town'] ?? '') : $city,
            'region' => $a['state'] ?? '',
            'cc' => $cc,
            'postcode' => $p['postcode'] ?? null,
            '_geo' => ['lat' => $lat, 'lng' => $lon],
            // kind of place first, Nominatim's importance (Wikipedia links, …) breaks ties
            'rank' => ($isPlace ? PLACE_RANK[$p['osm_value']] : 20) * 1000 + (int) round(($p['importance'] ?? 0) * 999),
        ];
        if ($isPlace) {
            $places["$cc|$name"][] = $doc;
            continue;
        }
        fwrite($out, json_encode($doc, JSON_UNESCAPED_UNICODE) . "\n");
        $counts['poi']++;
    }
}

// One town per name within 5 km: boundary relation, label node and a stray
// suburb of the same name are one place to the user — keep the biggest.
$km = static fn (array $x, array $y): float => 111.2 * sqrt(($x['lat'] - $y['lat']) ** 2
    + (($x['lng'] - $y['lng']) * cos(deg2rad($x['lat']))) ** 2);
foreach ($places as $group) {
    usort($group, static fn (array $x, array $y): int => $y['rank'] <=> $x['rank']);
    $kept = [];
    foreach ($group as $doc) {
        foreach ($kept as $i => $k) {
            if ($km($k['_geo'], $doc['_geo']) < 5) {
                $kept[$i]['postcode'] ??= $doc['postcode'];
                continue 2;
            }
        }
        $kept[] = $doc;
    }
    foreach ($kept as $doc) {
        fwrite($out, json_encode($doc, JSON_UNESCAPED_UNICODE) . "\n");
        $counts['place']++;
    }
}

foreach ($streets as [$name, $cc, $city, $region, $lat, $lon, $alt]) {
    fwrite($out, json_encode([
        'id' => 's' . $streetKey($cc, $city, $name),
        'kind' => 'street', 'type' => 'street', 'name' => $name, 'names' => $alt,
        'city' => $city, 'region' => $region, 'cc' => $cc, 'postcode' => null,
        '_geo' => ['lat' => $lat, 'lng' => $lon], 'rank' => 25000,
    ], JSON_UNESCAPED_UNICODE) . "\n");
}
$db->commit();
$counts['street'] = count($streets);
fwrite(STDERR, json_encode($counts) . ' peak ' . round(memory_get_peak_usage() / 1048576) . " MiB\n");
