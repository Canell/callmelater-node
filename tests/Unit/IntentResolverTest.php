<?php

namespace Tests\Unit;

use App\Services\IntentResolver;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class IntentResolverTest extends TestCase
{
    private IntentResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new IntentResolver;
    }

    // ==================== ABSOLUTE TIMESTAMP ====================

    public function test_resolve_absolute_timestamp(): void
    {
        $executeAt = '2025-06-15T14:30:00Z';

        $result = $this->resolver->resolve(['execute_at' => $executeAt]);

        $this->assertEquals('2025-06-15T14:30:00+00:00', $result->toIso8601String());
    }

    public function test_resolve_absolute_timestamp_with_timezone(): void
    {
        // Even with timezone parameter, execute_at is parsed and converted to UTC
        $executeAt = '2025-06-15T14:30:00-05:00';

        $result = $this->resolver->resolve(['execute_at' => $executeAt], 'America/New_York');

        $this->assertEquals('2025-06-15T19:30:00+00:00', $result->toIso8601String());
    }

    // ==================== DELAY FORMATS ====================

    public function test_resolve_delay_minutes(): void
    {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $result = $this->resolver->resolve(['delay' => '30m']);

        $this->assertEquals('2025-06-15T10:30:00+00:00', $result->toIso8601String());
    }

    public function test_resolve_delay_minutes_alt_format(): void
    {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $result = $this->resolver->resolve(['delay' => '30min']);

        $this->assertEquals('2025-06-15T10:30:00+00:00', $result->toIso8601String());
    }

    public function test_resolve_delay_hours(): void
    {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $result = $this->resolver->resolve(['delay' => '2h']);

        $this->assertEquals('2025-06-15T12:00:00+00:00', $result->toIso8601String());
    }

    public function test_resolve_delay_hours_alt_format(): void
    {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $result = $this->resolver->resolve(['delay' => '2hr']);

        $this->assertEquals('2025-06-15T12:00:00+00:00', $result->toIso8601String());
    }

    public function test_resolve_delay_days(): void
    {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $result = $this->resolver->resolve(['delay' => '3d']);

        $this->assertEquals('2025-06-18T10:00:00+00:00', $result->toIso8601String());
    }

    public function test_resolve_delay_days_alt_format(): void
    {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $result = $this->resolver->resolve(['delay' => '3day']);

        $this->assertEquals('2025-06-18T10:00:00+00:00', $result->toIso8601String());
    }

    public function test_resolve_delay_weeks(): void
    {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $result = $this->resolver->resolve(['delay' => '2w']);

        $this->assertEquals('2025-06-29T10:00:00+00:00', $result->toIso8601String());
    }

    public function test_resolve_delay_weeks_alt_format(): void
    {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $result = $this->resolver->resolve(['delay' => '2week']);

        $this->assertEquals('2025-06-29T10:00:00+00:00', $result->toIso8601String());
    }

    public function test_resolve_delay_invalid_format_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid delay format');

        $this->resolver->resolve(['delay' => 'invalid']);
    }

    // ==================== PRESETS ====================

    public function test_resolve_preset_tomorrow(): void
    {
        Carbon::setTestNow('2025-06-15 14:30:00');

        $result = $this->resolver->resolve(['preset' => 'tomorrow']);

        // Tomorrow at the same time
        $this->assertEquals('2025-06-16T14:30:00+00:00', $result->toIso8601String());
    }

    public function test_resolve_preset_next_week(): void
    {
        Carbon::setTestNow('2025-06-15 14:30:00');

        $result = $this->resolver->resolve(['preset' => 'next_week']);

        $this->assertEquals('2025-06-22T14:30:00+00:00', $result->toIso8601String());
    }

    public function test_resolve_preset_next_monday_from_wednesday(): void
    {
        // Wednesday June 18, 2025
        Carbon::setTestNow('2025-06-18 10:00:00');

        $result = $this->resolver->resolve(['preset' => 'next_monday']);

        // Next Monday is June 23, 2025
        $this->assertEquals('2025-06-23T10:00:00+00:00', $result->toIso8601String());
    }

    public function test_resolve_preset_next_monday_from_monday(): void
    {
        // Monday June 16, 2025
        Carbon::setTestNow('2025-06-16 10:00:00');

        $result = $this->resolver->resolve(['preset' => 'next_monday']);

        // Next Monday is June 23, 2025 (not today)
        $this->assertEquals('2025-06-23T10:00:00+00:00', $result->toIso8601String());
    }

    public function test_resolve_preset_next_friday(): void
    {
        // Monday June 16, 2025
        Carbon::setTestNow('2025-06-16 10:00:00');

        $result = $this->resolver->resolve(['preset' => 'next_friday']);

        // Next Friday is June 20, 2025
        $this->assertEquals('2025-06-20T10:00:00+00:00', $result->toIso8601String());
    }

    public function test_resolve_preset_1h(): void
    {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $result = $this->resolver->resolve(['preset' => '1h']);

        $this->assertEquals('2025-06-15T11:00:00+00:00', $result->toIso8601String());
    }

    public function test_resolve_preset_1_hour(): void
    {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $result = $this->resolver->resolve(['preset' => '1_hour']);

        $this->assertEquals('2025-06-15T11:00:00+00:00', $result->toIso8601String());
    }

    public function test_resolve_preset_2h(): void
    {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $result = $this->resolver->resolve(['preset' => '2h']);

        $this->assertEquals('2025-06-15T12:00:00+00:00', $result->toIso8601String());
    }

    public function test_resolve_preset_4h(): void
    {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $result = $this->resolver->resolve(['preset' => '4h']);

        $this->assertEquals('2025-06-15T14:00:00+00:00', $result->toIso8601String());
    }

    public function test_resolve_preset_1d(): void
    {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $result = $this->resolver->resolve(['preset' => '1d']);

        $this->assertEquals('2025-06-16T10:00:00+00:00', $result->toIso8601String());
    }

    public function test_resolve_preset_3d(): void
    {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $result = $this->resolver->resolve(['preset' => '3d']);

        $this->assertEquals('2025-06-18T10:00:00+00:00', $result->toIso8601String());
    }

    public function test_resolve_preset_1w(): void
    {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $result = $this->resolver->resolve(['preset' => '1w']);

        $this->assertEquals('2025-06-22T10:00:00+00:00', $result->toIso8601String());
    }

    public function test_resolve_preset_unknown_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown preset');

        $this->resolver->resolve(['preset' => 'unknown_preset']);
    }

    // ==================== AT/ON SPECIFIC TIME ====================

    public function test_resolve_at_time_today_future(): void
    {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $result = $this->resolver->resolve(['at' => '15:30']);

        $this->assertEquals('2025-06-15T15:30:00+00:00', $result->toIso8601String());
    }

    public function test_resolve_at_time_today_past_becomes_tomorrow(): void
    {
        Carbon::setTestNow('2025-06-15 16:00:00');

        $result = $this->resolver->resolve(['at' => '15:30']);

        // Time has passed, so it schedules for tomorrow
        $this->assertEquals('2025-06-16T15:30:00+00:00', $result->toIso8601String());
    }

    public function test_resolve_at_time_on_specific_date(): void
    {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $result = $this->resolver->resolve(['at' => '09:00', 'on' => '2025-06-20']);

        $this->assertEquals('2025-06-20T09:00:00+00:00', $result->toIso8601String());
    }

    // ==================== TIMEZONE HANDLING ====================

    public function test_resolve_with_user_timezone(): void
    {
        Carbon::setTestNow('2025-06-15 10:00:00'); // UTC

        // User in New York schedules 1 hour delay
        $result = $this->resolver->resolve(['delay' => '1h'], 'America/New_York');

        // Result should be UTC (timezone name can be 'UTC' or '+00:00')
        $this->assertTrue(
            in_array($result->getTimezone()->getName(), ['UTC', '+00:00']),
            "Expected UTC timezone, got: {$result->getTimezone()->getName()}"
        );
    }

    public function test_resolve_preset_tomorrow_with_timezone(): void
    {
        // Set "now" in a specific timezone context
        Carbon::setTestNow(Carbon::parse('2025-06-15 14:00:00', 'America/New_York'));

        $result = $this->resolver->resolve(['preset' => 'tomorrow'], 'America/New_York');

        // Tomorrow should be June 16
        $this->assertEquals('2025-06-16', $result->setTimezone('America/New_York')->toDateString());
    }

    // ==================== DST TRANSITIONS ====================

    public function test_dst_spring_forward(): void
    {
        // March 9, 2025 at 1:30 AM - just before DST transition at 2 AM
        // In America/New_York, clocks jump from 2:00 AM to 3:00 AM
        Carbon::setTestNow(Carbon::parse('2025-03-09 01:30:00', 'America/New_York'));

        $result = $this->resolver->resolve(['delay' => '1h'], 'America/New_York');

        // After 1 hour delay, it should be 3:30 AM (skipping 2:00-3:00)
        $expected = Carbon::parse('2025-03-09 01:30:00', 'America/New_York')->addHour()->utc();
        $this->assertEquals($expected->toIso8601String(), $result->toIso8601String());
    }

    public function test_dst_fall_back(): void
    {
        // November 2, 2025 at 1:30 AM - during DST "fall back"
        // In America/New_York, clocks go from 2:00 AM back to 1:00 AM
        Carbon::setTestNow(Carbon::parse('2025-11-02 01:30:00', 'America/New_York'));

        $result = $this->resolver->resolve(['delay' => '1h'], 'America/New_York');

        // After 1 hour delay from 1:30 AM
        $expected = Carbon::parse('2025-11-02 01:30:00', 'America/New_York')->addHour()->utc();
        $this->assertEquals($expected->toIso8601String(), $result->toIso8601String());
    }

    // ==================== EDGE CASES ====================

    public function test_resolve_empty_payload_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid intent payload');

        $this->resolver->resolve([]);
    }

    public function test_resolve_invalid_payload_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->resolver->resolve(['unknown_key' => 'value']);
    }

    // ==================== TIMEZONE VALIDATION ====================

    public function test_is_valid_timezone_returns_true_for_valid(): void
    {
        $this->assertTrue($this->resolver->isValidTimezone('UTC'));
        $this->assertTrue($this->resolver->isValidTimezone('America/New_York'));
        $this->assertTrue($this->resolver->isValidTimezone('Europe/London'));
        $this->assertTrue($this->resolver->isValidTimezone('Asia/Tokyo'));
    }

    public function test_is_valid_timezone_returns_false_for_invalid(): void
    {
        $this->assertFalse($this->resolver->isValidTimezone('Invalid/Timezone'));
        $this->assertFalse($this->resolver->isValidTimezone('Not_A_Timezone'));
        $this->assertFalse($this->resolver->isValidTimezone(''));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // Reset test time
        parent::tearDown();
    }
}
