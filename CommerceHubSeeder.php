<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CommerceHubSeeder extends Seeder
{
    public function run(): void
    {
        Company::query()->firstOrCreate(
            ['name' => 'Demo Company'],
            ['email' => 'demo@example.com']
        );
    }
}
