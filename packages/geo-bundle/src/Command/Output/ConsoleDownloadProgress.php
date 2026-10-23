<?php

namespace Tito10047\GeoBundle\Command\Output;

use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Tito10047\Geocoder\Import\Download\DownloadProgressInterface;

/**
 * Draws the progress of a download in megabytes: a bar when the size is known, a counter
 * otherwise. An output without ANSI codes (a log, a cron mail) gets nothing until the result.
 *
 * @internal
 */
final class ConsoleDownloadProgress implements DownloadProgressInterface
{
    private ?ProgressBar $bar = null;

    public function __construct(private readonly OutputInterface $output)
    {
    }

    public function advance(string $region, int $downloadedBytes, ?int $totalBytes): void
    {
        if (!$this->output->isDecorated()) {
            return;
        }
        if (!$this->bar instanceof ProgressBar) {
            $bar = new ProgressBar($this->output, $totalBytes ?? 0);
            $bar->setFormat(null === $totalBytes
                ? ' %region%: %downloaded% [%elapsed:6s%]'
                : ' %region%: %downloaded% of %total% [%bar%] %percent:3s%% [%elapsed:6s%/%estimated:-6s%]');
            $bar->setMessage($region, 'region');
            $bar->setMessage(Helper::formatMemory($totalBytes ?? 0), 'total');
            $bar->setMessage(Helper::formatMemory(0), 'downloaded');
            $bar->start();
            $this->bar = $bar;
        }

        $this->bar->setMessage(Helper::formatMemory($downloadedBytes), 'downloaded');
        $this->bar->setProgress($downloadedBytes);
    }

    /**
     * Ends the bar of the download; called when it ended, successfully or not.
     */
    public function finish(): void
    {
        if ($this->bar instanceof ProgressBar) {
            $this->bar->finish();
            $this->output->writeln('');
        }
        $this->bar = null;
    }
}
