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
use Tito10047\Routing\Coordinates;
use Tito10047\Routing\Costing;
use Tito10047\Routing\CountrySegment;
use Tito10047\Routing\Exception\InvalidArgumentException;
use Tito10047\Routing\Exception\RouteNotFoundException;
use Tito10047\Routing\Exception\RoutingUnavailableException;
use Tito10047\Routing\RouteRequest;
use Tito10047\Routing\RouterInterface;

/**
 * Prints the routes through points: distance, duration, countries and tolls per country.
 */
#[AsCommand(name: 'tito10047-geo:routing:route', description: 'Prints the routes through points: distance, duration, countries, tolls', help: <<<'TXT'
The <info>%command.name%</info> command asks Valhalla for the routes through the points:

  <info>php %command.full_name% 48.1486,17.1077 48.7164,21.2611 --alternatives=3</info>
TXT)]
final class RoutingRouteCommand extends Command
{
    /**
     * @param int $radius the configured default of the metres a point may snap to a road
     */
    public function __construct(
        private readonly RouterInterface $router,
        private readonly int $radius,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('points', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Start, stops and destination as "latitude,longitude", at least two')
            ->addOption('alternatives', null, InputOption::VALUE_REQUIRED, 'How many routes at most (honoured between two points only)', '1')
            ->addOption('costing', null, InputOption::VALUE_REQUIRED, \sprintf('How the route is travelled: %s', implode(', ', array_map(static fn (Costing $costing): string => $costing->value, Costing::cases()))), Costing::Car->value)
            ->addOption('radius', null, InputOption::VALUE_REQUIRED, 'Metres a point may snap to a road (the configured default otherwise)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $request = $this->request($input);
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        try {
            $routes = $this->router->route($request);
        } catch (RouteNotFoundException $e) {
            $io->warning($e->getMessage());

            return Command::FAILURE;
        } catch (RoutingUnavailableException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        foreach ($routes as $i => $route) {
            $io->section(\sprintf('Route %d', $i + 1));
            $io->definitionList(
                ['Distance' => self::kilometres($route->distanceMeters)],
                ['Duration' => \sprintf('%d:%02d h', intdiv($route->durationSeconds, 3600), intdiv($route->durationSeconds % 3600, 60))],
                ['Countries' => $this->countries($route->countries)],
                ['Tolls' => $this->perCountry($route->tolledByCountry())],
            );
        }
        $io->text(Attribution::OPENSTREETMAP);

        return Command::SUCCESS;
    }

    /**
     * @throws InvalidArgumentException when an argument or an option is invalid
     */
    private function request(InputInterface $input): RouteRequest
    {
        /** @var list<string> $arguments */
        $arguments = $input->getArgument('points');
        $points = array_map($this->point(...), $arguments);

        /** @var string $costing */
        $costing = $input->getOption('costing');
        $radius = $input->getOption('radius');

        return new RouteRequest(
            $points,
            $this->integer('alternatives', $input->getOption('alternatives')),
            Costing::tryFrom(strtolower($costing)) ?? throw new InvalidArgumentException(\sprintf('The costing must be one of %s, "%s" given.', implode(', ', array_map(static fn (Costing $costing): string => $costing->value, Costing::cases())), $costing)),
            null === $radius ? $this->radius : $this->integer('radius', $radius),
        );
    }

    /**
     * @throws InvalidArgumentException when the point is not "latitude,longitude"
     */
    private function point(string $point): Coordinates
    {
        if (1 !== preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/D', $point, $matches)) {
            throw new InvalidArgumentException(\sprintf('A point must be "latitude,longitude" in degrees, e.g. "48.1486,17.1077", "%s" given.', $point));
        }

        return new Coordinates((float) $matches[1], (float) $matches[2]);
    }

    /**
     * @throws InvalidArgumentException when the value is not a whole number
     */
    private function integer(string $option, mixed $value): int
    {
        if (!\is_string($value) || 1 !== preg_match('/^-?\d+$/D', $value)) {
            throw new InvalidArgumentException(\sprintf('The option "--%s" must be a whole number, "%s" given.', $option, \is_string($value) ? $value : get_debug_type($value)));
        }

        return (int) $value;
    }

    /**
     * @param list<CountrySegment> $segments
     */
    private function countries(array $segments): string
    {
        if ([] === $segments) {
            return 'unknown (no borders in the routing data)';
        }

        return implode(' → ', array_map(static fn (CountrySegment $segment): string => $segment->country.' '.self::kilometres($segment->distanceMeters), $segments));
    }

    /**
     * @param array<string, int> $distances
     */
    private function perCountry(array $distances): string
    {
        if ([] === $distances) {
            return 'none';
        }

        return implode(', ', array_map(static fn (string $country, int $meters): string => $country.' '.self::kilometres($meters), array_keys($distances), $distances));
    }

    private static function kilometres(int $meters): string
    {
        return \sprintf('%.1f km', $meters / 1000);
    }
}
