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
#[AsCommand(name: 'geo:route', description: 'Prints the routes through points: distance, duration, countries, tolls', help: <<<'TXT'
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
            ->addOption('costing', null, InputOption::VALUE_REQUIRED, 'How the route is travelled: '.RoutingInput::costings(), Costing::Car->value)
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
                ['Distance' => RoutingInput::kilometres($route->distanceMeters)],
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
        $points = array_map(RoutingInput::point(...), $arguments);

        $radius = $input->getOption('radius');

        return new RouteRequest(
            $points,
            RoutingInput::integer('alternatives', $input->getOption('alternatives')),
            RoutingInput::costing($input->getOption('costing')),
            null === $radius ? $this->radius : RoutingInput::integer('radius', $radius),
        );
    }

    /**
     * @param list<CountrySegment> $segments
     */
    private function countries(array $segments): string
    {
        if ([] === $segments) {
            return 'unknown (no borders in the routing data)';
        }

        return implode(' → ', array_map(static fn (CountrySegment $segment): string => $segment->country.' '.RoutingInput::kilometres($segment->distanceMeters), $segments));
    }

    /**
     * @param array<string, int> $distances
     */
    private function perCountry(array $distances): string
    {
        if ([] === $distances) {
            return 'none';
        }

        return implode(', ', array_map(static fn (string $country, int $meters): string => $country.' '.RoutingInput::kilometres($meters), array_keys($distances), $distances));
    }
}
