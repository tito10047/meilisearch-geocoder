<?php

namespace Tito10047\GeoBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tito10047\GeoBundle\Attribution;
use Tito10047\GeoBundle\Routing\RoutingStatusProviderInterface;
use Tito10047\Routing\Exception\RoutingUnavailableException;

/**
 * Tells whether Valhalla is reachable, and what it runs.
 */
#[AsCommand(name: 'tito10047-geo:routing:status', description: 'Tells whether Valhalla is reachable, and what it runs')]
final class RoutingStatusCommand extends Command
{
    public function __construct(
        private readonly RoutingStatusProviderInterface $statusProvider,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $status = $this->statusProvider->status();
        } catch (RoutingUnavailableException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->definitionList(
            ['Valhalla' => $status->version],
            ['Routing data built' => $status->tilesetModified?->format('Y-m-d H:i:s T') ?? 'unknown'],
            ['Services' => [] === $status->availableActions ? 'unknown' : implode(', ', $status->availableActions)],
        );
        $io->success('Valhalla is reachable.');
        $io->text(Attribution::OPENSTREETMAP);

        return Command::SUCCESS;
    }
}
