<?php

namespace Tito10047\Routing\Valhalla;

use Tito10047\Routing\Exception\RoutingUnavailableException;
use Tito10047\Routing\Matrix;
use Tito10047\Routing\MatrixRequest;

/**
 * Reads the successful answer of Valhalla's `/sources_to_targets` into a matrix.
 */
interface MatrixResponseParserInterface
{
    /**
     * @param string        $body    the JSON body of the answer
     * @param MatrixRequest $request the request answered: the matrix must have a row per source and a column per target
     *
     * @throws RoutingUnavailableException when the body is not the matrix asked for
     */
    public function parse(string $body, MatrixRequest $request): Matrix;
}
