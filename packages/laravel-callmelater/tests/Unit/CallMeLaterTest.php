<?php

namespace CallMeLater\Laravel\Tests\Unit;

use CallMeLater\Laravel\CallMeLater;
use CallMeLater\Laravel\Exceptions\ApiException;
use CallMeLater\Laravel\Exceptions\AuthenticationException;
use CallMeLater\Laravel\Exceptions\ConfigurationException;
use CallMeLater\Laravel\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class CallMeLaterTest extends TestCase
{
    protected CallMeLater $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = app(CallMeLater::class);
    }

    public function test_missing_api_token_throws_configuration_exception(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('API token not configured');

        config(['callmelater.api_token' => null]);
        app()->forgetInstance(CallMeLater::class);
        app(CallMeLater::class);
    }

    public function test_get_action_success(): void
    {
        Http::fake([
            'callmelater.test/api/v1/actions/act_123' => Http::response([
                'data' => ['id' => 'act_123', 'name' => 'Test'],
            ]),
        ]);

        $result = $this->client->get('act_123');
        $this->assertEquals('act_123', $result['id']);
    }

    public function test_get_action_throws_api_exception_on_404(): void
    {
        Http::fake([
            'callmelater.test/api/v1/actions/bad_id' => Http::response(
                ['message' => 'Action not found'],
                404
            ),
        ]);

        try {
            $this->client->get('bad_id');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertEquals(404, $e->getStatusCode());
            $this->assertStringContainsString('Action not found', $e->getMessage());
        }
    }

    public function test_send_action_throws_authentication_exception_on_401(): void
    {
        Http::fake([
            'callmelater.test/api/v1/actions' => Http::response(
                ['message' => 'Unauthenticated.'],
                401
            ),
        ]);

        $this->expectException(AuthenticationException::class);

        $this->client->sendAction(['mode' => 'immediate']);
    }

    public function test_send_action_captures_validation_errors(): void
    {
        Http::fake([
            'callmelater.test/api/v1/actions' => Http::response([
                'message' => 'The selected mode is invalid.',
                'errors' => [
                    'mode' => ['The selected mode is invalid.'],
                    'execute_at' => ['Either execute_at or intent must be provided.'],
                ],
            ], 422),
        ]);

        try {
            $this->client->sendAction(['mode' => 'bad_mode']);
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertEquals(422, $e->getStatusCode());
            $this->assertArrayHasKey('mode', $e->getValidationErrors());
            $this->assertArrayHasKey('execute_at', $e->getErrorBag());
            $this->assertStringContainsString('The selected mode is invalid', $e->getMessage());
        }
    }

    public function test_cancel_action_success(): void
    {
        Http::fake([
            'callmelater.test/api/v1/actions/act_789' => Http::response(
                ['message' => 'Action cancelled'],
                200
            ),
        ]);

        $result = $this->client->cancel('act_789');
        $this->assertEquals('Action cancelled', $result['message']);
    }

    public function test_list_actions_success(): void
    {
        Http::fake([
            'callmelater.test/api/v1/actions*' => Http::response([
                'data' => [['id' => 'act_1'], ['id' => 'act_2']],
                'meta' => ['total' => 2],
            ]),
        ]);

        $result = $this->client->list(['status' => 'resolved']);
        $this->assertCount(2, $result['data']);
    }

    public function test_timezone_and_retry_config_accessors(): void
    {
        $this->assertEquals('America/New_York', $this->client->getTimezone());
        $this->assertEquals([
            'max_attempts' => 3,
            'backoff' => 'exponential',
            'initial_delay' => 60,
        ], $this->client->getRetryConfig());
    }
}
