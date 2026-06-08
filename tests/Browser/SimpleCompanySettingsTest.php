<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class SimpleCompanySettingsTest extends DuskTestCase
{
    public function test_company_settings_page_loads()
    {
        $user = User::first();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user, 'web')
                ->visit('/settings/company')
                ->pause(2000)
                ->screenshot('simple-company-settings');
        });
    }
}
