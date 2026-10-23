<?php

namespace Tito10047\GeoBundle\Tests\Command\Output;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Tito10047\GeoBundle\Command\Output\ConsoleDownloadProgress;

final class ConsoleDownloadProgressTest extends TestCase
{
    public function testWritesNothingWithoutAnsiCodes(): void
    {
        $output = new BufferedOutput();
        $progress = new ConsoleDownloadProgress($output);

        $progress->advance('europe/slovakia', 0, 1 << 20);
        $progress->advance('europe/slovakia', 1 << 20, 1 << 20);
        $progress->finish();

        $this->assertSame('', $output->fetch());
    }

    public function testDrawsABarOfAKnownSize(): void
    {
        $output = new BufferedOutput(decorated: true);
        $progress = new ConsoleDownloadProgress($output);

        $progress->advance('europe/slovakia', 0, 3 << 20);
        $progress->advance('europe/slovakia', 3 << 20, 3 << 20);
        $progress->finish();

        $display = $output->fetch();
        $this->assertStringContainsString('europe/slovakia: 3.0 MiB of 3.0 MiB', $display);
        $this->assertStringContainsString('100%', $display);
    }

    public function testCountsADownloadOfAnUnknownSize(): void
    {
        $output = new BufferedOutput(decorated: true);
        $progress = new ConsoleDownloadProgress($output);

        $progress->advance('europe/slovakia', 0, null);
        $progress->advance('europe/slovakia', 2 << 20, null);
        $progress->finish();

        $display = $output->fetch();
        $this->assertStringContainsString('europe/slovakia: 2.0 MiB', $display);
        $this->assertStringNotContainsString(' of ', $display);
    }

    public function testFinishingWithoutProgressWritesNothing(): void
    {
        $output = new BufferedOutput(decorated: true);

        (new ConsoleDownloadProgress($output))->finish();

        $this->assertSame('', $output->fetch());
    }
}
