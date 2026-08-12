<?php

namespace Database\Seeders;

use App\Domains\CitizenAccess\Services\CitizenAccessCatalogueService;
use Illuminate\Database\Seeder;

class CitizenAccessCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        app(CitizenAccessCatalogueService::class)->seed();
    }
}
