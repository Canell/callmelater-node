<?php

namespace Database\Seeders;

use App\Models\SystemComponent;
use Illuminate\Database\Seeder;

class SystemComponentsSeeder extends Seeder
{
    public function run(): void
    {
        $components = [
            [
                'name' => 'Webhook Delivery',
                'slug' => 'webhook-delivery',
                'description' => 'HTTP webhook execution and delivery',
                'current_status' => SystemComponent::STATUS_OPERATIONAL,
                'display_order' => 1,
            ],
            [
                'name' => 'Scheduler',
                'slug' => 'scheduler',
                'description' => 'Action scheduling and dispatch',
                'current_status' => SystemComponent::STATUS_OPERATIONAL,
                'display_order' => 2,
            ],
            [
                'name' => 'API',
                'slug' => 'api',
                'description' => 'REST API endpoints',
                'current_status' => SystemComponent::STATUS_OPERATIONAL,
                'display_order' => 3,
            ],
            [
                'name' => 'Email Notifications',
                'slug' => 'email-notifications',
                'description' => 'Email delivery for reminders',
                'current_status' => SystemComponent::STATUS_OPERATIONAL,
                'display_order' => 4,
            ],
            [
                'name' => 'SMS Notifications',
                'slug' => 'sms-notifications',
                'description' => 'SMS delivery for reminders',
                'current_status' => SystemComponent::STATUS_OPERATIONAL,
                'display_order' => 5,
            ],
        ];

        foreach ($components as $component) {
            SystemComponent::firstOrCreate(
                ['slug' => $component['slug']],
                $component
            );
        }

        $this->command->info('System components seeded: '.count($components));
    }
}
