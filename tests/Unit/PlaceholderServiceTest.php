<?php

namespace Tests\Unit;

use App\Services\PlaceholderService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PlaceholderServiceTest extends TestCase
{
    private PlaceholderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PlaceholderService;
    }

    // ==================== Extraction ====================

    #[Test]
    public function extracts_placeholders_from_string(): void
    {
        $result = $this->service->extractPlaceholders('Hello {{name}}!');
        $this->assertEquals(['name'], $result);
    }

    #[Test]
    public function extracts_multiple_placeholders(): void
    {
        $result = $this->service->extractPlaceholders('Deploy {{service}} v{{version}} to {{env}}');
        $this->assertEquals(['service', 'version', 'env'], $result);
    }

    #[Test]
    public function handles_no_placeholders(): void
    {
        $result = $this->service->extractPlaceholders('No placeholders here');
        $this->assertEquals([], $result);
    }

    #[Test]
    public function deduplicates_repeated_placeholders(): void
    {
        $result = $this->service->extractPlaceholders('{{name}} and {{name}} again');
        $this->assertEquals(['name'], $result);
    }

    #[Test]
    public function validates_placeholder_name_format(): void
    {
        // Valid placeholders
        $this->assertEquals(['foo'], $this->service->extractPlaceholders('{{foo}}'));
        $this->assertEquals(['_bar'], $this->service->extractPlaceholders('{{_bar}}'));
        $this->assertEquals(['foo_bar123'], $this->service->extractPlaceholders('{{foo_bar123}}'));

        // Invalid placeholders (should not match)
        $this->assertEquals([], $this->service->extractPlaceholders('{{123invalid}}'));
        $this->assertEquals([], $this->service->extractPlaceholders('{{foo-bar}}'));
        $this->assertEquals([], $this->service->extractPlaceholders('{{foo bar}}'));
    }

    // ==================== Substitution ====================

    #[Test]
    public function substitutes_single_placeholder(): void
    {
        $result = $this->service->substitute('Hello {{name}}!', ['name' => 'World']);
        $this->assertEquals('Hello World!', $result);
    }

    #[Test]
    public function substitutes_multiple_placeholders(): void
    {
        $result = $this->service->substitute(
            'Deploy {{service}} v{{version}}',
            ['service' => 'api', 'version' => '2.0']
        );
        $this->assertEquals('Deploy api v2.0', $result);
    }

    #[Test]
    public function keeps_unmatched_placeholders(): void
    {
        $result = $this->service->substitute('{{matched}} and {{unmatched}}', ['matched' => 'yes']);
        $this->assertEquals('yes and {{unmatched}}', $result);
    }

    #[Test]
    public function handles_empty_values(): void
    {
        $result = $this->service->substitute('Value: {{empty}}', ['empty' => '']);
        $this->assertEquals('Value: ', $result);
    }

    #[Test]
    public function handles_numeric_values(): void
    {
        $result = $this->service->substitute('Count: {{num}}', ['num' => 42]);
        $this->assertEquals('Count: 42', $result);
    }

    #[Test]
    public function handles_null_values(): void
    {
        $result = $this->service->substitute('Value: {{val}}', ['val' => null]);
        $this->assertEquals('Value: ', $result);
    }

    // ==================== Deep Substitution ====================

    #[Test]
    public function substitute_deep_in_nested_array(): void
    {
        $data = [
            'url' => 'https://api.example.com/{{service}}',
            'headers' => [
                'Authorization' => 'Bearer {{token}}',
            ],
        ];

        $result = $this->service->substituteDeep($data, [
            'service' => 'users',
            'token' => 'abc123',
        ]);

        $this->assertEquals([
            'url' => 'https://api.example.com/users',
            'headers' => [
                'Authorization' => 'Bearer abc123',
            ],
        ], $result);
    }

    #[Test]
    public function substitute_deep_in_object_values(): void
    {
        $data = [
            'body' => [
                'service' => '{{service}}',
                'config' => [
                    'env' => '{{env}}',
                    'version' => '{{version}}',
                ],
            ],
        ];

        $result = $this->service->substituteDeep($data, [
            'service' => 'api',
            'env' => 'prod',
            'version' => '1.0',
        ]);

        $this->assertEquals([
            'body' => [
                'service' => 'api',
                'config' => [
                    'env' => 'prod',
                    'version' => '1.0',
                ],
            ],
        ], $result);
    }

    #[Test]
    public function substitute_deep_preserves_non_strings(): void
    {
        $data = [
            'name' => '{{name}}',
            'count' => 42,
            'enabled' => true,
            'tags' => null,
        ];

        $result = $this->service->substituteDeep($data, ['name' => 'test']);

        $this->assertEquals([
            'name' => 'test',
            'count' => 42,
            'enabled' => true,
            'tags' => null,
        ], $result);
    }

    #[Test]
    public function substitute_deep_handles_indexed_arrays(): void
    {
        $data = [
            'recipients' => ['{{email1}}', '{{email2}}'],
        ];

        $result = $this->service->substituteDeep($data, [
            'email1' => 'a@example.com',
            'email2' => 'b@example.com',
        ]);

        $this->assertEquals([
            'recipients' => ['a@example.com', 'b@example.com'],
        ], $result);
    }

    // ==================== Validation ====================

    #[Test]
    public function validates_required_placeholders(): void
    {
        $defs = [
            ['name' => 'service', 'required' => true],
            ['name' => 'version', 'required' => true],
            ['name' => 'env', 'required' => false],
        ];

        // Missing required parameter
        $errors = $this->service->validateRequired($defs, ['service' => 'api']);
        $this->assertArrayHasKey('version', $errors);
        $this->assertArrayNotHasKey('service', $errors);
        $this->assertArrayNotHasKey('env', $errors);
    }

    #[Test]
    public function validation_passes_when_all_required_present(): void
    {
        $defs = [
            ['name' => 'service', 'required' => true],
            ['name' => 'version', 'required' => true],
        ];

        $errors = $this->service->validateRequired($defs, [
            'service' => 'api',
            'version' => '1.0',
        ]);

        $this->assertEmpty($errors);
    }

    #[Test]
    public function validation_ignores_optional_placeholders(): void
    {
        $defs = [
            ['name' => 'optional1', 'required' => false],
            ['name' => 'optional2'], // No 'required' key defaults to false
        ];

        $errors = $this->service->validateRequired($defs, []);
        $this->assertEmpty($errors);
    }

    #[Test]
    public function validation_handles_empty_definitions(): void
    {
        $errors = $this->service->validateRequired([], ['any' => 'value']);
        $this->assertEmpty($errors);
    }

    // ==================== Defaults ====================

    #[Test]
    public function applies_default_values(): void
    {
        $defs = [
            ['name' => 'env', 'default' => 'staging'],
            ['name' => 'region', 'default' => 'us-east-1'],
        ];

        $result = $this->service->applyDefaults($defs, []);

        $this->assertEquals([
            'env' => 'staging',
            'region' => 'us-east-1',
        ], $result);
    }

    #[Test]
    public function does_not_override_provided_values_with_defaults(): void
    {
        $defs = [
            ['name' => 'env', 'default' => 'staging'],
        ];

        $result = $this->service->applyDefaults($defs, ['env' => 'production']);

        $this->assertEquals(['env' => 'production'], $result);
    }

    #[Test]
    public function handles_null_default_values(): void
    {
        $defs = [
            ['name' => 'optional', 'default' => null],
        ];

        $result = $this->service->applyDefaults($defs, []);

        $this->assertArrayHasKey('optional', $result);
        $this->assertNull($result['optional']);
    }

    #[Test]
    public function handles_empty_string_default(): void
    {
        $defs = [
            ['name' => 'prefix', 'default' => ''],
        ];

        $result = $this->service->applyDefaults($defs, []);

        $this->assertArrayHasKey('prefix', $result);
        $this->assertEquals('', $result['prefix']);
    }

    #[Test]
    public function preserves_explicit_null_parameter(): void
    {
        $defs = [
            ['name' => 'value', 'default' => 'default_value'],
        ];

        $result = $this->service->applyDefaults($defs, ['value' => null]);

        // The provided null should not be overwritten with default
        $this->assertNull($result['value']);
    }

    // ==================== Extract From Config ====================

    #[Test]
    public function extracts_from_request_config(): void
    {
        $config = [
            'request_config' => [
                'url' => 'https://api.example.com/{{service}}',
                'headers' => [
                    'Authorization' => 'Bearer {{token}}',
                ],
                'body' => [
                    'version' => '{{version}}',
                ],
            ],
        ];

        $result = $this->service->extractFromConfig($config);

        $this->assertContains('service', $result);
        $this->assertContains('token', $result);
        $this->assertContains('version', $result);
    }

    #[Test]
    public function extracts_from_gate_config(): void
    {
        $config = [
            'gate_config' => [
                'message' => 'Deploy {{service}} to {{env}}?',
                'recipients' => ['{{approver}}'],
            ],
        ];

        $result = $this->service->extractFromConfig($config);

        $this->assertContains('service', $result);
        $this->assertContains('env', $result);
        $this->assertContains('approver', $result);
    }

    #[Test]
    public function extracts_from_name(): void
    {
        $config = [
            'name' => 'Deploy {{service}}',
        ];

        $result = $this->service->extractFromConfig($config);

        $this->assertEquals(['service'], $result);
    }
}
