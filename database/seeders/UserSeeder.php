<?php

/**
 * @author      Drasko Gajic PR Pametna Rešenja
 * @copyright   Drasko Gajic PR Pametna Rešenja
 * @license     Proprietary
 */

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::create([
            'name' => 'Serbian Blueberry Farms',
            'address' => 'Čumićevo Sokače, Svileuva',
            'tax_number' => '12345678',
            'latitude' => 44.4838858,
            'longitude' => 19.8188159,
        ]);

        User::create([
            'name' => 'Draško Gajić',
            'email' => 'draskog@gmail.com',
            'password' => Hash::make('Beograd01!'),
            'email_verified_at' => now(),
            'company_id' => $company->id,
        ]);
        User::create([
            'name' => 'Nemanja Kostić',
            'email' => 'gazdanemanja@gmail.com',
            'password' => Hash::make('Beograd01!'),
            'email_verified_at' => now(),
            'company_id' => $company->id,
        ]);
    }
}
