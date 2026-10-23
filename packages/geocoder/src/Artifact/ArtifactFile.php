<?php

namespace Tito10047\Geocoder\Artifact;

use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * A file of an artifact, with what verifies it.
 */
final readonly class ArtifactFile
{
    /**
     * @param string $name   the name of the file in the directory of the artifact
     * @param int    $size   bytes
     * @param string $sha256 the SHA-256 of its content, hexadecimal
     *
     * @throws InvalidArgumentException when a value is invalid
     */
    public function __construct(
        public string $name,
        public int $size,
        public string $sha256,
    ) {
        if ('' === $name || basename($name) !== $name || '.' === $name || '..' === $name) {
            throw new InvalidArgumentException(\sprintf('The name of a file of an artifact must be a plain file name, "%s" given.', $name));
        }
        if ($size < 0) {
            throw new InvalidArgumentException(\sprintf('The size of the file "%s" must not be negative.', $name));
        }
        if (1 !== preg_match('/^[0-9a-f]{64}$/D', $sha256)) {
            throw new InvalidArgumentException(\sprintf('The checksum of the file "%s" must be a SHA-256 in lower-case hexadecimal.', $name));
        }
    }
}
