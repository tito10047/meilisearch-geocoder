<?php

namespace Tito10047\GeoBundle\Tests\Command\Output;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Tito10047\GeoBundle\Command\Output\ConsoleImportProgress;
use Tito10047\Geocoder\Import\ImportCounts;

final class ConsoleImportProgressTest extends TestCase
{
    public function testWritesOneLinePerDumpWithoutAnsiCodes(): void
    {
        $output = new BufferedOutput();
        $progress = new ConsoleImportProgress($output);

        $progress->advance('slovakia.jsonl.zst', new ImportCounts(1, 2, 3, 4));
        $progress->advance('slovakia.jsonl.zst', new ImportCounts(2900, 61000, 4100, 1_234_567));
        $progress->advance('czechia.jsonl.zst', new ImportCounts(5, 6, 7, 8));
        $progress->finish();
        $progress->finish();

        $this->assertSame(
            " slovakia.jsonl.zst: 2 900 places, 61 000 streets, 4 100 points of interest, 1 234 567 house numbers\n"
            ." czechia.jsonl.zst: 5 places, 6 streets, 7 points of interest, 8 house numbers\n",
            $output->fetch(),
        );
    }

    public function testDrawsABarPerDumpOnATerminal(): void
    {
        $output = new BufferedOutput(decorated: true);
        $progress = new ConsoleImportProgress($output);

        $progress->advance('slovakia.jsonl.zst', new ImportCounts(1, 2, 3, 4));
        $progress->advance('czechia.jsonl.zst', new ImportCounts(5, 6, 7, 8));
        $progress->finish();

        $display = $output->fetch();
        $this->assertStringContainsString('slovakia.jsonl.zst: 1 places', $display);
        $this->assertStringContainsString('czechia.jsonl.zst: 5 places, 6 streets, 7 points of interest, 8 house numbers', $display);
    }

    public function testFinishingWithoutProgressWritesNothing(): void
    {
        $output = new BufferedOutput();

        (new ConsoleImportProgress($output))->finish();

        $this->assertSame('', $output->fetch());
    }
}
