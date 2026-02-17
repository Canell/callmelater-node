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
      retry: { maxAttempts: 3, backoff: 'exponential', initialDelay: 60 },
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

  describe('metadata', () => {
    it('sets name', () => {
      const payload = client.http('https://example.com').name('My Action').toJSON();
      expect(payload.name).toBe('My Action');
    });

    it('sets idempotency key', () => {
      const payload = client.http('https://example.com').idempotencyKey('key-123').toJSON();
      expect(payload.idempotency_key).toBe('key-123');
    });

    it('sets metadata via metadata()', () => {
      const payload = client.http('https://example.com')
        .metadata({ env: 'test', version: '1.0' })
        .toJSON();

      expect(payload.metadata).toEqual({ env: 'test', version: '1.0' });
    });

    it('sets single meta key via meta()', () => {
      const payload = client.http('https://example.com')
        .meta('key1', 'value1')
        .meta('key2', 42)
        .toJSON();

      expect(payload.metadata).toEqual({ key1: 'value1', key2: 42 });
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
  });

  describe('scheduling - relative delay', () => {
    it('delay() with minutes', () => {
      const payload = client.http('https://example.com').delay(5, 'minutes').toJSON();
      expect(payload.intent).toEqual({ delay: '5m', timezone: 'UTC' });
    });

    it('delay() with hours', () => {
      const payload = client.http('https://example.com').delay(2, 'hours').toJSON();
      expect(payload.intent).toEqual({ delay: '2h', timezone: 'UTC' });
    });

    it('delay() with days', () => {
      const payload = client.http('https://example.com').delay(1, 'days').toJSON();
      expect(payload.intent).toEqual({ delay: '1d', timezone: 'UTC' });
    });

    it('delay() with weeks', () => {
      const payload = client.http('https://example.com').delay(4, 'weeks').toJSON();
      expect(payload.intent).toEqual({ delay: '4w', timezone: 'UTC' });
    });

    it('inMinutes() shortcut', () => {
      const payload = client.http('https://example.com').inMinutes(30).toJSON();
      expect(payload.intent).toEqual({ delay: '30m', timezone: 'UTC' });
    });

    it('inHours() shortcut', () => {
      const payload = client.http('https://example.com').inHours(1).toJSON();
      expect(payload.intent).toEqual({ delay: '1h', timezone: 'UTC' });
    });

    it('inDays() shortcut', () => {
      const payload = client.http('https://example.com').inDays(7).toJSON();
      expect(payload.intent).toEqual({ delay: '7d', timezone: 'UTC' });
    });
  });

  describe('scheduling - presets', () => {
    it('at() with preset string', () => {
      const payload = client.http('https://example.com').at('tomorrow').toJSON();
      expect(payload.intent).toEqual({ preset: 'tomorrow', timezone: 'UTC' });
    });

    it('at() recognizes all presets', () => {
      const presets = [
        'tomorrow', 'next_week', 'next_monday', 'next_tuesday',
        'next_wednesday', 'next_thursday', 'next_friday', 'end_of_day',
        'end_of_week', 'end_of_month',
      ];

      for (const preset of presets) {
        const payload = client.http('https://example.com').at(preset).toJSON();
        expect((payload.intent as Record<string, unknown>).preset).toBe(preset);
      }
    });
  });

  describe('scheduling - datetime', () => {
    it('at() with datetime string', () => {
      const payload = client.http('https://example.com').at('2025-06-15 14:30:00').toJSON();
      expect(payload.intent).toEqual({ at: '2025-06-15 14:30:00', timezone: 'UTC' });
    });

    it('at() with Date object', () => {
      const date = new Date(2025, 5, 15, 14, 30, 0); // June 15, 2025 14:30:00
      const payload = client.http('https://example.com').at(date).toJSON();
      expect(payload.intent).toEqual({ at: '2025-06-15 14:30:00', timezone: 'UTC' });
    });
  });

  describe('scheduling - timezone', () => {
    it('uses client timezone by default', () => {
      const payload = client.http('https://example.com').inHours(1).toJSON();
      expect((payload.intent as Record<string, unknown>).timezone).toBe('UTC');
    });

    it('timezone() overrides client default', () => {
      const payload = client.http('https://example.com')
        .timezone('America/New_York')
        .inHours(1)
        .toJSON();

      expect((payload.intent as Record<string, unknown>).timezone).toBe('America/New_York');
    });

    it('omits intent when no scheduling is set', () => {
      const noTimezoneClient = new CallMeLater({ apiToken: 'sk_live_test' });
      const payload = noTimezoneClient.http('https://example.com').toJSON();
      expect(payload.intent).toBeUndefined();
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
        .retry(5, 'linear', 120)
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
        .idempotencyKey('order-123')
        .headers({ 'X-Api-Key': 'secret' })
        .payload({ order_id: 123, action: 'process' })
        .inHours(2)
        .timezone('Europe/Paris')
        .retry(5, 'exponential', 120)
        .callback('https://myapp.com/webhook')
        .metadata({ source: 'sdk-test' })
        .meta('version', '2.0')
        .toJSON();

      expect(payload).toEqual({
        mode: 'immediate',
        name: 'Process Order',
        idempotency_key: 'order-123',
        request: {
          url: 'https://api.example.com/process',
          method: 'POST',
          headers: { 'X-Api-Key': 'secret' },
          body: { order_id: 123, action: 'process' },
        },
        intent: {
          delay: '2h',
          timezone: 'Europe/Paris',
        },
        max_attempts: 5,
        retry_strategy: 'exponential',
        callback_url: 'https://myapp.com/webhook',
        metadata: { source: 'sdk-test', version: '2.0' },
      });
    });
  });
});
