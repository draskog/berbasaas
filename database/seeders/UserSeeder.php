<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
            'name' => 'Demo Company',
            'address' => '123 Harvest Lane',
            'tax_number' => '12345678',
        ]);

        User::create([
            'name' => 'Drasko Gajic',
            'email' => 'draskog@gmail.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'company_id' => $company->id,
        ]);
    }
}
