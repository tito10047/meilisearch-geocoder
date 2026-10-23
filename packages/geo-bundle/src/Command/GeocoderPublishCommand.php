<?php

namespace Tito10047\GeoBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tito10047\GeoBundle\Attribution;
use Tito10047\GeoBundle\Command\Output\BuildPrinter;
use Tito10047\Geocoder\Exception\ExceptionInterface;
use Tito10047\Geocoder\Import\Build;
use Tito10047\Geocoder\Import\Exception\BuildNotFoundException;
use Tito10047\Geocoder\Import\ImporterInterface;

/**
 * Publishes a build imported with --no-publish: its index and tables become the live ones.
 */
#[AsCommand(name: 'geo:publish', description: 'Makes a build of the geocoder live (its index and its tables)', help: <<<'TXT'
The <info>%command.name%</info> command swaps the index of a build in under the live uid and renames its
tables to the live names, at once; the index and the tables it replaces are removed, and so are
older builds:

  <info>php %command.full_name% geo_20261023120000</info>

When a publication fails or is interrupted, run the same command again: it finishes the
publication from wherever it stopped (the index and the tables always end up of one build).
TXT)]
final class GeocoderPublishCommand extends Command
{
    public function __construct(private readonly ImporterInterface $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('build', InputArgument::REQUIRED, 'The uid of the build, e.g. "geo_20261023120000" (printed by the import)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $uid */
        $uid = $input->getArgument('build');

        try {
            $build = $this->importer->publish($this->build($uid));
        } catch (ExceptionInterface $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        (new BuildPrinter())->print($io, $build);
        $io->text(Attribution::OPENSTREETMAP);
        $io->success(\sprintf('The build %s is live.', $build->indexUid));

        return Command::SUCCESS;
    }

    /**
     * The build, also when a former run published its tables already and stopped before the end:
     * publishing it from the live tables finishes the publication.
     *
     * @throws ExceptionInterface
     */
    private function build(string $uid): Build
    {
        try {
            return $this->importer->build($uid);
        } catch (BuildNotFoundException $e) {
            try {
                $live = $this->importer->live();
            } catch (ExceptionInterface) {
                throw $e;
            }
            if ($live->indexUid !== $uid) {
                throw $e;
            }

            return $live;
        }
    }
}
