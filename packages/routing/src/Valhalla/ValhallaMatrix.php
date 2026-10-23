<?php

namespace Tito10047\Routing\Valhalla;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tito10047\Routing\Exception\InvalidArgumentException;
use Tito10047\Routing\Exception\RouteNotFoundException;
use Tito10047\Routing\Exception\RoutingUnavailableException;
use Tito10047\Routing\Matrix;
use Tito10047\Routing\MatrixInterface;
use Tito10047\Routing\MatrixRequest;

/**
 * Computes distance matrices with a Valhalla server (`/sources_to_targets`), through any PSR-18
 * HTTP client.
 *
 * Valhalla's limits per costing (`service_limits` of its configuration; the defaults):
 * - `max_matrix_location_pairs`, 2,500 sources × targets: a larger matrix is refused with error
 *   150. This class checks its own maximum, the same by default, before asking; configure it
 *   as the server is configured.
 * - `max_matrix_distance`, 400 km for `auto` and `truck`, 200 km for `bicycle` and
 *   `pedestrian`: a source and a target farther apart in a straight line make Valhalla refuse
 *   the whole matrix (error 154, a {@see RoutingUnavailableException}); a pair closer in a
 *   straight line but farther by road comes back without a route (null), as an unreachable one.
 *
 * A point outside the data of the server makes Valhalla refuse the whole matrix with error 170
 * (unlike `/route`, which answers 171): a {@see RoutingUnavailableException} as well, see
 * {@see ErrorTranslator::forMatrix()}.
 *
 * PSR-18 has no timeout: set it on the client, as for {@see ValhallaRouter}.
 */
final readonly class ValhallaMatrix implements MatrixInterface
{
    public const DEFAULT_MAX_LOCATION_PAIRS = 2500;

    private HttpTransport $transport;

    /**
     * The client and the factories are discovered (php-http/discovery) when not given.
     *
     * @param string $baseUrl          the URL of the Valhalla server, e.g. "http://localhost:8002"
     * @param int    $maxLocationPairs the most sources × targets asked at once, 1 or more: the server's
     *                                 `max_matrix_location_pairs`
     *
     * @throws InvalidArgumentException when the URL is not an absolute HTTP(S) URL, the maximum is below 1,
     *                                  or no client or factory is given and none can be discovered
     */
    public function __construct(
        string $baseUrl,
        ?ClientInterface $client = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        private LoggerInterface $logger = new NullLogger(),
        private MatrixRequestEncoderInterface $encoder = new MatrixRequestEncoder(),
        private MatrixResponseParserInterface $parser = new MatrixResponseParser(),
        ?ErrorTranslatorInterface $errorTranslator = null,
        private int $maxLocationPairs = self::DEFAULT_MAX_LOCATION_PAIRS,
    ) {
        if ($maxLocationPairs < 1) {
            throw new InvalidArgumentException(\sprintf('The maximum of location pairs must be at least 1, %d given.', $maxLocationPairs));
        }
        $this->transport = new HttpTransport($baseUrl, $client, $requestFactory, $streamFactory, $logger, $errorTranslator ?? ErrorTranslator::forMatrix());
    }

    public function matrix(MatrixRequest $request): Matrix
    {
        if ($request->pairs() > $this->maxLocationPairs) {
            throw new InvalidArgumentException(\sprintf('The matrix of %d × %d = %d location pairs exceeds the maximum of %d.', \count($request->sources), \count($request->targets), $request->pairs(), $this->maxLocationPairs));
        }

        try {
            [$matrix, $context] = $this->transport->post(
                'sources_to_targets',
                $this->encoder->encode($request),
                ['sources' => \count($request->sources), 'targets' => \count($request->targets), 'costing' => $request->costing->value],
                fn (string $body): Matrix => $this->parser->parse($body, $request),
            );
        } catch (RouteNotFoundException $e) {
            // only a custom error translator says so: a matrix has no "not found", the pairs are null
            throw new RoutingUnavailableException($e->getMessage(), $e->getCode(), $e);
        }
        $this->logger->debug('Valhalla computed a {sources} × {targets} matrix in {milliseconds} ms.', $context);

        return $matrix;
    }
}
