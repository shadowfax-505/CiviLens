<?php

declare(strict_types=1);

$target = $argv[1] ?? 'app';
$threshold = (int) ($_SERVER['CIVICLENS_COMPLEXITY_THRESHOLD'] ?? 10);
$files = [];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $files[] = $file->getPathname();
    }
}

sort($files);

$results = [];
foreach ($files as $file) {
    $tokens = token_get_all((string) file_get_contents($file));
    $current = null;
    $braceDepth = 0;
    $functionDepth = null;

    foreach ($tokens as $index => $token) {
        $text = is_array($token) ? $token[1] : $token;
        $id = is_array($token) ? $token[0] : null;

        if ($id === T_FUNCTION) {
            $name = 'anonymous';
            for ($i = $index + 1; $i < count($tokens); $i++) {
                if (is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) {
                    $name = $tokens[$i][1];
                    break;
                }
                if ($tokens[$i] === '(') {
                    break;
                }
            }
            $current = ['file' => $file, 'name' => $name, 'complexity' => 1];
            $functionDepth = null;
        }

        if ($text === '{') {
            $braceDepth++;
            if ($current !== null && $functionDepth === null) {
                $functionDepth = $braceDepth;
            }
        }

        if ($current !== null && in_array($id, [T_IF, T_ELSEIF, T_FOR, T_FOREACH, T_WHILE, T_CASE, T_CATCH, T_COALESCE], true)) {
            $current['complexity']++;
        }

        if ($current !== null && in_array($text, ['&&', '||', '?'], true)) {
            $current['complexity']++;
        }

        if ($text === '}') {
            if ($current !== null && $functionDepth === $braceDepth) {
                $results[] = $current;
                $current = null;
                $functionDepth = null;
            }
            $braceDepth--;
        }
    }
}

$hotspots = array_values(array_filter($results, fn (array $result): bool => $result['complexity'] > $threshold));
usort($hotspots, fn (array $a, array $b): int => $b['complexity'] <=> $a['complexity']);

echo "CivicLens complexity report\n";
echo "Threshold: {$threshold}\n";
echo 'Functions scanned: '.count($results).PHP_EOL;
echo 'Hotspots: '.count($hotspots).PHP_EOL;

foreach (array_slice($hotspots, 0, 25) as $hotspot) {
    echo sprintf(
        "%s:%s complexity=%d\n",
        $hotspot['file'],
        $hotspot['name'],
        $hotspot['complexity'],
    );
}

exit(0);
