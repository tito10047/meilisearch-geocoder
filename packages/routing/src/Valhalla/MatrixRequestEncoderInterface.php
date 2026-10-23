<?php

namespace Tito10047\Routing\Valhalla;

use Tito10047\Routing\MatrixRequest;

/**
 * Turns a matrix request into the JSON body of Valhalla's `/sources_to_targets`.
 *
 * The body must ask for what a {@see MatrixResponseParserInterface} reads: the concise answer
 * (`verbose: false`) in kilometres.
 */
interface MatrixRequestEncoderInterface
{
    /**
     * @return string the JSON body
     */
    public function encode(MatrixRequest $request): string;
}
