<?php

namespace Tito10047\Geocoder\Import\Workspace;

use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\IoException;

/**
 * Creates each workspace as a new SQLite file in a directory, the system temporary one by default.
 *
 * The file grows to a fraction of the dump (the places and streets it selects, as JSON).
 */
final readonly class SqliteConversionWorkspaceFactory implements ConversionWorkspaceFactoryInterface
{
    private string $directory;

    /**
     * @param string|null $directory a writable directory, null for the system temporary directory
     *
     * @throws InvalidArgumentException when the directory is not a writable directory
     */
    public function __construct(?string $directory = null)
    {
        $directory ??= sys_get_temp_dir();
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new InvalidArgumentException(\sprintf('The workspace directory "%s" is not a writable directory.', $directory));
        }
        $this->directory = $directory;
    }

    public function create(): ConversionWorkspaceInterface
    {
        $path = @tempnam($this->directory, 'geocoder-workspace-');
        if (false === $path) {
            throw new IoException(\sprintf('Cannot create a temporary file in "%s".', $this->directory));
        }

        return new SqliteConversionWorkspace($path);
    }
}
