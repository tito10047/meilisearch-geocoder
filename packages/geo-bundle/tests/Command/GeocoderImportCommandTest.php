<?php

namespace Tito10047\GeoBundle\Tests\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Tito10047\GeoBundle\Attribution;
use Tito10047\GeoBundle\Tests\App\Configs;
use Tito10047\GeoBundle\Tests\App\TestApplication;
use Tito10047\GeoBundle\Tests\Fake\Builds;
use Tito10047\GeoBundle\Tests\Fake\FakeFileExporter;
use Tito10047\GeoBundle\Tests\Fake\FakeImporter;
use Tito10047\Geocoder\Exception\GeocoderUnavailableException;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\StorageException;
use Tito10047\Geocoder\Index\Exception\MeilisearchException;

final class GeocoderImportCommandTest extends TestCase
{
    private const COMMAND = 'geo:import';

    private ?TestApplication $application = null;
    private ?string $directory = null;

    protected function tearDown(): void
    {
        $this->application?->shutdown();
        if (null !== $this->directory) {
            (new Filesystem())->remove($this->directory);
        }
    }

    public function testImportsAndPublishesTheDumps(): void
    {
        $tester = $this->application()->command(self::COMMAND);

        $tester->execute(['dumps' => ['slovakia.jsonl.zst', 'czechia.jsonl.zst']]);

        $tester->assertCommandIsSuccessful();
        $importer = $this->importer();
        $this->assertSame([['slovakia.jsonl.zst', 'czechia.jsonl.zst']], $importer->imports);
        $this->assertSame([Builds::UID], $importer->published);
        $display = $this->display($tester);
        $this->assertStringContainsString('The build '.Builds::UID.' is live.', $display);
        $this->assertStringContainsString('4 234 567', $display, 'The house numbers of the build.');
        $this->assertStringContainsString('CZ 2026-10-16, SK 2026-10-17', $display, 'The data timestamps of the countries.');
        $this->assertStringContainsString('2026-10-17 23:02:06', $display, 'The data timestamp of a dump.');
        $this->assertStringContainsString('slovakia.jsonl.zst: 10 places, 20 streets, 30 points of interest, 40 house numbers', $display, 'The progress.');
        $this->assertStringContainsString(Attribution::OPENSTREETMAP, $display);
    }

    public function testDrawsAProgressBarOnATerminal(): void
    {
        $tester = $this->application()->command(self::COMMAND);

        $tester->execute(['dumps' => ['slovakia.jsonl.zst']], ['decorated' => true]);

        $tester->assertCommandIsSuccessful();
        $display = $tester->getDisplay(true);
        $this->assertStringContainsString('slovakia.jsonl.zst: 10 places, 20 streets, 30 points of interest, 40 house numbers', $display);
    }

    public function testBuildsWithoutPublishing(): void
    {
        $tester = $this->application()->command(self::COMMAND);

        $tester->execute(['dumps' => ['slovakia.jsonl.zst'], '--no-publish' => true]);

        $tester->assertCommandIsSuccessful();
        $this->assertSame([], $this->importer()->published);
        $this->assertStringContainsString('geo:publish '.Builds::UID, $this->display($tester));
    }

    public function testImportsTheDumpsOfTheConfiguredDirectory(): void
    {
        $directory = $this->directory();
        foreach (['b.jsonl', 'a.jsonl.zst', 'notes.txt', 'c.json'] as $file) {
            touch($directory.'/'.$file);
        }
        $tester = $this->application(Configs::geocoder(['import' => ['dumps_dir' => $directory]]))->command(self::COMMAND);

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertSame([[$directory.'/a.jsonl.zst', $directory.'/b.jsonl']], $this->importer()->imports);
    }

    /**
     * @return iterable<string, array{string|null, string}>
     */
    public static function missingDumps(): iterable
    {
        yield 'no directory configured' => [null, 'Give the dumps to import, or configure their directory'];
        yield 'empty directory' => ['', 'There is no dump (*.jsonl.zst or *.jsonl)'];
        yield 'missing directory' => ['/missing', 'does not exist'];
    }

