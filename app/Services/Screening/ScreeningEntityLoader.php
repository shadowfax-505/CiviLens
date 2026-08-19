<?php

namespace App\Services\Screening;

use App\Models\ScreeningEntity;
use App\Models\SourceArtifactVersion;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Turn an acquired screening list into rows that can be queried.
 *
 * The list arrives as an archived artifact like any other document, with a hash
 * and a provenance record. Parsing it into entities is a separate step so the
 * archived copy stays exactly as the publisher served it: a screening decision
 * made last month has to be explainable against the list as it stood, not
 * against whatever the publisher has since changed.
 */
class ScreeningEntityLoader
{
    public function __construct(private readonly EntityNameNormaliser $normaliser) {}

    /**
     * @return array{dataset: string, rows: int, loaded: int, skipped: int}
     */
    public function loadCsv(SourceArtifactVersion $artifact, string $dataset): array
    {
        $disk = Storage::disk((string) config('civiclens.ingestion.artifact_disk', 'local'));
        $path = $disk->path((string) $artifact->storage_path);

        if (! is_readable($path)) {
            throw new RuntimeException('The screening artifact is not readable.');
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException('The screening artifact could not be opened.');
        }

        $rows = 0;
        $loaded = 0;
        $skipped = 0;

        try {
            $header = fgetcsv($handle, 0, ',', '"', '\\');

            if (! is_array($header)) {
                throw new RuntimeException('The screening artifact has no header row.');
            }

            $columns = array_flip(array_map(fn (mixed $name): string => (string) $name, $header));

            while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                $rows++;
                $name = trim($row[$columns['name'] ?? -1] ?? '');

                if ($name === '') {
                    $skipped++;

                    continue;
                }

                $aliases = array_values(array_filter(array_map(
                    'trim',
                    explode(';', $row[$columns['aliases'] ?? -1] ?? ''),
                )));

                ScreeningEntity::query()->updateOrCreate(
                    [
                        'dataset' => $dataset,
                        'external_id' => $row[$columns['id'] ?? -1] ?? $name,
                    ],
                    [
                        'source_artifact_version_id' => $artifact->getKey(),
                        'entity_type' => $row[$columns['schema'] ?? -1] ?? 'LegalEntity',
                        'name' => mb_substr($name, 0, 512),
                        'normalized_name' => mb_substr($this->normaliser->normalise($name), 0, 512),
                        'aliases' => $aliases,
                        'countries' => mb_substr($row[$columns['countries'] ?? -1] ?? '', 0, 255),
                        'topics' => mb_substr($row[$columns['topics'] ?? -1] ?? '', 0, 255),
                    ],
                );

                $loaded++;
            }
        } finally {
            fclose($handle);
        }

        return ['dataset' => $dataset, 'rows' => $rows, 'loaded' => $loaded, 'skipped' => $skipped];
    }
}
