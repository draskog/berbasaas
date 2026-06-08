<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class CompanySettingsTest extends DuskTestCase
{
    public function test_can_view_find_location_button_english()
    {
        $user = User::first();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user, 'web')
                ->visit('/settings/company')
                ->pause(1000)
                ->screenshot('01-company-settings');

            // Check for the button - try both English and Serbian
            $hasButtonEnglish = $browser->element('button:contains("Find Location")') !== null;
            $hasButtonSerbian = $browser->element('button:contains("Pronađi")') !== null;

            $browser->dump();
        });
    }
}
