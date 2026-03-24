import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { CallMeLater } from '../src/index.js';

describe('HttpActionBuilder', () => {
  let client: CallMeLater;
  let fetchSpy: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    fetchSpy = vi.fn();
    vi.stubGlobal('fetch', fetchSpy);
    client = new CallMeLater({
      apiToken: 'sk_live_test',
      timezone: 'UTC',
      retry: { maxAttempts: 3, retryStrategy: 'exponential' },
    });
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('basic configuration', () => {
    it('creates minimal HTTP action payload', () => {
      const payload = client.http('https://example.com/api').toJSON();

      expect(payload).toEqual({
        mode: 'immediate',
        timezone: 'UTC',
        request: {
          url: 'https://example.com/api',
          method: 'POST',
        },
        max_attempts: 3,
        retry_strategy: 'exponential',
      });
    });

    it('sets HTTP method via method()', () => {
      const payload = client.http('https://example.com').method('PATCH').toJSON();
      expect((payload.request as Record<string, unknown>).method).toBe('PATCH');
    });

    it('has shortcut methods for HTTP verbs', () => {
      expect((client.http('https://a.com').get().toJSON().request as Record<string, unknown>).method).toBe('GET');
      expect((client.http('https://a.com').post().toJSON().request as Record<string, unknown>).method).toBe('POST');
      expect((client.http('https://a.com').put().toJSON().request as Record<string, unknown>).method).toBe('PUT');
      expect((client.http('https://a.com').patch().toJSON().request as Record<string, unknown>).method).toBe('PATCH');
      expect((client.http('https://a.com').delete().toJSON().request as Record<string, unknown>).method).toBe('DELETE');
    });

    it('converts method to uppercase', () => {
      const payload = client.http('https://example.com').method('post').toJSON();
      expect((payload.request as Record<string, unknown>).method).toBe('POST');
    });
  });

  describe('headers', () => {
    it('sets headers via headers()', () => {
      const payload = client.http('https://example.com')
        .headers({ 'X-Custom': 'value', 'X-Other': 'test' })
        .toJSON();

      expect((payload.request as Record<string, unknown>).headers).toEqual({
        'X-Custom': 'value',
        'X-Other': 'test',
      });
    });

    it('sets single header via header()', () => {
      const payload = client.http('https://example.com')
        .header('X-Api-Key', 'abc123')
        .toJSON();

      expect((payload.request as Record<string, unknown>).headers).toEqual({
        'X-Api-Key': 'abc123',
      });
    });

    it('merges multiple header calls', () => {
      const payload = client.http('https://example.com')
        .header('X-First', '1')
        .headers({ 'X-Second': '2' })
        .header('X-Third', '3')
        .toJSON();

      expect((payload.request as Record<string, unknown>).headers).toEqual({
        'X-First': '1',
        'X-Second': '2',
        'X-Third': '3',
      });
    });
  });

  describe('payload', () => {
    it('sets body via payload()', () => {
      const payload = client.http('https://example.com')
        .payload({ user_id: 123 })
        .toJSON();

      expect((payload.request as Record<string, unknown>).body).toEqual({ user_id: 123 });
    });

    it('body() is an alias for payload()', () => {
      const payload = client.http('https://example.com')
        .body({ order_id: 42 })
        .toJSON();

      expect((payload.request as Record<string, unknown>).body).toEqual({ order_id: 42 });
    });
  });

  describe('action fields', () => {
    it('sets name', () => {
      const payload = client.http('https://example.com').name('My Action').toJSON();
      expect(payload.name).toBe('My Action');
    });

    it('sets description', () => {
      const payload = client.http('https://example.com').description('Action desc').toJSON();
      expect(payload.description).toBe('Action desc');
    });

    it('sets idempotency key', () => {
      const payload = client.http('https://example.com').idempotencyKey('key-123').toJSON();
      expect(payload.idempotency_key).toBe('key-123');
    });

    it('sets callback URL', () => {
      const payload = client.http('https://example.com')
        .callback('https://myapp.com/webhook')
        .toJSON();

      expect(payload.callback_url).toBe('https://myapp.com/webhook');
    });

    it('onComplete() is an alias for callback()', () => {
      const payload = client.http('https://example.com')
        .onComplete('https://myapp.com/webhook')
        .toJSON();

      expect(payload.callback_url).toBe('https://myapp.com/webhook');
    });

    it('sets request timeout', () => {
      const payload = client.http('https://example.com')
        .requestTimeout(30)
        .toJSON();

      expect((payload.request as Record<string, unknown>).timeout).toBe(30);
    });

    it('sets webhook secret', () => {
      const payload = client.http('https://example.com')
        .webhookSecret('my-secret')
        .toJSON();

      expect(payload.webhook_secret).toBe('my-secret');
    });

    it('sets coordination keys', () => {
      const payload = client.http('https://example.com')
        .coordinationKeys(['user:123', 'order:456'])
        .toJSON();

      expect(payload.coordination_keys).toEqual(['user:123', 'order:456']);
    });

    it('sets coordination config', () => {
      const payload = client.http('https://example.com')
        .coordination({ on_create: 'replace_existing' })
        .toJSON();

      expect(payload.coordination).toEqual({ on_create: 'replace_existing' });
    });
  });

  describe('scheduling - relative delay', () => {
    it('delay() with minutes', () => {
      const payload = client.http('https://example.com').delay(5, 'minutes').toJSON();
      expect(payload.intent).toEqual({ delay: '5m' });
    });

    it('delay() with hours', () => {
      const payload = client.http('https://example.com').delay(2, 'hours').toJSON();
      expect(payload.intent).toEqual({ delay: '2h' });
    });

    it('delay() with days', () => {
      const payload = client.http('https://example.com').delay(1, 'days').toJSON();
      expect(payload.intent).toEqual({ delay: '1d' });
    });

    it('delay() with weeks', () => {
      const payload = client.http('https://example.com').delay(4, 'weeks').toJSON();
      expect(payload.intent).toEqual({ delay: '4w' });
    });

    it('inMinutes() shortcut', () => {
      const payload = client.http('https://example.com').inMinutes(30).toJSON();
      expect(payload.intent).toEqual({ delay: '30m' });
    });

    it('inHours() shortcut', () => {
      const payload = client.http('https://example.com').inHours(1).toJSON();
      expect(payload.intent).toEqual({ delay: '1h' });
    });

    it('inDays() shortcut', () => {
      const payload = client.http('https://example.com').inDays(7).toJSON();
      expect(payload.intent).toEqual({ delay: '7d' });
    });
  });

  describe('scheduling - presets', () => {
    it('at() with preset string', () => {
      const payload = client.http('https://example.com').at('tomorrow').toJSON();
      expect(payload.intent).toEqual({ preset: 'tomorrow' });
    });

    it('at() recognizes all valid API presets', () => {
      const presets = [
        'tomorrow', 'next_week', 'next_monday', 'next_tuesday',
        'next_wednesday', 'next_thursday', 'next_friday',
        'next_saturday', 'next_sunday',
        '1h', '2h', '4h', '1d', '3d', '1w', '1M',
        '1_hour', '2_hours', '4_hours', '1_day', '3_days', '1_week', '1_month',
      ];

      for (const preset of presets) {
        const payload = client.http('https://example.com').at(preset).toJSON();
        expect((payload.intent as Record<string, unknown>).preset).toBe(preset);
      }
    });
  });

  describe('scheduling - datetime', () => {
    it('at() with full datetime string uses execute_at', () => {
      const payload = client.http('https://example.com').at('2025-06-15 14:30:00').toJSON();
      expect(payload.execute_at).toBe('2025-06-15 14:30:00');
      expect(payload.intent).toBeUndefined();
    });

    it('at() with Date object uses execute_at with ISO string', () => {
      const date = new Date(2025, 5, 15, 14, 30, 0);
      const payload = client.http('https://example.com').at(date).toJSON();
      expect(payload.execute_at).toBe(date.toISOString());
      expect(payload.intent).toBeUndefined();
    });

    it('at() with time-only string uses intent.at', () => {
      const payload = client.http('https://example.com').at('14:30').toJSON();
      expect(payload.intent).toEqual({ at: '14:30' });
      expect(payload.execute_at).toBeUndefined();
    });

    it('at() with time-only string with seconds uses intent.at', () => {
      const payload = client.http('https://example.com').at('14:30:00').toJSON();
      expect(payload.intent).toEqual({ at: '14:30:00' });
      expect(payload.execute_at).toBeUndefined();
    });

    it('atTime() sets intent.at with optional date', () => {
      const payload = client.http('https://example.com').atTime('14:30', '2025-06-15').toJSON();
      expect(payload.intent).toEqual({ at: '14:30', on: '2025-06-15' });
    });

    it('atTime() without date sets only time', () => {
      const payload = client.http('https://example.com').atTime('09:00').toJSON();
      expect(payload.intent).toEqual({ at: '09:00' });
    });
  });

  describe('scheduling - timezone', () => {
    it('timezone is sent at top level', () => {
      const payload = client.http('https://example.com').inHours(1).toJSON();
      expect(payload.timezone).toBe('UTC');
      // Timezone should NOT be inside intent
      expect((payload.intent as Record<string, unknown>).timezone).toBeUndefined();
    });

    it('timezone() overrides client default at top level', () => {
      const payload = client.http('https://example.com')
        .timezone('America/New_York')
        .inHours(1)
        .toJSON();

      expect(payload.timezone).toBe('America/New_York');
    });

    it('timezone is present even without scheduling', () => {
      const payload = client.http('https://example.com').toJSON();
      expect(payload.timezone).toBe('UTC');
    });

    it('omits timezone when no client default and none set', () => {
      const noTimezoneClient = new CallMeLater({ apiToken: 'sk_live_test' });
      const payload = noTimezoneClient.http('https://example.com').toJSON();
      expect(payload.timezone).toBeUndefined();
    });
  });

  describe('retry configuration', () => {
    it('inherits client retry config', () => {
      const payload = client.http('https://example.com').toJSON();
      expect(payload.max_attempts).toBe(3);
      expect(payload.retry_strategy).toBe('exponential');
    });

    it('retry() overrides config', () => {
      const payload = client.http('https://example.com')
        .retry(5, 'linear')
        .toJSON();

      expect(payload.max_attempts).toBe(5);
      expect(payload.retry_strategy).toBe('linear');
    });

    it('noRetry() sets max_attempts to 1', () => {
      const payload = client.http('https://example.com').noRetry().toJSON();
      expect(payload.max_attempts).toBe(1);
      expect(payload.retry_strategy).toBeUndefined();
    });
  });

  describe('recurrence', () => {
    it('repeat() sets frequency and unit', () => {
      const payload = client.http('https://example.com').repeat(2, 'hours').toJSON();
      expect(payload.recurrence).toEqual({ frequency: 2, unit: 'h', end_type: 'never' });
    });

    it('everyDays() shortcut', () => {
      const payload = client.http('https://example.com').everyDays(1).toJSON();
      expect(payload.recurrence).toEqual({ frequency: 1, unit: 'd', end_type: 'never' });
    });

    it('maxOccurrences() sets count end type', () => {
      const payload = client.http('https://example.com').everyHours(2).maxOccurrences(10).toJSON();
      const rec = payload.recurrence as Record<string, unknown>;
      expect(rec.end_type).toBe('count');
      expect(rec.max_occurrences).toBe(10);
    });

    it('until() sets date end type', () => {
      const payload = client.http('https://example.com').everyDays(1).until('2026-12-31').toJSON();
      const rec = payload.recurrence as Record<string, unknown>;
      expect(rec.end_type).toBe('date');
      expect(rec.end_date).toBe('2026-12-31');
    });

    it('repeatForever() sets never end type', () => {
      const payload = client.http('https://example.com').everyWeeks(1).repeatForever().toJSON();
      const rec = payload.recurrence as Record<string, unknown>;
      expect(rec.end_type).toBe('never');
    });
  });

  describe('send()', () => {
    it('sends payload to API', async () => {
      fetchSpy.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({ data: { id: 'act_123' } }),
      });

      const result = await client.http('https://example.com')
        .post()
        .name('Test')
        .payload({ foo: 'bar' })
        .inMinutes(5)
        .send();

      expect(result.id).toBe('act_123');

      const sentBody = JSON.parse(fetchSpy.mock.calls[0][1].body);
      expect(sentBody.mode).toBe('immediate');
      expect(sentBody.request.url).toBe('https://example.com');
    });

    it('dispatch() is an alias for send()', async () => {
      fetchSpy.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({ data: { id: 'act_456' } }),
      });

      const result = await client.http('https://example.com').dispatch();
      expect(result.id).toBe('act_456');
    });
  });

  describe('full payload example', () => {
    it('builds a complete payload', () => {
      const payload = client.http('https://api.example.com/process')
        .post()
        .name('Process Order')
        .description('Process the order')
        .idempotencyKey('order-123')
        .headers({ 'X-Api-Key': 'secret' })
        .payload({ order_id: 123, action: 'process' })
        .inHours(2)
        .timezone('Europe/Paris')
        .retry(5, 'exponential')
        .callback('https://myapp.com/webhook')
        .requestTimeout(30)
        .webhookSecret('my-secret')
        .coordinationKeys(['order:123'])
        .toJSON();

      expect(payload).toEqual({
        mode: 'immediate',
        name: 'Process Order',
        description: 'Process the order',
        idempotency_key: 'order-123',
        timezone: 'Europe/Paris',
        request: {
          url: 'https://api.example.com/process',
          method: 'POST',
          headers: { 'X-Api-Key': 'secret' },
          body: { order_id: 123, action: 'process' },
          timeout: 30,
        },
        intent: {
          delay: '2h',
        },
        max_attempts: 5,
        retry_strategy: 'exponential',
        callback_url: 'https://myapp.com/webhook',
        webhook_secret: 'my-secret',
        coordination_keys: ['order:123'],
      });
    });
  });
});
