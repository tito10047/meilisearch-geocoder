<?php

namespace Tito10047\Geocoder\Tests\Index\Fixtures;

use Meilisearch\Http\Client as MeilisearchHttp;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 client answering scripted responses in order and recording the requests.
 *
 * Every request must match the next expectation (method and decoded path with query), so a test
 * describes the exact conversation with Meilisearch.
 */
final class FakeHttpClient implements ClientInterface
{
    /** @var list<array{method: string, target: string, response: ResponseInterface|\Throwable}> */
    private array $expectations = [];

    /** @var list<RequestInterface> */
    public array $requests = [];

    /**
     * The HTTP layer of meilisearch-php over this client.
     */
    public function meilisearch(): MeilisearchHttp
    {
        $factory = new Psr17Factory();

        return new MeilisearchHttp('http://meilisearch', 'secret', $this, $factory, [], $factory);
    }

    /**
     * @param array<array-key, mixed>|null $body JSON body of the response
     */
    public function expect(string $method, string $target, ?array $body, int $status = 200): self
    {
        $response = null === $body
            ? new Response($status)
            : new Response($status, ['Content-Type' => 'application/json'], json_encode($body, \JSON_THROW_ON_ERROR));
        $this->expectations[] = ['method' => $method, 'target' => $target, 'response' => $response];

        return $this;
    }

    public function expectFailure(string $method, string $target, \Throwable $exception): self
    {
        $this->expectations[] = ['method' => $method, 'target' => $target, 'response' => $exception];

        return $this;
    }

    /**
     * Expects a request answered by a summary of an enqueued task.
     */
    public function expectTask(string $method, string $target, int $taskUid, string $type, ?string $indexUid): self
    {
        return $this->expect($method, $target, ['taskUid' => $taskUid, 'indexUid' => $indexUid, 'status' => 'enqueued', 'type' => $type, 'enqueuedAt' => '2026-10-23T08:00:00Z'], 202);
    }

    /**
     * Expects GET /tasks for the given uids, answered with the given statuses (uid => status).
     *
     * @param array<int, string> $statuses
     */
    public function expectTaskStatuses(array $statuses, string $type = 'documentAdditionOrUpdate', ?string $indexUid = 'geo'): self
    {
        $results = [];
        foreach ($statuses as $uid => $status) {
            $results[] = [
                'uid' => $uid,
                'indexUid' => $indexUid,
                'status' => $status,
                'type' => $type,
                'error' => 'failed' === $status ? ['message' => 'Document doesn\'t have a `id` attribute.', 'code' => 'missing_document_id', 'type' => 'invalid_request', 'link' => ''] : null,
            ];
        }

        return $this->expect('GET', \sprintf('/tasks/?uids=%s&limit=%d', implode(',', array_keys($statuses)), \count($statuses)), ['results' => $results, 'total' => \count($results), 'limit' => \count($results), 'from' => null, 'next' => null]);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $target = urldecode($request->getUri()->getPath().('' === $request->getUri()->getQuery() ? '' : '?'.$request->getUri()->getQuery()));

        $expectation = array_shift($this->expectations);
        if (null === $expectation) {
            throw new \LogicException(\sprintf('Unexpected request %s %s.', $request->getMethod(), $target));
        }
        if ($expectation['method'] !== $request->getMethod() || $expectation['target'] !== $target) {
            throw new \LogicException(\sprintf('Expected the request %s %s, got %s %s.', $expectation['method'], $expectation['target'], $request->getMethod(), $target));
        }
        if ($expectation['response'] instanceof \Throwable) {
            throw $expectation['response'];
        }

        return $expectation['response'];
    }

    public function remaining(): int
    {
        return \count($this->expectations);
    }

    public function request(int $index): RequestInterface
    {
        return $this->requests[$index] ?? throw new \LogicException(\sprintf('There is no request %d.', $index));
    }

    /**
     * The decoded JSON body of a recorded request.
     *
     * @return array<array-key, mixed>
     */
    public function jsonBody(int $request): array
    {
        $body = json_decode((string) $this->request($request)->getBody(), true, 512, \JSON_THROW_ON_ERROR);
        if (!\is_array($body)) {
            throw new \LogicException('The body of the request is not a JSON object or array.');
        }

        return $body;
    }
}
