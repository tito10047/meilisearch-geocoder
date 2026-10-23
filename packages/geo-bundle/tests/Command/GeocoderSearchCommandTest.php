<?php

namespace Tito10047\GeoBundle\Tests\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Tito10047\GeoBundle\Attribution;
use Tito10047\GeoBundle\Tests\App\Configs;
use Tito10047\GeoBundle\Tests\App\TestApplication;
use Tito10047\GeoBundle\Tests\Fake\FakeGeocoder;
use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Exception\GeocoderUnavailableException;
use Tito10047\Geocoder\Search\Kind;
use Tito10047\Geocoder\Search\SearchOptions;
use Tito10047\Geocoder\Search\Suggestion;

final class GeocoderSearchCommandTest extends TestCase
{
    private TestApplication $application;
    private FakeGeocoder $geocoder;

    protected function setUp(): void
    {
        $this->application = new TestApplication(Configs::geocoder(['search' => ['default_limit' => 3, 'preferred_country' => 'SK']]));
        $this->geocoder = $this->application->service('tito10047_geo.geocoder', FakeGeocoder::class);
    }

    protected function tearDown(): void
    {
        $this->application->shutdown();
    }

    public function testPrintsTheSuggestionsAndTheAttribution(): void
    {
        $this->geocoder->suggestions = [
            new Suggestion('s1#12', Kind::Address, 'house', 'Hlavná 12', 'Hlavná 12, Košice, Slovensko', new Coordinates(48.7203, 21.2577), 'SK'),
            new Suggestion('s1', Kind::Street, 'street', 'Hlavná', 'Hlavná, Košice, Slovensko', new Coordinates(48.72, 21.258), 'SK'),
        ];
        $tester = $this->application->command('tito10047-geo:geocoder:search');

        $tester->execute(['text' => 'Hlavná 12 Košice']);

        $tester->assertCommandIsSuccessful();
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Hlavná 12, Košice, Slovensko', $display);
        $this->assertStringContainsString('48.720300,21.257700', $display);
        $this->assertStringContainsString('address', $display);
        $this->assertStringContainsString(Attribution::OPENSTREETMAP, $display);
        $this->assertEquals([['Hlavná 12 Košice', new SearchOptions(limit: 3, preferredCountry: 'SK')]], $this->geocoder->calls);
    }

    public function testOptionsOverrideTheDefaults(): void
    {
        $tester = $this->application->command('tito10047-geo:geocoder:search');

        $tester->execute(['text' => 'wien', '--locale' => 'sk', '--country' => ['at', 'SK', 'AT'], '--preferred-country' => 'at', '--limit' => '10']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Nothing found.', $tester->getDisplay());
        $this->assertEquals([['wien', new SearchOptions('sk', 10, 'AT', ['AT', 'SK'])]], $this->geocoder->calls);
    }

    public function testEmptyPreferredCountryRemovesTheDefault(): void
    {
        $tester = $this->application->command('tito10047-geo:geocoder:search');

        $tester->execute(['text' => 'wien', '--preferred-country' => '']);

        $this->assertEquals([['wien', new SearchOptions(limit: 3)]], $this->geocoder->calls);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidOptions(): iterable
    {
        yield 'limit not a number' => [['--limit' => 'ten']];
        yield 'limit out of range' => [['--limit' => '21']];
        yield 'invalid locale' => [['--locale' => 'Slovak']];
        yield 'invalid country' => [['--country' => ['SVK']]];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('invalidOptions')]
    public function testInvalidOptionIsReported(array $options): void
    {
        $tester = $this->application->command('tito10047-geo:geocoder:search');

        $tester->execute(['text' => 'wien'] + $options);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertSame([], $this->geocoder->calls);
    }

    public function testUnavailableGeocoderFails(): void
    {
        $this->geocoder->exception = new GeocoderUnavailableException('Meilisearch is down.');
        $tester = $this->application->command('tito10047-geo:geocoder:search');

        $tester->execute(['text' => 'wien']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Meilisearch is down.', $tester->getDisplay());
    }
}
