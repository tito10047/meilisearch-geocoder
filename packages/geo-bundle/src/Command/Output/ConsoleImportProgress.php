<?php

namespace Tito10047\GeoBundle\Command\Output;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Tito10047\Geocoder\Import\ImportCounts;
use Tito10047\Geocoder\Import\ImportProgressInterface;

/**
 * Draws the progress of an import: one progress bar per dump, with what it produced so far.
 *
 * The size of a dump in objects is not known before it is read: the bars count the documents
 * and house numbers written, without a maximum. An output without ANSI codes (a log, a cron
 * mail) gets one line per dump once it is read instead.
 *
 * @internal
 */
final class ConsoleImportProgress implements ImportProgressInterface
{
    private const COUNTS = '%places% places, %streets% streets, %pois% points of interest, %houses% house numbers';

    private ?ProgressBar $bar = null;

    private ?string $dump = null;

    private ImportCounts $counts;

    public function __construct(private readonly OutputInterface $output)
    {
        $this->counts = new ImportCounts();
    }

    public function advance(string $dump, ImportCounts $counts): void
    {
        if ($dump !== $this->dump) {
            $this->finish();
            $this->dump = $dump;
            if ($this->output->isDecorated()) {
                $bar = new ProgressBar($this->output);
                $bar->setFormat(' %dump%: '.self::COUNTS.' [%elapsed:6s%, %memory:6s%]');
                $bar->setMessage($dump, 'dump');
                $this->setCounts($bar, new ImportCounts());
                $bar->start();
                $this->bar = $bar;
            }
        }

        $this->counts = $counts;
        $bar = $this->bar;
        if ($bar instanceof ProgressBar) {
            $this->setCounts($bar, $counts);
            $bar->setProgress(max($bar->getProgress(), $counts->documents() + $counts->houseNumbers));
        }
    }

    /**
     * Ends the progress of the last dump; called when the import ended, successfully or not.
     */
    public function finish(): void
    {
        if ($this->bar instanceof ProgressBar) {
            $this->bar->finish();
            $this->output->writeln('');
        } elseif (null !== $this->dump) {
            $this->output->writeln(' '.$this->dump.': '.strtr(self::COUNTS, $this->messages($this->counts)));
        }
        $this->bar = null;
        $this->dump = null;
        $this->counts = new ImportCounts();
    }

    private function setCounts(ProgressBar $bar, ImportCounts $counts): void
    {
        foreach ($this->messages($counts) as $placeholder => $message) {
            $bar->setMessage($message, trim($placeholder, '%'));
        }
    }

    /**
     * @return array<string, string> placeholder => formatted count
     */
    private function messages(ImportCounts $counts): array
    {
        return [
            '%places%' => Numbers::format($counts->places),
            '%streets%' => Numbers::format($counts->streets),
            '%pois%' => Numbers::format($counts->pois),
            '%houses%' => Numbers::format($counts->houseNumbers),
        ];
    }
}
