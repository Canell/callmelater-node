import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { CallMeLater, CallMeLaterError } from '../src/index.js';

describe('ReminderBuilder', () => {
  let client: CallMeLater;
  let fetchSpy: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    fetchSpy = vi.fn();
    vi.stubGlobal('fetch', fetchSpy);
    client = new CallMeLater({ apiToken: 'sk_live_test', timezone: 'UTC' });
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('recipients', () => {
    it('to() adds email recipient with prefix', () => {
      const payload = client.reminder('Test')
        .to('user@example.com')
        .toJSON();

      expect((payload.gate as Record<string, unknown>).recipients).toEqual(['email:user@example.com']);
    });

    it('toMany() adds multiple email recipients', () => {
      const payload = client.reminder('Test')
        .toMany(['alice@test.com', 'bob@test.com'])
        .toJSON();

      expect((payload.gate as Record<string, unknown>).recipients).toEqual([
        'email:alice@test.com',
        'email:bob@test.com',
      ]);
    });

    it('toPhone() adds phone recipient with prefix', () => {
      const payload = client.reminder('Test')
        .toPhone('+1234567890')
        .toJSON();

      expect((payload.gate as Record<string, unknown>).recipients).toEqual(['phone:+1234567890']);
    });

    it('toChannel() adds channel recipient with prefix', () => {
      const payload = client.reminder('Test')
        .toChannel('uuid-123')
        .toJSON();

      expect((payload.gate as Record<string, unknown>).recipients).toEqual(['channel:uuid-123']);
    });

    it('toRecipient() adds raw URI', () => {
      const payload = client.reminder('Test')
        .toRecipient('slack:#general')
        .toJSON();

      expect((payload.gate as Record<string, unknown>).recipients).toEqual(['slack:#general']);
    });

    it('throws when no recipients', () => {
      expect(() => client.reminder('Test').toJSON()).toThrow(CallMeLaterError);
      expect(() => client.reminder('Test').toJSON()).toThrow('At least one recipient is required');
    });
  });

  describe('gate options', () => {
    it('sets message', () => {
      const payload = client.reminder('Test')
        .to('user@example.com')
        .message('Please approve')
        .toJSON();

      expect((payload.gate as Record<string, unknown>).message).toBe('Please approve');
    });

    it('allowSnooze() sets max_snoozes', () => {
      const payload = client.reminder('Test')
        .to('user@example.com')
        .allowSnooze(3)
        .toJSON();

      expect((payload.gate as Record<string, unknown>).max_snoozes).toBe(3);
    });

    it('allowSnooze() defaults to 5', () => {
      const payload = client.reminder('Test')
        .to('user@example.com')
        .allowSnooze()
        .toJSON();

      expect((payload.gate as Record<string, unknown>).max_snoozes).toBe(5);
    });

    it('noSnooze() sets max_snoozes to 0', () => {
      const payload = client.reminder('Test')
        .to('user@example.com')
        .noSnooze()
        .toJSON();

      expect((payload.gate as Record<string, unknown>).max_snoozes).toBe(0);
    });

    it('timeout() sets gate timeout duration', () => {
      const payload = client.reminder('Test')
        .to('user@example.com')
        .timeout('7d')
        .toJSON();

      expect((payload.gate as Record<string, unknown>).timeout).toBe('7d');
    });

    it('onTimeout() sets gate on_timeout action', () => {
      const payload = client.reminder('Test')
        .to('user@example.com')
        .onTimeout('approve')
        .toJSON();

      expect((payload.gate as Record<string, unknown>).on_timeout).toBe('approve');
    });

    it('channels() sets notification channels', () => {
      const payload = client.reminder('Test')
        .to('user@example.com')
        .channels(['email', 'sms', 'push'])
        .toJSON();

      expect((payload.gate as Record<string, unknown>).channels).toEqual(['email', 'sms', 'push']);
    });

    it('integrationIds() sets integration IDs', () => {
      const payload = client.reminder('Test')
        .to('user@example.com')
        .integrationIds(['uuid-1', 'uuid-2'])
        .toJSON();

      expect((payload.gate as Record<string, unknown>).integration_ids).toEqual(['uuid-1', 'uuid-2']);
    });

    it('requireAll() sets confirmation_mode', () => {
      const payload = client.reminder('Test')
        .to('user@example.com')
        .requireAll()
        .toJSON();

      expect((payload.gate as Record<string, unknown>).confirmation_mode).toBe('all_required');
    });

    it('firstResponse() sets confirmation_mode', () => {
      const payload = client.reminder('Test')
        .to('user@example.com')
        .firstResponse()
        .toJSON();

      expect((payload.gate as Record<string, unknown>).confirmation_mode).toBe('first_response');
    });

    it('escalateTo() sets escalation with auto-prefixed emails', () => {
      const payload = client.reminder('Test')
        .to('user@example.com')
        .escalateTo(['manager@example.com', 'phone:+1234567890'], 12)
        .toJSON();

      const gate = payload.gate as Record<string, unknown>;
      expect(gate.escalation).toEqual({
        contacts: ['email:manager@example.com', 'phone:+1234567890'],
        after_hours: 12,
      });
    });

    it('attach() adds attachments', () => {
      const payload = client.reminder('Test')
        .to('user@example.com')
        .attach('https://example.com/file.pdf', 'Report')
        .attach('https://example.com/image.png')
        .toJSON();

      const gate = payload.gate as Record<string, unknown>;
      expect(gate.attachments).toEqual([
        { url: 'https://example.com/file.pdf', name: 'Report' },
        { url: 'https://example.com/image.png' },
      ]);
    });
  });

  describe('scheduling', () => {
    it('at() with preset', () => {
      const payload = client.reminder('Test')
        .to('user@example.com')
        .at('tomorrow')
        .toJSON();

      expect(payload.intent).toEqual({ preset: 'tomorrow' });
    });

    it('at() with full datetime string uses execute_at', () => {
      const payload = client.reminder('Test')
        .to('user@example.com')
        .at('2025-06-15 09:00:00')
        .toJSON();

      expect(payload.execute_at).toBe('2025-06-15 09:00:00');
      expect(payload.intent).toBeUndefined();
    });

    it('at() with Date object uses execute_at', () => {
      const date = new Date(2025, 5, 15, 9, 0, 0);
      const payload = client.reminder('Test')
        .to('user@example.com')
        .at(date)
        .toJSON();

      expect(payload.execute_at).toBe(date.toISOString());
    });

    it('at() with time-only string uses intent.at', () => {
      const payload = client.reminder('Test')
        .to('user@example.com')
        .at('09:00')
        .toJSON();

      expect(payload.intent).toEqual({ at: '09:00' });
    });

    it('delay() creates relative intent', () => {
      const payload = client.reminder('Test')
        .to('user@example.com')
        .delay(30, 'minutes')
        .toJSON();

      expect(payload.intent).toEqual({ delay: '30m' });
    });

    it('inMinutes() shortcut', () => {
      const payload = client.reminder('Test')
        .to('user@example.com')
        .inMinutes(15)
        .toJSON();

      expect(payload.intent).toEqual({ delay: '15m' });
    });

    it('inHours() shortcut', () => {
      const payload = client.reminder('Test')
        .to('user@example.com')
        .inHours(2)
        .toJSON();

      expect(payload.intent).toEqual({ delay: '2h' });
    });

    it('inDays() shortcut', () => {
      const payload = client.reminder('Test')
        .to('user@example.com')
        .inDays(3)
        .toJSON();

      expect(payload.intent).toEqual({ delay: '3d' });
    });

    it('timezone is at top level', () => {
      const payload = client.reminder('Test')
        .to('user@example.com')
        .timezone('America/Chicago')
        .inHours(1)
        .toJSON();

      expect(payload.timezone).toBe('America/Chicago');
      // Timezone should NOT be inside intent
      expect((payload.intent as Record<string, unknown>).timezone).toBeUndefined();
    });
  });

  describe('other options', () => {
    it('sets description', () => {
      const payload = client.reminder('Test')
        .to('user@example.com')
        .description('A reminder')
        .toJSON();

      expect(payload.description).toBe('A reminder');
    });

    it('sets idempotency key', () => {
      const payload = client.reminder('Test')
        .to('user@example.com')
        .idempotencyKey('reminder-123')
        .toJSON();

      expect(payload.idempotency_key).toBe('reminder-123');
    });

    it('callback() sets callback URL', () => {
      const payload = client.reminder('Test')
        .to('user@example.com')
        .callback('https://myapp.com/webhook')
        .toJSON();

      expect(payload.callback_url).toBe('https://myapp.com/webhook');
    });

    it('onResponse() is alias for callback()', () => {
      const payload = client.reminder('Test')
        .to('user@example.com')
        .onResponse('https://myapp.com/webhook')
        .toJSON();

      expect(payload.callback_url).toBe('https://myapp.com/webhook');
    });

    it('notifyCreatorOnResponse() sets flag', () => {
      const payload = client.reminder('Test')
        .to('user@example.com')
        .notifyCreatorOnResponse()
        .toJSON();

      expect(payload.notify_creator_on_response).toBe(true);
    });

    it('sets coordination keys', () => {
      const payload = client.reminder('Test')
        .to('user@example.com')
        .coordinationKeys(['user:123'])
        .toJSON();

      expect(payload.coordination_keys).toEqual(['user:123']);
    });

    it('sets coordination config', () => {
      const payload = client.reminder('Test')
        .to('user@example.com')
        .coordination({ on_create: 'skip_if_exists' })
        .toJSON();

      expect(payload.coordination).toEqual({ on_create: 'skip_if_exists' });
    });
  });

  describe('toJSON() output structure', () => {
    it('produces correct gated payload', () => {
      const payload = client.reminder('Approve deployment')
        .to('manager@example.com')
        .message('Please approve the production deployment')
        .allowSnooze(3)
        .timeout('4h')
        .onTimeout('cancel')
        .channels(['email', 'push'])
        .inHours(1)
        .callback('https://myapp.com/webhook')
        .toJSON();

      expect(payload).toEqual({
        mode: 'gated',
        name: 'Approve deployment',
        timezone: 'UTC',
        gate: {
          recipients: ['email:manager@example.com'],
          message: 'Please approve the production deployment',
          max_snoozes: 3,
          timeout: '4h',
          on_timeout: 'cancel',
          channels: ['email', 'push'],
        },
        intent: {
          delay: '1h',
        },
        callback_url: 'https://myapp.com/webhook',
      });
    });
  });

  describe('send()', () => {
    it('sends via client.sendAction()', async () => {
      fetchSpy.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({ data: { id: 'rem_123' } }),
      });

      const result = await client.reminder('Test')
        .to('user@example.com')
        .send();

      expect(result.id).toBe('rem_123');
    });

    it('dispatch() is an alias for send()', async () => {
      fetchSpy.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({ data: { id: 'rem_456' } }),
      });

      const result = await client.reminder('Test')
        .to('user@example.com')
        .dispatch();

      expect(result.id).toBe('rem_456');
    });
  });
});
