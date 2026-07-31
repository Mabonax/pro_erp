<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ProvincesSeeder::class,
            StaffDepartmentsSeeder::class,
            AccessControlSeeder::class,
            ProgrammeOfActionPlatformSeeder::class,
            HumanCapitalRegistrySeeder::class,
            IntelligenceSeeder::class,
            AdjudicationSectionsSeeder::class,
            SuperAdminUserSeeder::class,
            OrganizationEventsSeeder::class,
            CitizenAccessSeeder::class,
        ]);

        if (app()->environment(['local', 'development'])) {
            $this->call([
                LocalDevelopmentUsersSeeder::class,
            ]);
        }

        if (app()->environment(['local', 'development', 'testing'])) {
            $this->call([
                DefaultTestUserSeeder::class,
            ]);
        }
    }
}
