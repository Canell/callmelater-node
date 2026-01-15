<?php

namespace Tests\Feature;

use App\Models\AdminNotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminNotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_admin_notifications(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user/admin-notifications');

        $response->assertStatus(403)
            ->assertJson(['message' => 'Admin access required']);
    }

    public function test_admin_can_get_admin_notifications(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/user/admin-notifications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'health_alerts',
                'incident_alerts',
                'channels',
            ]);
    }

    public function test_get_admin_notifications_creates_default_if_not_exists(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $this->assertDatabaseMissing('admin_notification_preferences', [
            'user_id' => $admin->id,
        ]);

        $response = $this->getJson('/api/user/admin-notifications');

        $response->assertStatus(200);

        $this->assertDatabaseHas('admin_notification_preferences', [
            'user_id' => $admin->id,
            'health_alerts' => true,
            'incident_alerts' => true,
        ]);
    }

    public function test_get_admin_notifications_returns_existing_preferences(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        AdminNotificationPreference::create([
            'user_id' => $admin->id,
            'health_alerts' => false,
            'incident_alerts' => true,
            'channels' => ['email', 'sms'],
        ]);

        $response = $this->getJson('/api/user/admin-notifications');

        $response->assertStatus(200)
            ->assertJson([
                'health_alerts' => false,
                'incident_alerts' => true,
                'channels' => ['email', 'sms'],
            ]);
    }

    public function test_non_admin_cannot_update_admin_notifications(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/user/admin-notifications', [
            'health_alerts' => true,
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_update_admin_notifications(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/user/admin-notifications', [
            'health_alerts' => false,
            'incident_alerts' => true,
            'channels' => ['email'],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Admin notification preferences updated',
                'health_alerts' => false,
                'incident_alerts' => true,
                'channels' => ['email'],
            ]);

        $this->assertDatabaseHas('admin_notification_preferences', [
            'user_id' => $admin->id,
            'health_alerts' => false,
            'incident_alerts' => true,
        ]);
    }

    public function test_update_creates_preferences_if_not_exists(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $this->assertDatabaseMissing('admin_notification_preferences', [
            'user_id' => $admin->id,
        ]);

        $response = $this->putJson('/api/user/admin-notifications', [
            'health_alerts' => true,
            'incident_alerts' => false,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('admin_notification_preferences', [
            'user_id' => $admin->id,
            'health_alerts' => true,
            'incident_alerts' => false,
        ]);
    }

    public function test_update_validates_channel_values(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/user/admin-notifications', [
            'channels' => ['email', 'invalid_channel'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['channels.1']);
    }

    public function test_update_allows_partial_updates(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        AdminNotificationPreference::create([
            'user_id' => $admin->id,
            'health_alerts' => true,
            'incident_alerts' => true,
            'channels' => ['email'],
        ]);

        // Only update health_alerts
        $response = $this->putJson('/api/user/admin-notifications', [
            'health_alerts' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'health_alerts' => false,
            ]);
    }

    public function test_unauthenticated_user_cannot_access_admin_notifications(): void
    {
        $response = $this->getJson('/api/user/admin-notifications');

        $response->assertStatus(401);
    }
}
