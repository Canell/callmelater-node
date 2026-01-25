<?php

namespace Tests\Feature;

use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamMemberApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    // ==================== LIST ====================

    public function test_can_list_team_members(): void
    {
        TeamMember::factory()->count(3)->create([
            'account_id' => $this->user->account_id,
        ]);

        // Create team member in different account (should not be visible)
        $otherUser = User::factory()->create();
        TeamMember::factory()->create([
            'account_id' => $otherUser->account_id,
        ]);

        $response = $this->getJson('/api/v1/team-members');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_search_team_members(): void
    {
        TeamMember::factory()->create([
            'account_id' => $this->user->account_id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);

        TeamMember::factory()->create([
            'account_id' => $this->user->account_id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
        ]);

        // Search by first name
        $response = $this->getJson('/api/v1/team-members?search=John');
        $response->assertOk()->assertJsonCount(1, 'data');

        // Search by last name
        $response = $this->getJson('/api/v1/team-members?search=Smith');
        $response->assertOk()->assertJsonCount(1, 'data');

        // Search by email
        $response = $this->getJson('/api/v1/team-members?search=jane@');
        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_team_members_are_paginated(): void
    {
        TeamMember::factory()->count(30)->create([
            'account_id' => $this->user->account_id,
        ]);

        $response = $this->getJson('/api/v1/team-members?per_page=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data',
                'links',
                'meta',
            ]);
    }

    // ==================== CREATE ====================

    public function test_can_create_team_member_with_email(): void
    {
        $response = $this->postJson('/api/v1/team-members', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'first_name',
                    'last_name',
                    'full_name',
                    'email',
                    'phone',
                    'created_at',
                ],
            ])
            ->assertJsonPath('data.full_name', 'John Doe');

        $this->assertDatabaseHas('team_members', [
            'account_id' => $this->user->account_id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
        ]);
    }

    public function test_can_create_team_member_with_phone(): void
    {
        $response = $this->postJson('/api/v1/team-members', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+15551234567',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.phone', '+15551234567');

        $this->assertDatabaseHas('team_members', [
            'account_id' => $this->user->account_id,
            'phone' => '+15551234567',
        ]);
    }

    public function test_can_create_team_member_with_both_email_and_phone(): void
    {
        $response = $this->postJson('/api/v1/team-members', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '+15551234567',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('team_members', [
            'email' => 'john@example.com',
            'phone' => '+15551234567',
        ]);
    }

    public function test_create_requires_first_name(): void
    {
        $response = $this->postJson('/api/v1/team-members', [
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name']);
    }

    public function test_create_requires_last_name(): void
    {
        $response = $this->postJson('/api/v1/team-members', [
            'first_name' => 'John',
            'email' => 'john@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['last_name']);
    }

    public function test_create_requires_at_least_one_contact_method(): void
    {
        $response = $this->postJson('/api/v1/team-members', [
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_create_validates_email_format(): void
    {
        $response = $this->postJson('/api/v1/team-members', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_create_validates_phone_format(): void
    {
        $response = $this->postJson('/api/v1/team-members', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '123456', // Not E.164 format
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_create_prevents_duplicate_email_in_account(): void
    {
        TeamMember::factory()->create([
            'account_id' => $this->user->account_id,
            'email' => 'existing@example.com',
        ]);

        $response = $this->postJson('/api/v1/team-members', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'existing@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_create_prevents_duplicate_phone_in_account(): void
    {
        TeamMember::factory()->create([
            'account_id' => $this->user->account_id,
            'phone' => '+15551234567',
        ]);

        $response = $this->postJson('/api/v1/team-members', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+15551234567',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_create_allows_same_email_in_different_accounts(): void
    {
        $otherUser = User::factory()->create();
        TeamMember::factory()->create([
            'account_id' => $otherUser->account_id,
            'email' => 'shared@example.com',
        ]);

        $response = $this->postJson('/api/v1/team-members', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'shared@example.com',
        ]);

        $response->assertStatus(201);
    }

    // ==================== SHOW ====================

    public function test_can_show_team_member(): void
    {
        $member = TeamMember::factory()->create([
            'account_id' => $this->user->account_id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response = $this->getJson("/api/v1/team-members/{$member->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $member->id)
            ->assertJsonPath('data.full_name', 'John Doe');
    }

    public function test_cannot_show_team_member_from_other_account(): void
    {
        $otherUser = User::factory()->create();
        $member = TeamMember::factory()->create([
            'account_id' => $otherUser->account_id,
        ]);

        $response = $this->getJson("/api/v1/team-members/{$member->id}");

        $response->assertStatus(404);
    }

    // ==================== UPDATE ====================

    public function test_can_update_team_member(): void
    {
        $member = TeamMember::factory()->create([
            'account_id' => $this->user->account_id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response = $this->putJson("/api/v1/team-members/{$member->id}", [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.full_name', 'Jane Smith');

        $this->assertDatabaseHas('team_members', [
            'id' => $member->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);
    }

    public function test_can_update_partial_fields(): void
    {
        $member = TeamMember::factory()->create([
            'account_id' => $this->user->account_id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);

        $response = $this->putJson("/api/v1/team-members/{$member->id}", [
            'email' => 'john.updated@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.email', 'john.updated@example.com')
            ->assertJsonPath('data.first_name', 'John');
    }

    public function test_cannot_update_to_remove_all_contact_methods(): void
    {
        $member = TeamMember::factory()->create([
            'account_id' => $this->user->account_id,
            'email' => 'john@example.com',
            'phone' => null,
        ]);

        $response = $this->putJson("/api/v1/team-members/{$member->id}", [
            'email' => null,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_cannot_update_team_member_from_other_account(): void
    {
        $otherUser = User::factory()->create();
        $member = TeamMember::factory()->create([
            'account_id' => $otherUser->account_id,
        ]);

        $response = $this->putJson("/api/v1/team-members/{$member->id}", [
            'first_name' => 'Hacker',
        ]);

        $response->assertStatus(404);
    }

    // ==================== DELETE ====================

    public function test_can_delete_team_member(): void
    {
        $member = TeamMember::factory()->create([
            'account_id' => $this->user->account_id,
        ]);

        $response = $this->deleteJson("/api/v1/team-members/{$member->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('team_members', [
            'id' => $member->id,
        ]);
    }

    public function test_cannot_delete_team_member_from_other_account(): void
    {
        $otherUser = User::factory()->create();
        $member = TeamMember::factory()->create([
            'account_id' => $otherUser->account_id,
        ]);

        $response = $this->deleteJson("/api/v1/team-members/{$member->id}");

        $response->assertStatus(404);

        $this->assertDatabaseHas('team_members', [
            'id' => $member->id,
        ]);
    }

    // ==================== AUTH ====================

    public function test_unauthenticated_user_cannot_access_team_members(): void
    {
        // Create a fresh request without authentication
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/team-members');

        $response->assertStatus(401);
    }
}
