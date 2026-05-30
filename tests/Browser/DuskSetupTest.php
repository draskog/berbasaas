<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class DuskSetupTest extends DuskTestCase
{
    public function test_dusk_can_load_homepage(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->assertUrlIs('http://laravel.test/');
        });
    }
}
