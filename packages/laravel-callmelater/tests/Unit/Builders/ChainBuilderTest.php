<?php

namespace CallMeLater\Laravel\Tests\Unit\Builders;

use CallMeLater\Laravel\CallMeLater;
use CallMeLater\Laravel\Exceptions\ApiException;
use CallMeLater\Laravel\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class ChainBuilderTest extends TestCase
{
    protected CallMeLater $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = app(CallMeLater::class);
    }

    public function test_basic_chain_payload(): void
    {
        $payload = $this->client->chain('My Chain')
            ->addHttpStep('Step 1')
                ->url('https://example.com/api')
                ->post()
                ->done()
            ->toArray();

        $this->assertEquals('My Chain', $payload['name']);
        $this->assertCount(1, $payload['steps']);
        $this->assertEquals('http_call', $payload['steps'][0]['type']);
        $this->assertEquals('Step 1', $payload['steps'][0]['name']);
    }

    public function test_chain_with_input(): void
    {
        $payload = $this->client->chain('Order Flow')
            ->input(['order_id' => 456, 'amount' => 2999])
            ->addHttpStep('Charge')->url('https://stripe.com/charge')->post()->done()
            ->toArray();

        $this->assertEquals(['order_id' => 456, 'amount' => 2999], $payload['input']);
    }

    public function test_chain_error_handling(): void
    {
        $payload = $this->client->chain('My Chain')
            ->errorHandling('fail_chain')
            ->addHttpStep('S1')->url('https://ex.com')->post()->done()
            ->toArray();

        $this->assertEquals('fail_chain', $payload['error_handling']);
    }

    public function test_http_step_full_config(): void
    {
        $payload = $this->client->chain('Test')
            ->addHttpStep('Call API')
                ->url('https://api.example.com/endpoint')
                ->put()
                ->headers(['Authorization' => 'Bearer token'])
                ->body(['key' => 'value'])
                ->maxAttempts(5)
                ->retryStrategy('exponential')
                ->condition('{{steps.0.status}} == 200')
                ->done()
            ->toArray();

        $step = $payload['steps'][0];
        $this->assertEquals('http_call', $step['type']);
        $this->assertEquals('Call API', $step['name']);
        $this->assertEquals('https://api.example.com/endpoint', $step['url']);
        $this->assertEquals('PUT', $step['method']);
        $this->assertEquals(['Authorization' => 'Bearer token'], $step['headers']);
        $this->assertEquals(['key' => 'value'], $step['body']);
        $this->assertEquals(5, $step['max_attempts']);
        $this->assertEquals('exponential', $step['retry_strategy']);
        $this->assertEquals('{{steps.0.status}} == 200', $step['condition']);
    }

    public function test_http_step_method_shortcuts(): void
    {
        $chain = $this->client->chain('Test');

        $getStep = $chain->addHttpStep('Get')->url('https://ex.com')->get()->toArray();
        $this->assertEquals('GET', $getStep['method']);

        $postStep = $chain->addHttpStep('Post')->url('https://ex.com')->post()->toArray();
        $this->assertEquals('POST', $postStep['method']);

        $putStep = $chain->addHttpStep('Put')->url('https://ex.com')->put()->toArray();
        $this->assertEquals('PUT', $putStep['method']);

        $patchStep = $chain->addHttpStep('Patch')->url('https://ex.com')->patch()->toArray();
        $this->assertEquals('PATCH', $patchStep['method']);

        $deleteStep = $chain->addHttpStep('Delete')->url('https://ex.com')->delete()->toArray();
        $this->assertEquals('DELETE', $deleteStep['method']);
    }

    public function test_gate_step_full_config(): void
    {
        $payload = $this->client->chain('Approval Flow')
            ->addGateStep('Get Approval')
                ->message('Please approve this request')
                ->to('approver@example.com')
                ->toMany(['backup1@example.com', 'backup2@example.com'])
                ->maxSnoozes(3)
                ->requireAll()
                ->timeout('2d')
                ->onTimeout('cancel')
                ->condition('{{steps.0.status}} == 200')
                ->done()
            ->toArray();

        $step = $payload['steps'][0];
        $this->assertEquals('gated', $step['type']);
        $this->assertEquals('Get Approval', $step['name']);
        $this->assertEquals('Please approve this request', $step['gate']['message']);
        $this->assertEquals([
            'email:approver@example.com',
            'email:backup1@example.com',
            'email:backup2@example.com',
        ], $step['gate']['recipients']);
        $this->assertEquals(3, $step['gate']['max_snoozes']);
        $this->assertEquals('all_required', $step['gate']['confirmation_mode']);
        $this->assertEquals('2d', $step['gate']['timeout']);
        $this->assertEquals('cancel', $step['gate']['on_timeout']);
        $this->assertEquals('{{steps.0.status}} == 200', $step['condition']);
    }

    public function test_gate_step_first_response(): void
    {
        $payload = $this->client->chain('Test')
            ->addGateStep('Quick Approve')
                ->to('user@example.com')
                ->firstResponse()
                ->done()
            ->toArray();

        $this->assertEquals('first_response', $payload['steps'][0]['gate']['confirmation_mode']);
    }

    public function test_gate_step_raw_recipient(): void
    {
        $payload = $this->client->chain('Test')
            ->addGateStep('Gate')
                ->toRecipient('phone:+15551234567')
                ->done()
            ->toArray();

        $this->assertEquals(['phone:+15551234567'], $payload['steps'][0]['gate']['recipients']);
    }

    public function test_delay_step_minutes(): void
    {
        $payload = $this->client->chain('Test')
            ->addDelayStep('Wait a bit')
                ->minutes(30)
                ->done()
            ->toArray();

        $step = $payload['steps'][0];
        $this->assertEquals('delay', $step['type']);
        $this->assertEquals('Wait a bit', $step['name']);
        $this->assertEquals('30m', $step['delay']);
    }

    public function test_delay_step_hours(): void
    {
        $payload = $this->client->chain('Test')
            ->addDelayStep('Wait an hour')
                ->hours(1)
                ->done()
            ->toArray();

        $this->assertEquals('1h', $payload['steps'][0]['delay']);
    }

    public function test_delay_step_days(): void
    {
        $payload = $this->client->chain('Test')
            ->addDelayStep('Wait a day')
                ->days(2)
                ->done()
            ->toArray();

        $this->assertEquals('2d', $payload['steps'][0]['delay']);
    }

    public function test_delay_step_raw_duration(): void
    {
        $payload = $this->client->chain('Test')
            ->addDelayStep('Custom Wait')
                ->duration('4h30m')
                ->done()
            ->toArray();

        $this->assertEquals('4h30m', $payload['steps'][0]['delay']);
    }

    public function test_delay_step_with_condition(): void
    {
        $payload = $this->client->chain('Test')
            ->addDelayStep('Conditional Wait')
                ->hours(1)
                ->condition('{{steps.0.response.needs_wait}} == true')
                ->done()
            ->toArray();

        $this->assertEquals('{{steps.0.response.needs_wait}} == true', $payload['steps'][0]['condition']);
    }

    public function test_multi_step_chain_ordering(): void
    {
        $payload = $this->client->chain('Full Workflow')
            ->input(['order_id' => 123])
            ->addHttpStep('Step 1')
                ->url('https://api.example.com/charge')
                ->post()
                ->body(['amount' => 1000])
                ->done()
            ->addGateStep('Step 2')
                ->message('Approve shipment?')
                ->to('warehouse@example.com')
                ->timeout('24h')
                ->onTimeout('cancel')
                ->done()
            ->addDelayStep('Step 3')
                ->hours(1)
                ->done()
            ->addHttpStep('Step 4')
                ->url('https://api.example.com/ship')
                ->post()
                ->body(['order_id' => '{{input.order_id}}'])
                ->condition("{{steps.1.response.action}} == confirmed")
                ->done()
            ->errorHandling('fail_chain')
            ->toArray();

        $this->assertCount(4, $payload['steps']);
        $this->assertEquals('http_call', $payload['steps'][0]['type']);
        $this->assertEquals('Step 1', $payload['steps'][0]['name']);
        $this->assertEquals('gated', $payload['steps'][1]['type']);
        $this->assertEquals('Step 2', $payload['steps'][1]['name']);
        $this->assertEquals('delay', $payload['steps'][2]['type']);
        $this->assertEquals('Step 3', $payload['steps'][2]['name']);
        $this->assertEquals('http_call', $payload['steps'][3]['type']);
        $this->assertEquals('Step 4', $payload['steps'][3]['name']);
        $this->assertEquals("{{steps.1.response.action}} == confirmed", $payload['steps'][3]['condition']);
    }

    public function test_step_add_alias_works(): void
    {
        $payload = $this->client->chain('Test')
            ->addHttpStep('Step 1')
                ->url('https://ex.com')
                ->post()
                ->add()
            ->addDelayStep('Wait')
                ->hours(1)
                ->add()
            ->addGateStep('Approve')
                ->to('user@ex.com')
                ->add()
            ->toArray();

        $this->assertCount(3, $payload['steps']);
    }

    public function test_empty_chain_has_no_steps(): void
    {
        $payload = $this->client->chain('Empty')->toArray();

        $this->assertEquals('Empty', $payload['name']);
        $this->assertEmpty($payload['steps']);
        $this->assertArrayNotHasKey('input', $payload);
        $this->assertArrayNotHasKey('error_handling', $payload);
    }

    public function test_send_calls_api(): void
    {
        Http::fake([
            'callmelater.test/api/v1/chains' => Http::response([
                'data' => ['id' => 'chn_123', 'name' => 'Test Chain', 'status' => 'running'],
            ], 201),
        ]);

        $result = $this->client->chain('Test Chain')
            ->addHttpStep('Step 1')
                ->url('https://example.com')
                ->post()
                ->done()
            ->send();

        $this->assertEquals('chn_123', $result['id']);
        Http::assertSentCount(1);
    }

    public function test_get_chain(): void
    {
        Http::fake([
            'callmelater.test/api/v1/chains/chn_123' => Http::response([
                'data' => ['id' => 'chn_123', 'name' => 'My Chain', 'status' => 'running'],
            ], 200),
        ]);

        $result = $this->client->getChain('chn_123');

        $this->assertEquals('chn_123', $result['id']);
        $this->assertEquals('running', $result['status']);
    }

    public function test_list_chains(): void
    {
        Http::fake([
            'callmelater.test/api/v1/chains*' => Http::response([
                'data' => [
                    ['id' => 'chn_1', 'status' => 'running'],
                    ['id' => 'chn_2', 'status' => 'completed'],
                ],
                'meta' => ['total' => 2],
            ], 200),
        ]);

        $result = $this->client->listChains(['status' => 'running']);

        $this->assertCount(2, $result['data']);
    }

    public function test_cancel_chain(): void
    {
        Http::fake([
            'callmelater.test/api/v1/chains/chn_123/cancel' => Http::response([
                'message' => 'Chain cancelled',
            ], 200),
        ]);

        $result = $this->client->cancelChain('chn_123');

        $this->assertEquals('Chain cancelled', $result['message']);
    }

    public function test_send_chain_throws_on_api_error(): void
    {
        Http::fake([
            'callmelater.test/api/v1/chains' => Http::response([
                'message' => 'Validation failed',
                'errors' => ['steps' => ['At least one step is required']],
            ], 422),
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Failed to create chain');

        $this->client->chain('Bad Chain')->send();
    }
}
