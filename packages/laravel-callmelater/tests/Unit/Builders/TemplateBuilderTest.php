<?php

namespace CallMeLater\Laravel\Tests\Unit\Builders;

use CallMeLater\Laravel\CallMeLater;
use CallMeLater\Laravel\Exceptions\ApiException;
use CallMeLater\Laravel\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class TemplateBuilderTest extends TestCase
{
    protected CallMeLater $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = app(CallMeLater::class);
    }

    public function test_basic_template_payload(): void
    {
        $payload = $this->client->template('Invoice Reminder')
            ->description('Sends reminder to approve an invoice')
            ->mode('gated')
            ->toArray();

        $this->assertEquals('Invoice Reminder', $payload['name']);
        $this->assertEquals('Sends reminder to approve an invoice', $payload['description']);
        $this->assertEquals('gated', $payload['mode']);
    }

    public function test_template_with_type(): void
    {
        $payload = $this->client->template('HTTP Template')
            ->type('http')
            ->mode('immediate')
            ->toArray();

        $this->assertEquals('http', $payload['type']);
        $this->assertEquals('immediate', $payload['mode']);
    }

    public function test_template_with_request_config(): void
    {
        $payload = $this->client->template('API Call')
            ->mode('immediate')
            ->requestConfig([
                'url' => 'https://api.example.com/process',
                'method' => 'POST',
                'body' => ['msg' => '{{message}}'],
            ])
            ->toArray();

        $this->assertEquals('https://api.example.com/process', $payload['request_config']['url']);
        $this->assertEquals('POST', $payload['request_config']['method']);
        $this->assertEquals(['msg' => '{{message}}'], $payload['request_config']['body']);
    }

    public function test_template_with_gate_config(): void
    {
        $payload = $this->client->template('Approval Template')
            ->mode('gated')
            ->gateConfig([
                'message' => 'Please approve invoice #{{invoice_id}}',
                'recipients' => ['email:{{approver_email}}'],
            ])
            ->toArray();

        $this->assertEquals('Please approve invoice #{{invoice_id}}', $payload['gate_config']['message']);
        $this->assertEquals(['email:{{approver_email}}'], $payload['gate_config']['recipients']);
    }

    public function test_template_with_placeholders(): void
    {
        $payload = $this->client->template('Test')
            ->placeholder('invoice_id', required: true, description: 'The invoice number')
            ->placeholder('approver_email', required: true)
            ->placeholder('priority', required: false, default: 'normal')
            ->toArray();

        $this->assertCount(3, $payload['placeholders']);

        $this->assertEquals('invoice_id', $payload['placeholders'][0]['name']);
        $this->assertTrue($payload['placeholders'][0]['required']);
        $this->assertEquals('The invoice number', $payload['placeholders'][0]['description']);

        $this->assertEquals('approver_email', $payload['placeholders'][1]['name']);
        $this->assertTrue($payload['placeholders'][1]['required']);
        $this->assertArrayNotHasKey('description', $payload['placeholders'][1]);

        $this->assertEquals('priority', $payload['placeholders'][2]['name']);
        $this->assertFalse($payload['placeholders'][2]['required']);
        $this->assertEquals('normal', $payload['placeholders'][2]['default']);
    }

    public function test_template_placeholders_bulk(): void
    {
        $placeholders = [
            ['name' => 'user_id', 'required' => true],
            ['name' => 'message', 'required' => false, 'default' => 'Hello'],
        ];

        $payload = $this->client->template('Test')
            ->placeholders($placeholders)
            ->toArray();

        $this->assertEquals($placeholders, $payload['placeholders']);
    }

    public function test_template_with_retry_config(): void
    {
        $payload = $this->client->template('Retryable')
            ->maxAttempts(5)
            ->retryStrategy('exponential')
            ->toArray();

        $this->assertEquals(5, $payload['max_attempts']);
        $this->assertEquals('exponential', $payload['retry_strategy']);
    }

    public function test_template_with_timezone(): void
    {
        $payload = $this->client->template('Timed')
            ->timezone('Europe/Paris')
            ->toArray();

        $this->assertEquals('Europe/Paris', $payload['timezone']);
    }

    public function test_chain_template(): void
    {
        $steps = [
            ['type' => 'http_call', 'name' => 'Step 1', 'url' => 'https://ex.com', 'method' => 'POST'],
            ['type' => 'delay', 'name' => 'Wait', 'delay' => '1h'],
        ];

        $payload = $this->client->template('Chain Template')
            ->type('chain')
            ->chainSteps($steps)
            ->chainErrorHandling('fail_chain')
            ->toArray();

        $this->assertEquals($steps, $payload['chain_steps']);
        $this->assertEquals('fail_chain', $payload['chain_error_handling']);
    }

    public function test_template_with_coordination(): void
    {
        $payload = $this->client->template('Coordinated')
            ->coordinationKeys(['user_id', 'region'])
            ->coordinationConfig(['strategy' => 'debounce', 'window' => '5m'])
            ->toArray();

        $this->assertEquals(['user_id', 'region'], $payload['default_coordination_keys']);
        $this->assertEquals(['strategy' => 'debounce', 'window' => '5m'], $payload['coordination_config']);
    }

    public function test_minimal_template(): void
    {
        $payload = $this->client->template('Minimal')->toArray();

        $this->assertEquals('Minimal', $payload['name']);
        $this->assertArrayNotHasKey('description', $payload);
        $this->assertArrayNotHasKey('type', $payload);
        $this->assertArrayNotHasKey('mode', $payload);
        $this->assertArrayNotHasKey('request_config', $payload);
        $this->assertArrayNotHasKey('gate_config', $payload);
        $this->assertArrayNotHasKey('placeholders', $payload);
        $this->assertArrayNotHasKey('chain_steps', $payload);
    }

    public function test_send_creates_template(): void
    {
        Http::fake([
            'callmelater.test/api/v1/templates' => Http::response([
                'data' => [
                    'id' => 'tpl_123',
                    'name' => 'Test Template',
                    'trigger_token' => 'clmt_abc123',
                ],
            ], 201),
        ]);

        $result = $this->client->template('Test Template')
            ->mode('immediate')
            ->requestConfig(['url' => 'https://ex.com', 'method' => 'POST'])
            ->send();

        $this->assertEquals('tpl_123', $result['id']);
        $this->assertEquals('clmt_abc123', $result['trigger_token']);
        Http::assertSentCount(1);
    }

    public function test_create_alias(): void
    {
        Http::fake([
            'callmelater.test/api/v1/templates' => Http::response([
                'data' => ['id' => 'tpl_456'],
            ], 201),
        ]);

        $result = $this->client->template('Alias Test')->create();

        $this->assertEquals('tpl_456', $result['id']);
    }

    public function test_update_template(): void
    {
        Http::fake([
            'callmelater.test/api/v1/templates/tpl_123' => Http::response([
                'data' => ['id' => 'tpl_123', 'name' => 'Updated Name'],
            ], 200),
        ]);

        $result = $this->client->template('Updated Name')
            ->description('New description')
            ->update('tpl_123');

        $this->assertEquals('tpl_123', $result['id']);
        $this->assertEquals('Updated Name', $result['name']);
    }

    public function test_get_template(): void
    {
        Http::fake([
            'callmelater.test/api/v1/templates/tpl_123' => Http::response([
                'data' => ['id' => 'tpl_123', 'name' => 'My Template'],
            ], 200),
        ]);

        $result = $this->client->getTemplate('tpl_123');

        $this->assertEquals('tpl_123', $result['id']);
    }

    public function test_list_templates(): void
    {
        Http::fake([
            'callmelater.test/api/v1/templates*' => Http::response([
                'data' => [
                    ['id' => 'tpl_1'],
                    ['id' => 'tpl_2'],
                ],
                'meta' => ['total' => 2],
            ], 200),
        ]);

        $result = $this->client->listTemplates();

        $this->assertCount(2, $result['data']);
    }

    public function test_delete_template(): void
    {
        Http::fake([
            'callmelater.test/api/v1/templates/tpl_123' => Http::response([
                'message' => 'Template deleted',
            ], 200),
        ]);

        $result = $this->client->deleteTemplate('tpl_123');

        $this->assertEquals('Template deleted', $result['message']);
    }

    public function test_regenerate_template_token(): void
    {
        Http::fake([
            'callmelater.test/api/v1/templates/tpl_123/regenerate-token' => Http::response([
                'data' => ['trigger_token' => 'clmt_new_token'],
            ], 200),
        ]);

        $result = $this->client->regenerateTemplateToken('tpl_123');

        $this->assertEquals('clmt_new_token', $result['trigger_token']);
    }

    public function test_toggle_template(): void
    {
        Http::fake([
            'callmelater.test/api/v1/templates/tpl_123/toggle' => Http::response([
                'data' => ['id' => 'tpl_123', 'enabled' => false],
            ], 200),
        ]);

        $result = $this->client->toggleTemplate('tpl_123');

        $this->assertFalse($result['enabled']);
    }

    public function test_template_limits(): void
    {
        Http::fake([
            'callmelater.test/api/v1/templates/limits' => Http::response([
                'data' => ['max_templates' => 50, 'current' => 12],
            ], 200),
        ]);

        $result = $this->client->templateLimits();

        $this->assertEquals(50, $result['max_templates']);
        $this->assertEquals(12, $result['current']);
    }

    public function test_trigger_template(): void
    {
        Http::fake([
            'callmelater.test/t/clmt_abc123' => Http::response([
                'data' => ['id' => 'act_789', 'status' => 'pending_resolution'],
            ], 201),
        ]);

        $result = $this->client->trigger('clmt_abc123', [
            'invoice_id' => 'INV-001',
            'approver_email' => 'boss@example.com',
        ]);

        $this->assertEquals('act_789', $result['id']);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['invoice_id'] === 'INV-001'
                && $body['approver_email'] === 'boss@example.com';
        });
    }

    public function test_send_template_throws_on_api_error(): void
    {
        Http::fake([
            'callmelater.test/api/v1/templates' => Http::response([
                'message' => 'Validation failed',
                'errors' => ['name' => ['The name field is required.']],
            ], 422),
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Failed to create template');

        $this->client->template('')->send();
    }

    public function test_trigger_throws_on_invalid_token(): void
    {
        Http::fake([
            'callmelater.test/t/invalid_token' => Http::response([
                'message' => 'Invalid trigger token',
            ], 404),
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Failed to trigger template');

        $this->client->trigger('invalid_token', []);
    }
}
