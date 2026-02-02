<?php

namespace Tests\Feature;

use App\Models\ChatConnection;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecipientApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    // ==================== LIST RECIPIENTS ====================

    public function test_can_list_recipients(): void
    {
        // Create team members
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
            'phone' => '+15551234567',
        ]);

        $response = $this->getJson('/api/v1/recipients');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['uri', 'label', 'sublabel', 'type'],
                ],
            ]);

        // Should have 2 team members (Jane has phone only, so only 1 entry for her)
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    public function test_recipients_use_uri_format(): void
    {
        $contact = Contact::factory()->create([
            'account_id' => $this->user->account_id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '+15551234567',
        ]);

        $response = $this->getJson('/api/v1/recipients');

        $response->assertOk();

        $data = $response->json('data');

        // Should have two entries for the contact (email and phone)
        $contactEntries = array_filter($data, fn ($item) => str_contains($item['uri'], $contact->id));
        $this->assertCount(2, $contactEntries);

        // Check URI format uses 'contact:' prefix
        $uris = array_column(array_values($contactEntries), 'uri');
        $this->assertContains("contact:{$contact->id}:email", $uris);
        $this->assertContains("contact:{$contact->id}:phone", $uris);
    }

    public function test_recipients_include_chat_channels(): void
    {
        // Create a Teams channel connection
        ChatConnection::create([
            'account_id' => $this->user->account_id,
            'provider' => ChatConnection::PROVIDER_TEAMS,
            'name' => 'Ops Team',
            'teams_webhook_url' => 'https://outlook.office.com/webhook/test',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/recipients');

        $response->assertOk();

        $data = $response->json('data');

        // Find the channel entry
        $channels = array_filter($data, fn ($item) => $item['type'] === 'channel');
        $this->assertCount(1, $channels);

        $channel = reset($channels);
        $this->assertStringStartsWith('channel:', $channel['uri']);
        $this->assertEquals('Ops Team', $channel['label']);
        $this->assertEquals('Teams', $channel['sublabel']);
    }

    public function test_can_search_recipients(): void
    {
        Contact::factory()->create([
            'account_id' => $this->user->account_id,
            'first_name' => 'UniqueJohn',
            'last_name' => 'Doe',
            'email' => 'uniquejohn@example.com',
        ]);

        Contact::factory()->create([
            'account_id' => $this->user->account_id,
            'first_name' => 'UniqueJane',
            'last_name' => 'Smith',
            'email' => 'uniquejane@example.com',
        ]);

        $response = $this->getJson('/api/v1/recipients');
        $response->assertOk();

        // Search by unique name - should find only contacts with that name
        $data = $response->json('data');
        $johnEntries = array_filter($data, fn ($item) => str_contains($item['label'], 'UniqueJohn'));
        $this->assertCount(1, $johnEntries);

        $janeEntries = array_filter($data, fn ($item) => str_contains($item['label'], 'UniqueJane'));
        $this->assertCount(1, $janeEntries);
    }

    public function test_recipients_only_shows_own_account_data(): void
    {
        // Create contact for current user's account
        Contact::factory()->create([
            'account_id' => $this->user->account_id,
            'first_name' => 'My',
            'last_name' => 'Contact',
            'email' => 'my@example.com',
        ]);

        // Create contact for another account (should not be visible)
        $otherUser = User::factory()->create();
        Contact::factory()->create([
            'account_id' => $otherUser->account_id,
            'first_name' => 'Other',
            'last_name' => 'Contact',
            'email' => 'other@example.com',
        ]);

        $response = $this->getJson('/api/v1/recipients');

        $response->assertOk();

        // Filter to only contacts (not workspace members)
        $contacts = array_filter($response->json('data'), fn ($item) => $item['type'] === 'contact');
        $this->assertCount(1, $contacts);
        $this->assertEquals('My Contact', reset($contacts)['label']);

        // Verify 'Other Contact' is not present
        $otherContacts = array_filter($response->json('data'), fn ($item) => $item['label'] === 'Other Contact');
        $this->assertCount(0, $otherContacts);
    }

    public function test_inactive_channels_not_included(): void
    {
        // Create active channel
        ChatConnection::create([
            'account_id' => $this->user->account_id,
            'provider' => ChatConnection::PROVIDER_SLACK,
            'name' => 'Active Channel',
            'slack_channel_name' => 'active',
            'slack_bot_token' => 'xoxb-test1',
            'is_active' => true,
        ]);

        // Create inactive channel
        ChatConnection::create([
            'account_id' => $this->user->account_id,
            'provider' => ChatConnection::PROVIDER_SLACK,
            'name' => 'Inactive Channel',
            'slack_channel_name' => 'inactive',
            'slack_bot_token' => 'xoxb-test2',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/recipients');

        $response->assertOk();

        $channels = array_filter($response->json('data'), fn ($item) => $item['type'] === 'channel');
        $this->assertCount(1, $channels);
        $this->assertEquals('Active Channel', reset($channels)['label']);
    }

    public function test_unauthenticated_user_cannot_access_recipients(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/recipients');

        $response->assertStatus(401);
    }
}
