<?php

/*
 * A tiny HTTP/1.1 server that keeps every connection open after answering, as Valhalla does: a
 * client reading a body until the socket closes hangs on it, a client reading the body by its
 * Content-Length does not.
 *
 * Prints the port it listens on, then serves until it is killed:
 *  - GET /status: the status of a Valhalla server;
 *  - POST /route: a route of tests/Fixtures/valhalla-route.json;
 *  - anything else: the request headers as a JSON object (lower case names).
 */

$server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
if (false === $server) {
    fwrite(\STDERR, "Cannot listen: $errorMessage\n");
    exit(1);
}
echo parse_url('tcp://'.stream_socket_get_name($server, false), \PHP_URL_PORT), "\n";
fflush(\STDOUT);

$route = (string) file_get_contents(__DIR__.'/valhalla-route.json');
$status = '{"version":"3.5.1","tileset_last_modified":1761177600,"available_actions":["status","route","locate"]}';

/** @var array<int, resource> $clients */
$clients = [];
/** @var array<int, string> $buffers */
$buffers = [];
while (true) {
    $read = [$server, ...array_values($clients)];
    $write = $except = null;
    if (false === stream_select($read, $write, $except, null)) {
        break;
    }
    foreach ($read as $stream) {
        if ($stream === $server) {
            $client = stream_socket_accept($server);
            if (false !== $client) {
                $clients[(int) $client] = $client;
                $buffers[(int) $client] = '';
            }
            continue;
        }

        $id = (int) $stream;
        $data = fread($stream, 65536);
        if (false === $data || ('' === $data && feof($stream))) {
            fclose($stream);
            unset($clients[$id], $buffers[$id]);
            continue;
        }
        $buffers[$id] .= $data;

        // answer every complete request of the buffer
        while (false !== $end = strpos($buffers[$id], "\r\n\r\n")) {
            $lines = explode("\r\n", substr($buffers[$id], 0, $end));
            $headers = [];
            foreach (array_slice($lines, 1) as $line) {
                [$name, $value] = array_map(trim(...), explode(':', $line, 2)) + [1 => ''];
                $headers[strtolower($name)] = $value;
            }
            $length = (int) ($headers['content-length'] ?? 0);
            if (strlen($buffers[$id]) < $end + 4 + $length) {
                break;
            }
            $buffers[$id] = substr($buffers[$id], $end + 4 + $length);

            $path = explode(' ', $lines[0])[1] ?? '/';
            $body = match ($path) {
                '/status' => $status,
                '/route' => $route,
                default => json_encode($headers, \JSON_THROW_ON_ERROR),
            };
            fwrite($stream, "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: ".strlen($body)."\r\nConnection: keep-alive\r\n\r\n".$body);
        }
    }
}
