<?php

namespace Tito10047\GeoBundle\Tests\Fixtures;

/**
 * Runs keep-alive-server.php in a process of its own, for the tests of the HTTP clients.
 */
final class KeepAliveServer
{
    /** @var resource|null */
    private $process;

    /** @var array<int, resource> */
    private array $pipes = [];

    private readonly string $url;

    public function __construct()
    {
        $process = proc_open([\PHP_BINARY, __DIR__.'/keep-alive-server.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $this->pipes);
        if (false === $process) {
            throw new \RuntimeException('The test server cannot be started.');
        }
        $this->process = $process;

        $port = trim((string) fgets($this->pipes[1]));
        if (1 !== preg_match('/^\d+$/D', $port)) {
            $error = stream_get_contents($this->pipes[2]);
            $this->stop();

            throw new \RuntimeException(\sprintf('The test server did not start: %s', $error));
        }
        $this->url = 'http://127.0.0.1:'.$port;
    }

    public function __destruct()
    {
        $this->stop();
    }

    /**
     * The base URL of the server, "http://127.0.0.1:<port>".
     */
    public function url(): string
    {
        return $this->url;
    }

    public function stop(): void
    {
        if (null === $this->process) {
            return;
        }

        proc_terminate($this->process);
        foreach ($this->pipes as $pipe) {
            fclose($pipe);
        }
        proc_close($this->process);
        $this->process = null;
        $this->pipes = [];
    }
}
