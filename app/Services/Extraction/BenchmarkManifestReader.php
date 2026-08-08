<?php

namespace App\Services\Extraction;

use App\Data\Extraction\BenchmarkPage;
use App\Exceptions\Extraction\ExtractionFailed;

/**
 * Reads a benchmark manifest describing gold-annotated pages.
 *
 * Benchmarks are referenced, never vendored. A manifest names image files
 * relative to its own directory, so a corpus that cannot be redistributed —
 * BaFCo is CC-BY-NC-4.0 — stays outside the repository while its structure
 * remains reproducible from a committed manifest.
 */
class BenchmarkManifestReader
{
    private const ALLOWED_SPLITS = ['calibration', 'test', 'train'];

    private const ALLOWED_SCRIPTS = ['bn', 'en', 'mixed', 'unknown'];

    /** @return list<BenchmarkPage> */
    public function read(string $manifestPath): array
    {
        $realManifest = realpath($manifestPath);

        if ($realManifest === false || ! is_file($realManifest)) {
            throw new ExtractionFailed('Benchmark manifest was not found.');
        }

        $root = dirname($realManifest);
        $decoded = json_decode((string) file_get_contents($realManifest), true);

        if (! is_array($decoded) || ! isset($decoded['pages']) || ! is_array($decoded['pages'])) {
            throw new ExtractionFailed('Benchmark manifest does not declare a pages array.');
        }

        $pages = [];

        foreach ($decoded['pages'] as $index => $page) {
            if (! is_array($page)) {
                throw new ExtractionFailed('Benchmark manifest page '.$index.' is not an object.');
            }

            $pages[] = $this->page($page, $root, $index);
        }

        if ($pages === []) {
            throw new ExtractionFailed('Benchmark manifest declares no pages.');
        }

        return $pages;
    }

    /** @param array<mixed> $page */
    private function page(array $page, string $root, int $index): BenchmarkPage
    {
        $image = $page['image'] ?? null;

        if (! is_string($image) || $image === '') {
            throw new ExtractionFailed('Benchmark manifest page '.$index.' has no image path.');
        }

        $split = $this->oneOf($page['split'] ?? 'calibration', self::ALLOWED_SPLITS, 'split', $index);
        $script = $this->oneOf($page['script_class'] ?? 'unknown', self::ALLOWED_SCRIPTS, 'script_class', $index);
        $publisher = $page['publisher_group'] ?? null;

        if (! is_string($publisher) || trim($publisher) === '') {
            throw new ExtractionFailed('Benchmark manifest page '.$index.' has no publisher group.');
        }

        return new BenchmarkPage(
            $this->resolveImage($image, $root, $index),
            trim($publisher),
            $script,
            $split,
            $this->goldFields($page['fields'] ?? null, $index),
            is_int($page['page_number'] ?? null) ? $page['page_number'] : $index + 1,
        );
    }

    /**
     * Resolve an image path and refuse anything outside the manifest directory.
     *
     * A manifest is data, not instructions. It may be authored by whoever
     * published the benchmark, so a path in it must not be able to reach an
     * arbitrary file on the host.
     */
    private function resolveImage(string $image, string $root, int $index): string
    {
        $resolved = realpath($root.DIRECTORY_SEPARATOR.$image);

        if ($resolved === false || ! is_file($resolved)) {
            throw new ExtractionFailed('Benchmark manifest page '.$index.' references a missing image.');
        }

        if (! str_starts_with($resolved, $root.DIRECTORY_SEPARATOR)) {
            throw new ExtractionFailed('Benchmark manifest page '.$index.' references an image outside the manifest directory.');
        }

        return $resolved;
    }

    /**
     * @return array<string, string>
     */
    private function goldFields(mixed $fields, int $index): array
    {
        if (! is_array($fields) || $fields === []) {
            throw new ExtractionFailed('Benchmark manifest page '.$index.' declares no gold fields.');
        }

        $gold = [];

        foreach ($fields as $key => $value) {
            if (! is_string($key) || $key === '' || ! is_scalar($value)) {
                throw new ExtractionFailed('Benchmark manifest page '.$index.' has an unusable gold field.');
            }

            $gold[$key] = (string) $value;
        }

        return $gold;
    }

    /**
     * @param  list<string>  $allowed
     */
    private function oneOf(mixed $value, array $allowed, string $label, int $index): string
    {
        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            throw new ExtractionFailed('Benchmark manifest page '.$index.' has an unsupported '.$label.'.');
        }

        return $value;
    }
}
