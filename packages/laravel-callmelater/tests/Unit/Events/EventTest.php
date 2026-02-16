<?php

namespace CallMeLater\Laravel\Tests\Unit\Events;

use CallMeLater\Laravel\Events\ActionExecuted;
use CallMeLater\Laravel\Events\ActionExpired;
use CallMeLater\Laravel\Events\ActionFailed;
use CallMeLater\Laravel\Events\ReminderResponded;
use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{
    public function test_action_executed_from_payload(): void
    {
        $payload = [
            'action_id' => 'act_1',
            'action_name' => 'Test',
            'execution' => ['status_code' => 200],
        ];

        $event = ActionExecuted::fromPayload($payload);

        $this->assertEquals('act_1', $event->actionId);
        $this->assertEquals('Test', $event->actionName);
        $this->assertEquals(['status_code' => 200], $event->execution);
        $this->assertEquals($payload, $event->payload);
    }

    public function test_action_failed_from_payload(): void
    {
        $payload = [
            'action_id' => 'act_2',
            'failure' => ['reason' => 'timeout'],
        ];

        $event = ActionFailed::fromPayload($payload);

        $this->assertEquals('act_2', $event->actionId);
        $this->assertEquals('', $event->actionName);
        $this->assertEquals(['reason' => 'timeout'], $event->failure);
    }

    public function test_action_expired_from_payload(): void
    {
        $payload = [
            'action_id' => 'act_3',
            'action_name' => 'Expired',
            'expiration' => ['expired_at' => '2026-01-01'],
        ];

        $event = ActionExpired::fromPayload($payload);

        $this->assertEquals('act_3', $event->actionId);
        $this->assertEquals(['expired_at' => '2026-01-01'], $event->expiration);
    }

    public function test_reminder_responded_from_payload(): void
    {
        $payload = [
            'action_id' => 'act_4',
            'action_name' => 'Approval',
            'response' => 'confirmed',
            'responder_email' => 'user@example.com',
            'responded_at' => '2026-02-16T10:00:00Z',
            'comment' => 'Looks good',
        ];

        $event = ReminderResponded::fromPayload($payload);

        $this->assertEquals('act_4', $event->actionId);
        $this->assertEquals('confirmed', $event->response);
        $this->assertEquals('user@example.com', $event->responderEmail);
        $this->assertEquals('Looks good', $event->comment);
        $this->assertTrue($event->isConfirmed());
        $this->assertFalse($event->isDeclined());
        $this->assertFalse($event->isSnoozed());
    }

    public function test_reminder_responded_states(): void
    {
        $declined = ReminderResponded::fromPayload([
            'action_id' => 'act_5',
            'response' => 'declined',
        ]);
        $this->assertTrue($declined->isDeclined());
        $this->assertFalse($declined->isConfirmed());

        $snoozed = ReminderResponded::fromPayload([
            'action_id' => 'act_6',
            'response' => 'snoozed',
        ]);
        $this->assertTrue($snoozed->isSnoozed());
    }
}
