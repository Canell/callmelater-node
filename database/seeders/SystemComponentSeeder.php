<?php

namespace Database\Seeders;

use App\Services\StatusService;
use Illuminate\Database\Seeder;

class SystemComponentSeeder extends Seeder
{
    public function run(): void
    {
        $service = new StatusService();
        $service->seedDefaultComponents();
    }
}
