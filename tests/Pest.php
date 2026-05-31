<?php

uses(
    DuskTestCase::class,
    // Illuminate\Foundation\Testing\DatabaseMigrations::class,
)->in('Browser');

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DuskTestCase;
use Tests\TestCase;

uses(TestCase::class)->in('Feature');

uses(RefreshDatabase::class)->in('Feature');
