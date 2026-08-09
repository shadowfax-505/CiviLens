<?php

use Database\Factories\CountryFactory;
use Tests\TestCase;

pest()->extend(TestCase::class)
    // The factory remembers which country codes it has handed out, because a
    // batch create builds every model before saving any and a check against the
    // database alone would issue one code repeatedly. That memory has to be
    // dropped when the database is refreshed, or the usable space shrinks with
    // every test until it runs out.
    ->beforeEach(fn () => CountryFactory::forgetIssuedCodes())
    ->in('Feature', 'Unit');

require_once __DIR__.'/Support/helpers.php';
