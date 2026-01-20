<?php

namespace Tests\Unit;

use App\Services\HttpRequestService;
use App\Services\UrlValidator;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HttpRequestServiceTest extends TestCase
{
    private HttpRequestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HttpRequestService(new UrlValidator);
    }

    public function test_successful_request(): void
    {
        // Disable IP blocking since Http::fake() doesn't make real network calls
        config(['callmelater.http.block_private_ips' => false]);

        Http::fake([
            'https://example.com/webhook' => Http::response(['status' => 'ok'], 200),
        ]);

        $result = $this->service->execute([
            'url' => 'https://example.com/webhook',
            'method' => 'POST',
            'body' => ['test' => true],
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['status_code']);
        $this->assertNull($result['error']);
        $this->assertGreaterThanOrEqual(0, $result['duration_ms']);
    }

    public function test_server_error_response(): void
    {
        // Disable IP blocking since Http::fake() doesn't make real network calls
        config(['callmelater.http.block_private_ips' => false]);

        Http::fake([
            'https://example.com/webhook' => Http::response('Server Error', 500),
        ]);

        $result = $this->service->execute([
            'url' => 'https://example.com/webhook',
            'method' => 'POST',
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals(500, $result['status_code']);
        $this->assertEquals('HTTP 500', $result['error']);
    }

    public function test_client_error_response(): void
    {
        // Disable IP blocking since Http::fake() doesn't make real network calls
        config(['callmelater.http.block_private_ips' => false]);

        Http::fake([
            'https://example.com/webhook' => Http::response('Not Found', 404),
        ]);

        $result = $this->service->execute([
            'url' => 'https://example.com/webhook',
            'method' => 'GET',
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals(404, $result['status_code']);
    }

    public function test_blocked_private_ip(): void
    {
        $result = $this->service->execute([
            'url' => 'http://192.168.1.1/internal',
            'method' => 'GET',
        ]);

        $this->assertFalse($result['success']);
        $this->assertNull($result['status_code']);
        $this->assertStringContainsString('private IP', $result['error']);
    }

    public function test_blocked_localhost(): void
    {
        $result = $this->service->execute([
            'url' => 'http://localhost/internal',
            'method' => 'GET',
        ]);

        $this->assertFalse($result['success']);
        $this->assertNull($result['status_code']);
        $this->assertStringContainsString('not allowed', $result['error']);
    }

    public function test_generate_signature(): void
    {
        $body = ['event' => 'test', 'user_id' => 123];
        $secret = 'test-secret';

        $signature = $this->service->generateSignature($body, $secret);

        $this->assertStringStartsWith('sha256=', $signature);

        // Verify it's a valid HMAC
        $expectedPayload = json_encode($body);
        $expectedHash = hash_hmac('sha256', $expectedPayload, $secret);
        $this->assertEquals("sha256={$expectedHash}", $signature);
    }

    public function test_generate_signature_with_null_body(): void
    {
        $secret = 'test-secret';

        $signature = $this->service->generateSignature(null, $secret);

        $this->assertStringStartsWith('sha256=', $signature);
        $expectedHash = hash_hmac('sha256', '', $secret);
        $this->assertEquals("sha256={$expectedHash}", $signature);
    }
}
