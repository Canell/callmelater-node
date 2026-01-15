<?php

namespace Tests\Unit;

use App\Models\AdminNotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_admin_notification_preference(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $pref = AdminNotificationPreference::create([
            'user_id' => $user->id,
            'health_alerts' => true,
            'incident_alerts' => false,
            'channels' => ['email'],
        ]);

        $this->assertDatabaseHas('admin_notification_preferences', [
            'user_id' => $user->id,
            'health_alerts' => true,
            'incident_alerts' => false,
        ]);

        $this->assertEquals(['email'], $pref->channels);
    }

    public function test_channels_is_cast_to_array(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $pref = AdminNotificationPreference::create([
            'user_id' => $user->id,
            'health_alerts' => true,
            'incident_alerts' => true,
            'channels' => ['email', 'sms'],
        ]);

        $pref->refresh();

        $this->assertIsArray($pref->channels);
        $this->assertContains('email', $pref->channels);
        $this->assertContains('sms', $pref->channels);
    }

    public function test_health_alert_recipients_returns_opted_in_admins(): void
    {
        // Create admins with health alerts enabled
        $admin1 = User::factory()->create(['is_admin' => true, 'email' => 'admin1@example.com']);
        $admin2 = User::factory()->create(['is_admin' => true, 'email' => 'admin2@example.com']);
        $admin3 = User::factory()->create(['is_admin' => true, 'email' => 'admin3@example.com']);

        AdminNotificationPreference::create([
            'user_id' => $admin1->id,
            'health_alerts' => true,
            'incident_alerts' => false,
            'channels' => ['email'],
        ]);

        AdminNotificationPreference::create([
            'user_id' => $admin2->id,
            'health_alerts' => false,
            'incident_alerts' => true,
            'channels' => ['email'],
        ]);

        AdminNotificationPreference::create([
            'user_id' => $admin3->id,
            'health_alerts' => true,
            'incident_alerts' => true,
            'channels' => ['email'],
        ]);

        $recipients = AdminNotificationPreference::getHealthAlertRecipients();

        $this->assertCount(2, $recipients);
        $this->assertContains('admin1@example.com', $recipients);
        $this->assertContains('admin3@example.com', $recipients);
        $this->assertNotContains('admin2@example.com', $recipients);
    }

    public function test_incident_alert_recipients_returns_opted_in_admins(): void
    {
        $admin1 = User::factory()->create(['is_admin' => true, 'email' => 'admin1@example.com']);
        $admin2 = User::factory()->create(['is_admin' => true, 'email' => 'admin2@example.com']);

        AdminNotificationPreference::create([
            'user_id' => $admin1->id,
            'health_alerts' => true,
            'incident_alerts' => false,
            'channels' => ['email'],
        ]);

        AdminNotificationPreference::create([
            'user_id' => $admin2->id,
            'health_alerts' => false,
            'incident_alerts' => true,
            'channels' => ['email'],
        ]);

        $recipients = AdminNotificationPreference::getIncidentAlertRecipients();

        $this->assertCount(1, $recipients);
        $this->assertContains('admin2@example.com', $recipients);
        $this->assertNotContains('admin1@example.com', $recipients);
    }

    public function test_returns_empty_array_when_no_admins_opted_in(): void
    {
        // Create admin without preferences
        User::factory()->create(['is_admin' => true]);

        $healthRecipients = AdminNotificationPreference::getHealthAlertRecipients();
        $incidentRecipients = AdminNotificationPreference::getIncidentAlertRecipients();

        $this->assertIsArray($healthRecipients);
        $this->assertEmpty($healthRecipients);
        $this->assertIsArray($incidentRecipients);
        $this->assertEmpty($incidentRecipients);
    }

    public function test_preference_belongs_to_user(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $pref = AdminNotificationPreference::create([
            'user_id' => $user->id,
            'health_alerts' => true,
            'incident_alerts' => true,
            'channels' => ['email'],
        ]);

        $this->assertInstanceOf(User::class, $pref->user);
        $this->assertEquals($user->id, $pref->user->id);
    }

    public function test_boolean_fields_are_cast_correctly(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $pref = AdminNotificationPreference::create([
            'user_id' => $user->id,
            'health_alerts' => 1,
            'incident_alerts' => 0,
            'channels' => ['email'],
        ]);

        $pref->refresh();

        $this->assertIsBool($pref->health_alerts);
        $this->assertIsBool($pref->incident_alerts);
        $this->assertTrue($pref->health_alerts);
        $this->assertFalse($pref->incident_alerts);
    }
}
