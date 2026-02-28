<?php

namespace App\Services;

use Carbon\Carbon;
use DateTimeZone;

class IntentResolver
{
    /**
     * Resolve an intent payload to a UTC timestamp.
     *
     * @param  array<string, mixed>  $intentPayload
     * @return Carbon The resolved UTC timestamp
     */
    public function resolve(array $intentPayload, string $timezone = 'UTC'): Carbon
    {
        $tz = new DateTimeZone($timezone);
        $now = Carbon::now($tz);

        // Absolute timestamp (ISO 8601)
        if (isset($intentPayload['execute_at'])) {
            return Carbon::parse($intentPayload['execute_at'])->utc();
        }

        // Preset (tomorrow, next_week, next_monday, etc.)
        if (isset($intentPayload['preset'])) {
            return $this->resolvePreset($intentPayload['preset'], $now)->utc();
        }

        // Relative delay (1h, 30m, 2d, etc.)
        if (isset($intentPayload['delay'])) {
            return $this->resolveDelay($intentPayload['delay'], $now)->utc();
        }

        // Specific time on a future date
        if (isset($intentPayload['at'])) {
            return $this->resolveAt($intentPayload['at'], $now, $intentPayload['on'] ?? null)->utc();
        }

        throw new \InvalidArgumentException('Invalid intent payload: no recognizable scheduling directive.');
    }

    /**
     * Resolve a preset to a Carbon instance in the user's timezone.
     */
    private function resolvePreset(string $preset, Carbon $now): Carbon
    {
        return match ($preset) {
            'tomorrow' => $now->copy()->addDay()->startOfDay()->setTime(
                $now->hour,
                $now->minute,
                0
            ),
            'next_week' => $now->copy()->addWeek(),
            'next_monday' => $this->nextWeekday($now, Carbon::MONDAY),
            'next_tuesday' => $this->nextWeekday($now, Carbon::TUESDAY),
            'next_wednesday' => $this->nextWeekday($now, Carbon::WEDNESDAY),
            'next_thursday' => $this->nextWeekday($now, Carbon::THURSDAY),
            'next_friday' => $this->nextWeekday($now, Carbon::FRIDAY),
            'next_saturday' => $this->nextWeekday($now, Carbon::SATURDAY),
            'next_sunday' => $this->nextWeekday($now, Carbon::SUNDAY),
            '1_hour', '1h' => $now->copy()->addHour(),
            '2_hours', '2h' => $now->copy()->addHours(2),
            '4_hours', '4h' => $now->copy()->addHours(4),
            '1_day', '1d' => $now->copy()->addDay(),
            '3_days', '3d' => $now->copy()->addDays(3),
            '1_week', '1w' => $now->copy()->addWeek(),
            default => throw new \InvalidArgumentException("Unknown preset: {$preset}"),
        };
    }

    /**
     * Get the next occurrence of a specific weekday, preserving time.
     */
    private function nextWeekday(Carbon $now, int $dayOfWeek): Carbon
    {
        $next = $now->copy()->next($dayOfWeek);

        return $next->setTime($now->hour, $now->minute, 0);
    }

    /**
     * Resolve a relative delay string (e.g., "1h", "30m", "2d", "3M").
     */
    private function resolveDelay(string $delay, Carbon $now): Carbon
    {
        // Try month unit first (case-sensitive: M, mo, month)
        if (preg_match('/^(\d+)(M|mo|month)s?$/', $delay, $matches)) {
            return $now->copy()->addMonths((int) $matches[1]);
        }

        // Parse delay string like "1h", "30m", "2d", "1w"
        if (! preg_match('/^(\d+)(m|min|h|hr|d|day|w|week)s?$/i', $delay, $matches)) {
            throw new \InvalidArgumentException("Invalid delay format: {$delay}");
        }

        $amount = (int) $matches[1];
        $unit = strtolower($matches[2]);

        return match ($unit) {
            'm', 'min' => $now->copy()->addMinutes($amount),
            'h', 'hr' => $now->copy()->addHours($amount),
            'd', 'day' => $now->copy()->addDays($amount),
            'w', 'week' => $now->copy()->addWeeks($amount),
            default => throw new \InvalidArgumentException("Unknown time unit: {$unit}"),
        };
    }

    /**
     * Resolve a specific time, optionally on a specific date.
     *
     * @param  string  $time  Time in HH:MM or HH:MM:SS format
     * @param  string|null  $on  Optional date in YYYY-MM-DD format
     */
    private function resolveAt(string $time, Carbon $now, ?string $on = null): Carbon
    {
        // Parse time
        $timeParts = explode(':', $time);
        $hour = (int) $timeParts[0];
        $minute = (int) ($timeParts[1] ?? 0);
        $second = (int) ($timeParts[2] ?? 0);

        if ($on !== null) {
            // Specific date provided
            $target = Carbon::parse($on, $now->timezone)->setTime($hour, $minute, $second);
        } else {
            // Today or tomorrow if time has passed
            $target = $now->copy()->setTime($hour, $minute, $second);
            if ($target->lte($now)) {
                $target->addDay();
            }
        }

        return $target;
    }

    /**
     * Validate a timezone string.
     */
    public function isValidTimezone(string $timezone): bool
    {
        try {
            new DateTimeZone($timezone);

            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
