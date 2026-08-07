<?php

namespace App\Services\Extraction;

use App\Exceptions\Extraction\ExtractionFailed;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class PageRasterizer
{
    /**
     * Render one PDF page to a PNG and return its path. The caller owns the file
     * and must unlink it.
     */
    public function rasterize(string $pdfPath, int $pageNumber, int $dpi): string
    {
        $directory = sys_get_temp_dir().'/civiclens-raster-'.bin2hex(random_bytes(8));

        if (! mkdir($directory, 0700) && ! is_dir($directory)) {
            throw new ExtractionFailed('Unable to prepare the page image workspace.');
        }

        $prefix = $directory.'/page';

        $process = new Process([
            (string) config('civiclens.extraction.ocr.pdftoppm_binary', 'pdftoppm'),
            '-r', (string) $dpi,
            '-png',
            '-f', (string) $pageNumber,
            '-l', (string) $pageNumber,
            $pdfPath,
            $prefix,
        ]);
        $process->setTimeout((float) config('civiclens.extraction.ocr.timeout_seconds', 120));

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            $this->purge($directory);

            throw new ExtractionFailed('Page rasterization exceeded the configured time limit.');
        }

        if (! $process->isSuccessful()) {
            $this->purge($directory);

            throw new ExtractionFailed('Page rasterization could not read the document.');
        }

        // pdftoppm zero-pads the page suffix by total page count, so the exact
        // filename is not predictable from the page number alone.
        $rendered = glob($prefix.'*.png') ?: [];

        if ($rendered === []) {
            $this->purge($directory);

            throw new ExtractionFailed('Page rasterization produced no image.');
        }

        return $rendered[0];
    }

    public function discard(string $imagePath): void
    {
        @unlink($imagePath);
        @rmdir(dirname($imagePath));
    }

    private function purge(string $directory): void
    {
        foreach (glob($directory.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($directory);
    }
}
