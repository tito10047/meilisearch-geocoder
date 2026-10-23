<?php

namespace Tito10047\GeoBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tito10047\GeoBundle\Attribution;
use Tito10047\Geocoder\Exception\GeocoderNotConfiguredException;
use Tito10047\Geocoder\Exception\GeocoderUnavailableException;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Search\GeocoderInterface;
use Tito10047\Geocoder\Search\QueryMatch;
use Tito10047\Geocoder\Search\SearchOptions;
use Tito10047\Geocoder\Search\Suggestion;

/**
 * Prints the suggestions of the geocoder for a text, to try the index and its ranking.
 */
#[AsCommand(name: 'geo:search', description: 'Prints the suggestions of the geocoder for a text', help: <<<'TXT'
The <info>%command.name%</info> command prints what the geocoder suggests for a text:

  <info>php %command.full_name% "Hlavná 12 Košice" --locale=sk --preferred-country=SK</info>

The "Match" column tells how many words of the text (without the house number) each
suggestion matched; <info>--complete-only</info> keeps the suggestions matching all of them, as an
application falling back to another geocoder would ask:

  <info>php %command.full_name% "Champs-Élysées, Paris" --complete-only</info>

The defaults of the limit, of the preferred country and of the complete matches come from
the configuration (geo.geocoder.search).
TXT)]
final class GeocoderSearchCommand extends Command
{
    /**
     * @param SearchOptions $defaults the configured defaults (limit, preferred country, complete matches only)
     */
    public function __construct(
        private readonly GeocoderInterface $geocoder,
        private readonly SearchOptions $defaults,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('text', InputArgument::REQUIRED, 'What the user typed, e.g. "Hlavná 12 Košice"')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'The language of the labels, e.g. "sk"; the local names by default')
            ->addOption('country', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only this country (ISO 3166-1 alpha-2), repeatable')
            ->addOption('preferred-country', null, InputOption::VALUE_REQUIRED, 'The country winning among suggestions of the same name ("" for none)')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, \sprintf('The number of suggestions, 1 to %d', SearchOptions::MAX_LIMIT))
            ->addOption('complete-only', null, InputOption::VALUE_NEGATABLE, 'Only the suggestions matching every word of the text (--no-complete-only for all of them)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $options = $this->options($input);
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        /** @var string $text */
        $text = $input->getArgument('text');
        try {
            $suggestions = $this->geocoder->suggest($text, $options);
        } catch (GeocoderNotConfiguredException $e) {
            $io->error($e->getMessage());
            $io->note('The geocoder is switched off by the configuration: set geo.geocoder.meilisearch.url to switch it on.');

            return Command::FAILURE;
        } catch (GeocoderUnavailableException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if ([] === $suggestions) {
            $io->warning($options->completeMatchesOnly ? 'Nothing matches every word.' : 'Nothing found.');
        } else {
            $io->table(
                ['#', 'Kind', 'Type', 'Label', 'Match', 'Coordinates', 'Country', 'Id'],
                array_map(static fn (int $i, Suggestion $suggestion): array => [
                    $i + 1,
                    $suggestion->kind->value,
                    $suggestion->type,
                    $suggestion->label,
                    self::match($suggestion->match),
                    \sprintf('%.6F,%.6F', $suggestion->coordinates->latitude, $suggestion->coordinates->longitude),
                    $suggestion->countryCode,
                    $suggestion->id,
                ], array_keys($suggestions), $suggestions),
            );
        }
        $io->text(Attribution::OPENSTREETMAP);

        return Command::SUCCESS;
    }

    /**
     * @throws InvalidArgumentException when an option is invalid
     */
    private function options(InputInterface $input): SearchOptions
    {
        $options = $this->defaults;

        $locale = $input->getOption('locale');
        if (\is_string($locale)) {
            $options = $options->withLocale($locale);
        }

        /** @var list<string> $countries */
        $countries = $input->getOption('country');
        if ([] !== $countries) {
            $options = $options->withCountries(array_values(array_unique(array_map(strtoupper(...), $countries))));
        }

        $preferredCountry = $input->getOption('preferred-country');
        if (\is_string($preferredCountry)) {
            $options = $options->withPreferredCountry('' === $preferredCountry ? null : strtoupper($preferredCountry));
        }

        $completeOnly = $input->getOption('complete-only');
        if (\is_bool($completeOnly)) {
            $options = $options->withCompleteMatchesOnly($completeOnly);
        }

        $limit = $input->getOption('limit');
        if (\is_string($limit)) {
            $number = 1 === preg_match('/^\d{1,3}$/D', $limit) ? (int) $limit : 0;
            if ($number < 1 || $number > SearchOptions::MAX_LIMIT) {
                throw new InvalidArgumentException(\sprintf('The limit must be a number from 1 to %d, "%s" given.', SearchOptions::MAX_LIMIT, $limit));
            }
            $options = $options->withLimit($number);
        }

        return $options;
    }

    /**
     * "2/3 words", "3/3 words (complete)"; "unknown" when the search engine does not tell.
     */
    private static function match(?QueryMatch $match): string
    {
        if (!$match instanceof QueryMatch) {
            return 'unknown';
        }

        return \sprintf('%d/%d%s', $match->matchedWords, $match->queryWords, $match->isComplete() ? ' complete' : '');
    }
}
