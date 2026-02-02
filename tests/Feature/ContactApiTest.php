<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContactApiTest extends TestCase
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

    public function test_can_list_contacts(): void
    {
        Contact::factory()->count(3)->create([
            'account_id' => $this->user->account_id,
        ]);

        // Create contact in different account (should not be visible)
        $otherUser = User::factory()->create();
        Contact::factory()->create([
            'account_id' => $otherUser->account_id,
        ]);

        $response = $this->getJson('/api/v1/contacts');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_search_contacts(): void
    {
        Contact::factory()->create([
            'account_id' => $this->user->account_id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);

        Contact::factory()->create([
            'account_id' => $this->user->account_id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
        ]);

        // Search by first name
        $response = $this->getJson('/api/v1/contacts?search=John');
        $response->assertOk()->assertJsonCount(1, 'data');

        // Search by last name
        $response = $this->getJson('/api/v1/contacts?search=Smith');
        $response->assertOk()->assertJsonCount(1, 'data');

        // Search by email
        $response = $this->getJson('/api/v1/contacts?search=jane@');
        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_contacts_are_paginated(): void
    {
        Contact::factory()->count(30)->create([
            'account_id' => $this->user->account_id,
        ]);

        $response = $this->getJson('/api/v1/contacts?per_page=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data',
                'links',
                'meta',
            ]);
    }

    // ==================== CREATE ====================

    public function test_can_create_contact_with_email(): void
    {
        $response = $this->postJson('/api/v1/contacts', [
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

        $this->assertDatabaseHas('contacts', [
            'account_id' => $this->user->account_id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
        ]);
    }

    public function test_can_create_contact_with_phone(): void
    {
        $response = $this->postJson('/api/v1/contacts', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+15551234567',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.phone', '+15551234567');

        $this->assertDatabaseHas('contacts', [
            'account_id' => $this->user->account_id,
            'phone' => '+15551234567',
        ]);
    }

    public function test_can_create_contact_with_both_email_and_phone(): void
    {
        $response = $this->postJson('/api/v1/contacts', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '+15551234567',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('contacts', [
            'email' => 'john@example.com',
            'phone' => '+15551234567',
        ]);
    }

    public function test_create_requires_first_name(): void
    {
        $response = $this->postJson('/api/v1/contacts', [
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name']);
    }

    public function test_create_requires_last_name(): void
    {
        $response = $this->postJson('/api/v1/contacts', [
            'first_name' => 'John',
            'email' => 'john@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['last_name']);
    }

    public function test_create_requires_at_least_one_contact_method(): void
    {
        $response = $this->postJson('/api/v1/contacts', [
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_create_validates_email_format(): void
    {
        $response = $this->postJson('/api/v1/contacts', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_create_validates_phone_format(): void
    {
        $response = $this->postJson('/api/v1/contacts', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '123456', // Not E.164 format
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_create_prevents_duplicate_email_in_account(): void
    {
        Contact::factory()->create([
            'account_id' => $this->user->account_id,
            'email' => 'existing@example.com',
        ]);

        $response = $this->postJson('/api/v1/contacts', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'existing@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_create_prevents_duplicate_phone_in_account(): void
    {
        Contact::factory()->create([
            'account_id' => $this->user->account_id,
            'phone' => '+15551234567',
        ]);

        $response = $this->postJson('/api/v1/contacts', [
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
        Contact::factory()->create([
            'account_id' => $otherUser->account_id,
            'email' => 'shared@example.com',
        ]);

        $response = $this->postJson('/api/v1/contacts', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'shared@example.com',
        ]);

        $response->assertStatus(201);
    }

    // ==================== SHOW ====================

    public function test_can_show_contact(): void
    {
        $contact = Contact::factory()->create([
            'account_id' => $this->user->account_id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response = $this->getJson("/api/v1/contacts/{$contact->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $contact->id)
            ->assertJsonPath('data.full_name', 'John Doe');
    }

    public function test_cannot_show_contact_from_other_account(): void
    {
        $otherUser = User::factory()->create();
        $contact = Contact::factory()->create([
            'account_id' => $otherUser->account_id,
        ]);

        $response = $this->getJson("/api/v1/contacts/{$contact->id}");

        $response->assertStatus(404);
    }

    // ==================== UPDATE ====================

    public function test_can_update_contact(): void
    {
        $contact = Contact::factory()->create([
            'account_id' => $this->user->account_id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response = $this->putJson("/api/v1/contacts/{$contact->id}", [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.full_name', 'Jane Smith');

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);
    }

    public function test_can_update_partial_fields(): void
    {
        $contact = Contact::factory()->create([
            'account_id' => $this->user->account_id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);

        $response = $this->putJson("/api/v1/contacts/{$contact->id}", [
            'email' => 'john.updated@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.email', 'john.updated@example.com')
            ->assertJsonPath('data.first_name', 'John');
    }

    public function test_cannot_update_to_remove_all_contact_methods(): void
    {
        $contact = Contact::factory()->create([
            'account_id' => $this->user->account_id,
            'email' => 'john@example.com',
            'phone' => null,
        ]);

        $response = $this->putJson("/api/v1/contacts/{$contact->id}", [
            'email' => null,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_cannot_update_contact_from_other_account(): void
    {
        $otherUser = User::factory()->create();
        $contact = Contact::factory()->create([
            'account_id' => $otherUser->account_id,
        ]);

        $response = $this->putJson("/api/v1/contacts/{$contact->id}", [
            'first_name' => 'Hacker',
        ]);

        $response->assertStatus(404);
    }

    // ==================== DELETE ====================

    public function test_can_delete_contact(): void
    {
        $contact = Contact::factory()->create([
            'account_id' => $this->user->account_id,
        ]);

        $response = $this->deleteJson("/api/v1/contacts/{$contact->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('contacts', [
            'id' => $contact->id,
        ]);
    }

    public function test_cannot_delete_contact_from_other_account(): void
    {
        $otherUser = User::factory()->create();
        $contact = Contact::factory()->create([
            'account_id' => $otherUser->account_id,
        ]);

        $response = $this->deleteJson("/api/v1/contacts/{$contact->id}");

        $response->assertStatus(404);

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
        ]);
    }

    // ==================== AUTH ====================

    public function test_unauthenticated_user_cannot_access_contacts(): void
    {
        // Create a fresh request without authentication
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/contacts');

        $response->assertStatus(401);
    }
}
