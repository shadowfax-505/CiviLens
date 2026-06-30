<?php

use App\Contracts\Search\Searchable;
use App\Contracts\Search\SearchProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates normalized search platform tables and provider contracts', function (): void {
    expect(interface_exists(Searchable::class))->toBeTrue()
        ->and(interface_exists(SearchProvider::class))->toBeTrue();

    foreach ([
        'search_indexes',
        'search_documents',
        'search_keywords',
        'search_synonyms',
        'search_popularity',
        'search_clicks',
        'saved_searches',
        'search_history',
        'search_jobs',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
});
