<?php

namespace Tito10047\GeoBundle\Tests\Command\Output;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tito10047\GeoBundle\Command\Output\BuildPrinter;
use Tito10047\GeoBundle\Tests\Fake\Builds;
use Tito10047\Geocoder\Import\Build;
use Tito10047\Geocoder\Index\IndexSettings;

final class BuildPrinterTest extends TestCase
{
    public function testPrintsTheBuild(): void
    {
        $display = $this->print(Builds::build());

        $this->assertStringContainsString(Builds::UID, $display);
        $this->assertStringContainsString('2026-10-23 12:00:00 UTC', $display);
        $this->assertStringContainsString('212 000 (17 900 places, 181 000 streets, 13 100 points of interest)', $display);
        $this->assertStringContainsString('4 234 567', $display);
        $this->assertStringContainsString('CZ 2026-10-16, SK 2026-10-17', $display);
        $this->assertStringContainsString('the default settings', $display);
        $this->assertMatchesRegularExpression('/slovakia\.jsonl\.zst +SK +2026-10-17 23:02:06 UTC +2 900 +61 000 +4 100 +1 234 567/', $display);
    }

    public function testTellsCustomSettings(): void
    {
        $build = Builds::build();
        $settings = (new IndexSettings(maxTotalHits: 50))->hash();

        $display = $this->print(new Build($build->indexUid, $build->tables, $build->startedAt, $settings, 1, $build->dumps));

        $this->assertStringContainsString('custom settings ('.substr($settings, 0, 12).')', $display);
    }

    private function print(Build $build): string
    {
        $output = new BufferedOutput();
        (new BuildPrinter())->print(new SymfonyStyle(new ArrayInput([]), $output), $build);

        return $output->fetch();
    }
}
