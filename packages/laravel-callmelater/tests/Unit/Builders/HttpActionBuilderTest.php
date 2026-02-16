<?php

namespace CallMeLater\Laravel\Tests\Unit\Builders;

use CallMeLater\Laravel\CallMeLater;
use CallMeLater\Laravel\Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class HttpActionBuilderTest extends TestCase
{
    protected CallMeLater $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = app(CallMeLater::class);
    }

    public function test_base_payload_structure(): void
    {
        $payload = $this->client->http('https://example.com/api')
            ->post()
            ->inHours(1)
            ->toArray();

        $this->assertEquals('immediate', $payload['mode']);
        $this->assertEquals('https://example.com/api', $payload['request']['url']);
        $this->assertEquals('POST', $payload['request']['method']);
    }

    public function test_method_shortcuts(): void
    {
        $this->assertEquals('GET', $this->client->http('https://ex.com')->get()->toArray()['request']['method']);
        $this->assertEquals('PUT', $this->client->http('https://ex.com')->put()->toArray()['request']['method']);
        $this->assertEquals('PATCH', $this->client->http('https://ex.com')->patch()->toArray()['request']['method']);
        $this->assertEquals('DELETE', $this->client->http('https://ex.com')->delete()->toArray()['request']['method']);
    }

    public function test_at_with_datetime_object(): void
    {
        $time = Carbon::parse('2026-03-01 10:00:00');
        $payload = $this->client->http('https://ex.com')->at($time)->toArray();

        $this->assertEquals('2026-03-01 10:00:00', $payload['intent']['at']);
        $this->assertEquals('America/New_York', $payload['intent']['timezone']);
    }

    public function test_at_with_preset_string(): void
    {
        $payload = $this->client->http('https://ex.com')->at('tomorrow')->toArray();

        $this->assertEquals('tomorrow', $payload['intent']['preset']);
    }

    public function test_at_with_datetime_string(): void
    {
        $payload = $this->client->http('https://ex.com')->at('2026-06-15 09:00:00')->toArray();

        $this->assertEquals('2026-06-15 09:00:00', $payload['intent']['at']);
    }

    public function test_relative_delay(): void
    {
        $payload = $this->client->http('https://ex.com')->inHours(3)->toArray();
        $this->assertEquals('3h', $payload['intent']['delay']);

        $payload = $this->client->http('https://ex.com')->inMinutes(30)->toArray();
        $this->assertEquals('30m', $payload['intent']['delay']);

        $payload = $this->client->http('https://ex.com')->inDays(7)->toArray();
        $this->assertEquals('7d', $payload['intent']['delay']);
    }

    public function test_retry_config(): void
    {
        $payload = $this->client->http('https://ex.com')
            ->retry(5, 'linear', 30)
            ->inHours(1)
            ->toArray();

        $this->assertEquals(5, $payload['max_attempts']);
        $this->assertEquals('linear', $payload['retry_strategy']);
    }

    public function test_no_retry(): void
    {
        $payload = $this->client->http('https://ex.com')
            ->noRetry()
            ->inHours(1)
            ->toArray();

        $this->assertEquals(1, $payload['max_attempts']);
        $this->assertArrayNotHasKey('retry_strategy', $payload);
    }

    public function test_default_retry_from_config(): void
    {
        $payload = $this->client->http('https://ex.com')->inHours(1)->toArray();

        $this->assertEquals(3, $payload['max_attempts']);
        $this->assertEquals('exponential', $payload['retry_strategy']);
    }

    public function test_headers_and_payload(): void
    {
        $payload = $this->client->http('https://ex.com')
            ->headers(['Authorization' => 'Bearer abc'])
            ->header('X-Custom', 'value')
            ->payload(['key' => 'val'])
            ->inHours(1)
            ->toArray();

        $this->assertEquals(['Authorization' => 'Bearer abc', 'X-Custom' => 'value'], $payload['request']['headers']);
        $this->assertEquals(['key' => 'val'], $payload['request']['body']);
    }

    public function test_name_and_metadata(): void
    {
        $payload = $this->client->http('https://ex.com')
            ->name('My Action')
            ->metadata(['env' => 'prod'])
            ->meta('version', '2')
            ->inHours(1)
            ->toArray();

        $this->assertEquals('My Action', $payload['name']);
        $this->assertEquals(['env' => 'prod', 'version' => '2'], $payload['metadata']);
    }

    public function test_callback_url(): void
    {
        $payload = $this->client->http('https://ex.com')
            ->callback('https://myapp.com/cb')
            ->inHours(1)
            ->toArray();

        $this->assertEquals('https://myapp.com/cb', $payload['callback_url']);
    }

    public function test_idempotency_key(): void
    {
        $payload = $this->client->http('https://ex.com')
            ->idempotencyKey('unique-key-123')
            ->inHours(1)
            ->toArray();

        $this->assertEquals('unique-key-123', $payload['idempotency_key']);
    }

    public function test_send_calls_api(): void
    {
        Http::fake([
            'callmelater.test/api/v1/actions' => Http::response([
                'data' => ['id' => 'act_123', 'name' => 'Test'],
            ], 200),
        ]);

        $result = $this->client->http('https://ex.com')
            ->post()
            ->inHours(1)
            ->send();

        $this->assertEquals('act_123', $result['id']);
        Http::assertSentCount(1);
    }
}
