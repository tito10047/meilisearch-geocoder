<?php

namespace Tito10047\Geocoder\Tests\Fake;

use Tito10047\Geocoder\Import\Build;
use Tito10047\Geocoder\Import\Exception\BuildNotFoundException;
use Tito10047\Geocoder\Import\ImporterInterface;
use Tito10047\Geocoder\Import\ImportProgressInterface;

/**
 * Answers the live build it is given, or throws the exception it is given; nothing else.
 */
final class FakeImporter implements ImporterInterface
{
    public ?Build $live = null;

    public ?\Throwable $liveException = null;

    public function import(iterable $dumps, ?ImportProgressInterface $progress = null): Build
    {
        throw new \LogicException('Not faked.');
    }

    public function publish(Build $build): Build
    {
        throw new \LogicException('Not faked.');
    }

    public function build(string $indexUid): Build
    {
        throw new \LogicException('Not faked.');
    }

    public function live(): Build
    {
        if ($this->liveException instanceof \Throwable) {
            throw $this->liveException;
        }

        return $this->live ?? throw new BuildNotFoundException('Nothing was published yet.');
    }

    public function cleanup(array $keep = []): array
    {
        throw new \LogicException('Not faked.');
    }
}
