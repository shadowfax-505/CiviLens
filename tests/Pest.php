<?php

use Tests\TestCase;

pest()->extend(TestCase::class)
    ->in('Feature', 'Unit');

require_once __DIR__.'/Support/helpers.php';
