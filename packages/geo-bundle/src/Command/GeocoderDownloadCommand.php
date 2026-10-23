<?php

namespace Tito10047\GeoBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tito10047\GeoBundle\Attribution;
use Tito10047\GeoBundle\Command\Output\ConsoleDownloadProgress;
use Tito10047\Geocoder\Exception\ExceptionInterface;
use Tito10047\Geocoder\Import\Download\DumpDownloaderInterface;

/**
 * Downloads the Photon dumps of the configured regions into the directory of the dumps.
 */
#[AsCommand(name: 'tito10047-geo:geocoder:download', description: 'Downloads the Photon dumps of the geocoder, again only when they changed', help: <<<'TXT'
The <info>%command.name%</info> command downloads the Photon dump of each region into the directory the
import command reads (tito10047_geo.geocoder.import.dumps_dir), e.g. "europe/slovakia" into
slovakia.jsonl.zst:

  <info>php %command.full_name%</info>                    the regions of tito10047_geo.geocoder.import.regions
  <info>php %command.full_name% europe/slovakia</info>    one region

A dump that has not changed since the last download (ETag, Last-Modified) is not downloaded
again; <comment>--force</comment> downloads it anyway. A dump replaces the previous copy only once it is
complete and checked, so an interrupted download leaves the old one in place. Then import:

  <info>php bin/console tito10047-geo:geocoder:import</info>
TXT)]
final class GeocoderDownloadCommand extends Command
{
    /**
     * @param list<string> $regions        the regions downloaded when none is given
     * @param string|null  $dumpsDirectory where the dumps go
     */
    public function __construct(
        private readonly DumpDownloaderInterface $downloader,
        private readonly array $regions,
        private readonly ?string $dumpsDirectory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('regions', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Regions of the download server, e.g. "europe/slovakia"; the configured regions by default')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Download even the dumps that have not changed');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var list<string> $regions */
        $regions = $input->getArgument('regions');
        $regions = [] === $regions ? $this->regions : $regions;
        if ([] === $regions) {
            $io->error('Give the regions to download (e.g. "europe/slovakia"), or configure them in "tito10047_geo.geocoder.import.regions".');

            return Command::INVALID;
        }
        if (null === $this->dumpsDirectory) {
            $io->error('Configure where the dumps go: "tito10047_geo.geocoder.import.dumps_dir".');

            return Command::INVALID;
        }

        $failed = 0;
        foreach ($regions as $region) {
            $progress = new ConsoleDownloadProgress($output);
            try {
                $result = $this->downloader->download($region, $this->dumpsDirectory, (bool) $input->getOption('force'), $progress);
            } catch (ExceptionInterface $e) {
                $progress->finish();
                $io->error($e->getMessage());
                ++$failed;
                continue;
            }
            $progress->finish();

            $io->text(\sprintf(' %s: %s %s (%s%s)', $region, $result->downloaded ? 'downloaded' : 'unchanged,', $result->path,
                Helper::formatMemory($result->bytes), null === $result->lastModified ? '' : ', changed '.$result->lastModified->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i').' UTC'));
        }

        if ($failed > 0) {
            $io->error(\sprintf('%d of %d downloads failed.', $failed, \count($regions)));

            return Command::FAILURE;
        }
        $io->text(Attribution::OPENSTREETMAP);
        $io->success(\sprintf('The dumps are in %s; import them with tito10047-geo:geocoder:import.', $this->dumpsDirectory));

        return Command::SUCCESS;
    }
}
