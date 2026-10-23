<?php

namespace Tito10047\Geocoder\Tests\Fake;

use Tito10047\Geocoder\Import\Workspace\ConversionWorkspaceFactoryInterface;
use Tito10047\Geocoder\Import\Workspace\ConversionWorkspaceInterface;
use Tito10047\Geocoder\Import\Workspace\SqliteConversionWorkspaceFactory;

/**
 * Creates SQLite workspaces in a directory and remembers them, to check they are closed.
 */
final class TrackingWorkspaceFactory implements ConversionWorkspaceFactoryInterface
{
    /** @var list<TrackingWorkspace> */
    public array $workspaces = [];

    public function __construct(
        private readonly SqliteConversionWorkspaceFactory $factory,
    ) {
    }

    public function create(): ConversionWorkspaceInterface
    {
        return $this->workspaces[] = new TrackingWorkspace($this->factory->create());
    }
}
