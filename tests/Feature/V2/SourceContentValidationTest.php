<?php

use App\Services\Ingestion\SourceContentValidator;

it('accepts a page that carries what was expected', function (): void {
    $result = app(SourceContentValidator::class)->validate(
        '<html><body><h1>CAG Audit Reports</h1><a href="/report-2024.pdf">Download</a></body></html>',
        'text/html',
        'text/html',
        ['audit'],
        true,
    );

    expect($result['usable'])->toBeTrue()
        ->and($result['reasons'])->toBe([])
        ->and($result['document_link_count'])->toBe(1);
});

it('rejects an unfilled template that returns a healthy status', function (): void {
    // The real defect: cag.org.bd audit pages return 200 with 145KB of markup
    // whose body is Lorem ipsum and zero document links. Every status check
    // calls that healthy.
    $result = app(SourceContentValidator::class)->validate(
        '<html><body>'.str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', 40).'</body></html>',
        'text/html',
        'text/html',
        ['audit'],
        true,
    );

    expect($result['usable'])->toBeFalse()
        ->and($result['placeholder_hits'])->toBeGreaterThan(0)
        ->and($result['document_link_count'])->toBe(0)
        ->and($result['reasons'])->toContain('placeholder text present')
        ->and($result['reasons'])->toContain('documents expected but none linked');
});

it('catches an html error page served with a pdf content type', function (): void {
    // A common way for an archive to fill with unreadable files. The body is
    // checked rather than the declared type, because the declared type is what
    // is wrong.
    $result = app(SourceContentValidator::class)->validate(
        '<html><body>404 Not Found</body></html>',
        'application/pdf',
        'application/pdf',
    );

    expect($result['usable'])->toBeFalse()
        ->and($result['reasons'])->toContain('expected a PDF but the body is not one');
});

it('accepts a genuine pdf by its signature', function (): void {
    $result = app(SourceContentValidator::class)->validate(
        "%PDF-1.7\n1 0 obj<</Type/Catalog>>endobj",
        'application/pdf',
        'application/pdf',
    );

    expect($result['usable'])->toBeTrue();
});

it('flags a page that does not mention what it should', function (): void {
    $result = app(SourceContentValidator::class)->validate(
        '<html><body>Welcome to our website</body></html>',
        'text/html',
        'text/html',
        ['audit', 'report'],
    );

    expect($result['usable'])->toBeFalse()
        ->and($result['reasons'][0])->toContain('expected terms absent');
});

it('counts document links across the formats publishers actually use', function (): void {
    $result = app(SourceContentValidator::class)->validate(
        '<a href="a.pdf">1</a><a href="b.PDF?v=2">2</a><a href="c.xlsx">3</a>'
        .'<a href="d.docx">4</a><a href="e.csv">5</a><a href="f.html">skip</a>',
        'text/html',
    );

    expect($result['document_link_count'])->toBe(5);
});

it('rejects an empty body', function (): void {
    $result = app(SourceContentValidator::class)->validate('   ', 'text/html');

    expect($result['usable'])->toBeFalse()
        ->and($result['reasons'])->toContain('empty body');
});

it('reports a media type mismatch for non-pdf expectations', function (): void {
    $result = app(SourceContentValidator::class)->validate(
        '{"rows":[]}',
        'text/html',
        'application/json',
    );

    expect($result['usable'])->toBeFalse()
        ->and($result['reasons'][0])->toContain('expected application/json but received text/html');
});