    #[DataProvider('missingDumps')]
    public function testFailsWithoutDumps(?string $subdirectory, string $error): void
    {
        $directory = null === $subdirectory ? null : $this->directory().$subdirectory;
        $tester = $this->application(Configs::geocoder(['import' => ['dumps_dir' => $directory]]))->command(self::COMMAND);

        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString($error, $this->display($tester));
        $this->assertSame([], $this->importer()->imports);
    }

    public function testExportsIntoFilesOnly(): void
    {
        $tester = $this->application()->command(self::COMMAND);

        $tester->execute(['dumps' => ['slovakia.jsonl.zst'], '--export' => '/srv/export']);

        $tester->assertCommandIsSuccessful();
        $exporter = $this->application()->service('geo.geocoder.file_exporter', FakeFileExporter::class);
        $this->assertSame([[['slovakia.jsonl.zst'], '/srv/export']], $exporter->exports);
        $this->assertSame([], $this->importer()->imports);
        $display = $this->display($tester);
        $this->assertStringContainsString('Exported into "/srv/export"', $display);
        $this->assertStringContainsString(self::COMMAND.' "/srv/export"', $display);
    }

    public function testExportsWithoutTheUrlOfMeilisearch(): void
    {
        $tester = $this->application(Configs::geocoder(['meilisearch' => ['client' => 'app.meilisearch']]))->command(self::COMMAND);

        $tester->execute(['dumps' => ['slovakia.jsonl.zst'], '--export' => '/srv/export']);

        $tester->assertCommandIsSuccessful();
    }

    public function testImportingNeedsTheUrlOfMeilisearch(): void
    {
        $tester = $this->application(Configs::geocoder(['meilisearch' => ['client' => 'app.meilisearch']]))->command(self::COMMAND);

        $tester->execute(['dumps' => ['slovakia.jsonl.zst']]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('--export=<dir>', $this->display($tester));
    }

    /**
     * @return iterable<string, array{\Throwable}>
     */
    public static function failures(): iterable
    {
        yield 'not a dump' => [new InvalidArgumentException('"x.jsonl" is not a Photon dump.')];
        yield 'search engine' => [new MeilisearchException('Meilisearch is down.')];
        yield 'database' => [new StorageException('The disk is full.')];
        yield 'connection' => [new GeocoderUnavailableException('The database of the geocoder cannot be connected to: unable to open database file')];
    }

    #[DataProvider('failures')]
    public function testFailedImportFails(\Throwable $failure): void
    {
        $this->importer()->importException = $failure;
        $tester = $this->application()->command(self::COMMAND);

        $tester->execute(['dumps' => ['slovakia.jsonl.zst']]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $display = $this->display($tester);
        $this->assertStringContainsString($failure->getMessage(), $display);
        $this->assertStringNotContainsString('Exception', $display, 'No stack trace.');
        $this->assertSame([], $this->importer()->published);
    }

    #[DataProvider('failures')]
    public function testFailedExportFails(\Throwable $failure): void
    {
        $this->application()->service('geo.geocoder.file_exporter', FakeFileExporter::class)->exception = $failure;
        $tester = $this->application()->command(self::COMMAND);

        $tester->execute(['dumps' => ['slovakia.jsonl.zst'], '--export' => '/srv/export']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString($failure->getMessage(), $this->display($tester));
    }

    public function testFailedPublicationFails(): void
    {
        $this->importer()->publishException = new MeilisearchException('The swap failed.');
        $tester = $this->application()->command(self::COMMAND);

        $tester->execute(['dumps' => ['slovakia.jsonl.zst']]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('The build '.Builds::UID.' was imported but not published: The swap failed.', $this->display($tester));
    }

    /**
     * The output with its blocks unwrapped.
     */
    private function display(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay(true));
    }

    /**
     * @param array<string, mixed>|null $config
     */
    private function application(?array $config = null): TestApplication
    {
        return $this->application ??= new TestApplication($config ?? Configs::geocoder());
    }

    private function importer(): FakeImporter
    {
        return $this->application()->service('geo.geocoder.importer', FakeImporter::class);
    }

    private function directory(): string
    {
        $this->directory = sys_get_temp_dir().'/geo-bundle-import-'.bin2hex(random_bytes(6));
        mkdir($this->directory);

        return $this->directory;
    }
}
