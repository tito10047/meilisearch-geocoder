<?php

namespace Tito10047\GeoBundle\Tests\Fake;

use Tito10047\Routing\Matrix;
use Tito10047\Routing\MatrixInterface;
use Tito10047\Routing\MatrixRequest;

/**
 * Answers the matrix it is given and records the requests.
 */
final class FakeMatrix implements MatrixInterface
{
    public ?Matrix $matrix = null;

    public ?\Throwable $exception = null;

    /** @var list<MatrixRequest> */
    public array $requests = [];

    public function matrix(MatrixRequest $request): Matrix
    {
        $this->requests[] = $request;
        if ($this->exception instanceof \Throwable) {
            throw $this->exception;
        }

        return $this->matrix ?? throw new \LogicException('No matrix given to the fake.');
    }
}
