<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * There is no `users` table — the app authenticates against the migrated
     * member_hq / members tables (see project-plan.md decision 9). Only static
     * lookup data is seeded here; transactional data arrives through the
     * import command in plan item 2.13.
     */
    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);
    }
}
