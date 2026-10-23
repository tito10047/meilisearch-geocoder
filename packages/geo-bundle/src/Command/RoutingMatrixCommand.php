<?php

namespace Tito10047\GeoBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tito10047\GeoBundle\Attribution;
use Tito10047\Routing\Coordinates;
use Tito10047\Routing\Costing;
use Tito10047\Routing\Exception\InvalidArgumentException;
use Tito10047\Routing\Exception\RoutingUnavailableException;
use Tito10047\Routing\MatrixInterface;
use Tito10047\Routing\MatrixRequest;

/**
 * Prints the distance and duration from every source to every target.
 */
#[AsCommand(name: 'tito10047-geo:routing:matrix', description: 'Prints the distance and duration from every source to every target', help: <<<'TXT'
The <info>%command.name%</info> command asks Valhalla for a distance matrix, one row per source:

  <info>php %command.full_name% --source=48.1486,17.1077 --source=48.3774,17.5872 --target=49.2231,18.7394 --target=48.3069,18.0864</info>
TXT)]
final class RoutingMatrixCommand extends Command
{
    /**
     * @param int $radius the configured default of the metres a point may snap to a road
     */
    public function __construct(
        private readonly MatrixInterface $matrix,
        private readonly int $radius,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('source', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Where the routes start, "latitude,longitude"; repeat for more')
            ->addOption('target', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Where the routes end, "latitude,longitude"; repeat for more')
            ->addOption('costing', null, InputOption::VALUE_REQUIRED, 'How the routes are travelled: '.RoutingInput::costings(), Costing::Car->value)
            ->addOption('radius', null, InputOption::VALUE_REQUIRED, 'Metres a point may snap to a road (the configured default otherwise)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $request = $this->request($input);
            $matrix = $this->matrix->matrix($request);
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        } catch (RoutingUnavailableException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $rows = [];
        foreach ($request->sources as $source => $point) {
            $row = [self::point($point)];
            foreach (array_keys($request->targets) as $target) {
                $distance = $matrix->distanceMeters($source, $target);
                $duration = $matrix->durationSeconds($source, $target);
                $row[] = null === $distance || null === $duration
                    ? 'no route'
                    : \sprintf('%s, %d min', RoutingInput::kilometres($distance), (int) round($duration / 60));
            }
            $rows[] = $row;
        }
        $io->table(['From \ to', ...array_map(self::point(...), $request->targets)], $rows);
        $io->text(Attribution::OPENSTREETMAP);

        return Command::SUCCESS;
    }

    /**
     * @throws InvalidArgumentException when an option is invalid
     */
    private function request(InputInterface $input): MatrixRequest
    {
        /** @var list<string> $sources */
        $sources = $input->getOption('source');
        /** @var list<string> $targets */
        $targets = $input->getOption('target');
        $radius = $input->getOption('radius');

        return new MatrixRequest(
            array_map(RoutingInput::point(...), $sources),
            array_map(RoutingInput::point(...), $targets),
            RoutingInput::costing($input->getOption('costing')),
            null === $radius ? $this->radius : RoutingInput::integer('radius', $radius),
        );
    }

    private static function point(Coordinates $point): string
    {
        return $point->latitude.','.$point->longitude;
    }
}
