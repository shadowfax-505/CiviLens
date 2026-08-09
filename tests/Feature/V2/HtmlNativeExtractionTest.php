<?php

use App\Exceptions\Extraction\ExtractionFailed;
use App\Services\Extraction\HtmlNativeExtractor;
use App\Services\Extraction\NativeExtractorRegistry;

function htmlFixture(string $html): string
{
    $path = sys_get_temp_dir().'/civiclens-html-'.bin2hex(random_bytes(6)).'.html';
    file_put_contents($path, $html);

    return $path;
}

it('is the extractor the registry picks for html', function (): void {
    // Registration order decides this. text/html is mapped to 'text' in the
    // native media type registry, so the plain-text extractor also claims it
    // and would win if it were registered first.
    expect(app(NativeExtractorRegistry::class)->for('text/html'))
        ->toBeInstanceOf(HtmlNativeExtractor::class);
});

it('returns the page as text rather than as markup', function (): void {
    $path = htmlFixture('<html><body><p>Tender notice for road repair</p></body></html>');

    $result = app(NativeExtractorRegistry::class)->for('text/html')->extract($path);
    $text = $result->pages[0]->text;

    expect($text)->toBe('Tender notice for road repair')
        ->and($text)->not->toContain('<')
        ->and($result->engine)->toBe('native-html');

    unlink($path);
});

it('keeps a tender table as rows and columns', function (): void {
    // A listing is a table. Flattened to prose, the adjacency that says which
    // reference belongs to which deadline is gone, and no later stage can
    // recover it.
    $path = htmlFixture(<<<'HTML'
        <html><body><table>
        <tr><th>Reference</th><th>Agency</th><th>Deadline</th></tr>
        <tr><td>OTM-2026-114</td><td>Roads and Highways</td><td>2026-09-01</td></tr>
        <tr><td>OTM-2026-115</td><td>Public Works</td><td>2026-09-14</td></tr>
        </table></body></html>
        HTML);

    $lines = explode("\n", app(HtmlNativeExtractor::class)->extract($path)->pages[0]->text);

    expect($lines)->toHaveCount(3)
        ->and($lines[0])->toBe("Reference\tAgency\tDeadline")
        ->and($lines[1])->toBe("OTM-2026-114\tRoads and Highways\t2026-09-01")
        ->and(explode("\t", $lines[2]))->toHaveCount(3);

    unlink($path);
});

it('drops scripts, styles, and site furniture', function (): void {
    $path = htmlFixture(
        '<html><head><style>.a{color:red}</style></head><body>'
        .'<nav>Home Contact</nav><script>var secret = 1;</script>'
        .'<p>Actual notice text</p><footer>Copyright</footer></body></html>'
    );

    $text = app(HtmlNativeExtractor::class)->extract($path)->pages[0]->text;

    expect($text)->toBe('Actual notice text')
        ->and($text)->not->toContain('secret')
        ->and($text)->not->toContain('color:red')
        ->and($text)->not->toContain('Copyright');

    unlink($path);
});

it('does not resolve an entity naming a local file', function (): void {
    // Publisher HTML is untrusted. A declared entity must never be substituted,
    // or a crawled page could read a file off the host and carry it into an
    // artifact.
    $path = htmlFixture(
        '<!DOCTYPE html [<!ENTITY leak SYSTEM "file:///etc/hostname">]>'
        .'<html><body><p>Notice &leak;</p></body></html>'
    );

    $text = app(HtmlNativeExtractor::class)->extract($path)->pages[0]->text;

    expect($text)->toContain('Notice')
        ->and($text)->not->toContain(trim((string) @file_get_contents('/etc/hostname')) ?: 'unreachable-sentinel');

    unlink($path);
});

it('folds a nested table without losing the inner rows', function (): void {
    $path = htmlFixture(
        '<html><body><table><tr><td>'
        .'<table><tr><td>Lot A</td><td>120000</td></tr></table>'
        .'</td></tr></table></body></html>'
    );

    $text = app(HtmlNativeExtractor::class)->extract($path)->pages[0]->text;

    expect($text)->toContain("Lot A\t120000");

    unlink($path);
});

it('recovers text from markup too broken to parse', function (): void {
    // Government pages are frequently invalid. Losing the page is worse than
    // returning imperfect text from it.
    $path = htmlFixture('<p>Unclosed notice <b>bold');

    expect(app(HtmlNativeExtractor::class)->extract($path)->pages[0]->text)
        ->toContain('Unclosed notice');

    unlink($path);
});

it('returns an empty page rather than failing on an empty document', function (): void {
    $path = htmlFixture('   ');

    $page = app(HtmlNativeExtractor::class)->extract($path)->pages[0];

    expect($page->text)->toBe('')
        ->and($page->characterCount())->toBe(0);

    unlink($path);
});

it('fails loudly when the document cannot be read', function (): void {
    expect(fn () => app(HtmlNativeExtractor::class)->extract('/nonexistent/notice.html'))
        ->toThrow(ExtractionFailed::class);
});
