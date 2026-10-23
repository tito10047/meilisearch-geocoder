<?php

namespace Tito10047\Geocoder\Import\Workspace;

use Tito10047\Geocoder\Exception\RuntimeException;

/**
 * Creates an empty workspace for each conversion.
 */
interface ConversionWorkspaceFactoryInterface
{
    /**
     * @throws RuntimeException when the storage cannot be created
     */
    public function create(): ConversionWorkspaceInterface;
}
