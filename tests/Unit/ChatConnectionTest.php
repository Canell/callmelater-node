<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\ChatConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatConnectionTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->account = $user->account;
    }

    public function test_can_create_teams_connection(): void
    {
        $connection = ChatConnection::create([
            'account_id' => $this->account->id,
            'provider' => 'teams',
            'name' => 'DevOps Channel',
            'teams_webhook_url' => 'https://outlook.office.com/webhook/test',
            'is_active' => true,
        ]);

        $this->assertNotNull($connection->id);
        $this->assertEquals('teams', $connection->provider);
        $this->assertEquals('DevOps Channel', $connection->name);
        $this->assertTrue($connection->is_active);
    }

    public function test_can_create_slack_connection(): void
    {
        $connection = ChatConnection::create([
            'account_id' => $this->account->id,
            'provider' => 'slack',
            'name' => 'Engineering Team',
            'slack_bot_token' => 'xoxb-test-token',
            'slack_signing_secret' => 'secret123',
            'is_active' => true,
        ]);

        $this->assertNotNull($connection->id);
        $this->assertEquals('slack', $connection->provider);
    }

    public function test_is_teams_helper(): void
    {
        $teams = ChatConnection::create([
            'account_id' => $this->account->id,
            'provider' => 'teams',
            'name' => 'Teams',
        ]);

        $slack = ChatConnection::create([
            'account_id' => $this->account->id,
            'provider' => 'slack',
            'name' => 'Slack',
        ]);

        $this->assertTrue($teams->isTeams());
        $this->assertFalse($teams->isSlack());
        $this->assertTrue($slack->isSlack());
        $this->assertFalse($slack->isTeams());
    }

    public function test_teams_scope(): void
    {
        ChatConnection::create([
            'account_id' => $this->account->id,
            'provider' => 'teams',
            'name' => 'Teams 1',
        ]);

        ChatConnection::create([
            'account_id' => $this->account->id,
            'provider' => 'slack',
            'name' => 'Slack 1',
        ]);

        $teams = ChatConnection::teams()->get();
        $this->assertCount(1, $teams);
        $this->assertEquals('teams', $teams->first()->provider);
    }

    public function test_slack_scope(): void
    {
        ChatConnection::create([
            'account_id' => $this->account->id,
            'provider' => 'teams',
            'name' => 'Teams 1',
        ]);

        ChatConnection::create([
            'account_id' => $this->account->id,
            'provider' => 'slack',
            'name' => 'Slack 1',
        ]);

        $slack = ChatConnection::slack()->get();
        $this->assertCount(1, $slack);
        $this->assertEquals('slack', $slack->first()->provider);
    }

    public function test_active_scope(): void
    {
        ChatConnection::create([
            'account_id' => $this->account->id,
            'provider' => 'teams',
            'name' => 'Active',
            'is_active' => true,
        ]);

        ChatConnection::create([
            'account_id' => $this->account->id,
            'provider' => 'teams',
            'name' => 'Inactive',
            'is_active' => false,
        ]);

        $active = ChatConnection::active()->get();
        $this->assertCount(1, $active);
        $this->assertTrue($active->first()->is_active);
    }

    public function test_belongs_to_account(): void
    {
        $connection = ChatConnection::create([
            'account_id' => $this->account->id,
            'provider' => 'teams',
            'name' => 'Test',
        ]);

        $this->assertInstanceOf(Account::class, $connection->account);
        $this->assertEquals($this->account->id, $connection->account->id);
    }

    public function test_uses_uuid_as_primary_key(): void
    {
        $connection = ChatConnection::create([
            'account_id' => $this->account->id,
            'provider' => 'teams',
            'name' => 'Test',
        ]);

        // UUID should be a 36 character string
        $this->assertIsString($connection->id);
        $this->assertEquals(36, strlen($connection->id));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $connection->id);
    }
}
