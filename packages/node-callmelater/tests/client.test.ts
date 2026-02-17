import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { CallMeLater, ApiError, ConfigurationError } from '../src/index.js';

describe('CallMeLater Client', () => {
  let fetchSpy: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    fetchSpy = vi.fn();
    vi.stubGlobal('fetch', fetchSpy);
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  function mockResponse(data: unknown, status = 200) {
    return {
      ok: status >= 200 && status < 300,
      status,
      json: async () => data,
      text: async () => JSON.stringify(data),
    };
  }

  describe('constructor', () => {
    it('requires an API token', () => {
      expect(() => new CallMeLater({ apiToken: '' })).toThrow(ConfigurationError);
    });

    it('sets default API URL', () => {
      const client = new CallMeLater({ apiToken: 'sk_live_test' });
      expect(client).toBeInstanceOf(CallMeLater);
    });

    it('accepts custom config', () => {
      const client = new CallMeLater({
        apiToken: 'sk_live_test',
        apiUrl: 'https://custom.example.com/',
        webhookSecret: 'whsec_test',
        timezone: 'America/New_York',
        retry: { maxAttempts: 5, backoff: 'linear', initialDelay: 30 },
      });
      expect(client.getTimezone()).toBe('America/New_York');
      expect(client.getRetryConfig()).toEqual({
        max_attempts: 5,
        backoff: 'linear',
        initial_delay: 30,
      });
    });

    it('strips trailing slash from API URL', () => {
      const client = new CallMeLater({ apiToken: 'sk_live_test', apiUrl: 'https://example.com/' });
      fetchSpy.mockResolvedValueOnce(mockResponse({ data: { id: '1' } }));
      client.getAction('1');
      expect(fetchSpy).toHaveBeenCalledWith(
        'https://example.com/api/v1/actions/1',
        expect.any(Object),
      );
    });
  });

  describe('actions CRUD', () => {
    let client: CallMeLater;

    beforeEach(() => {
      client = new CallMeLater({ apiToken: 'sk_live_test' });
    });

    it('getAction sends GET with bearer auth', async () => {
      fetchSpy.mockResolvedValueOnce(mockResponse({ data: { id: '123', name: 'Test' } }));

      const result = await client.getAction('123');

      expect(fetchSpy).toHaveBeenCalledWith(
        'https://callmelater.io/api/v1/actions/123',
        expect.objectContaining({
          method: 'GET',
          headers: expect.objectContaining({
            Authorization: 'Bearer sk_live_test',
            Accept: 'application/json',
          }),
        }),
      );
      expect(result).toEqual({ id: '123', name: 'Test' });
    });

    it('listActions sends GET with query params', async () => {
      fetchSpy.mockResolvedValueOnce(mockResponse({ data: [{ id: '1' }], meta: {} }));

      const result = await client.listActions({ status: 'resolved', limit: '5' });

      expect(fetchSpy).toHaveBeenCalledWith(
        expect.stringContaining('status=resolved'),
        expect.any(Object),
      );
      expect(result).toHaveProperty('data');
    });

    it('cancelAction sends DELETE', async () => {
      fetchSpy.mockResolvedValueOnce(mockResponse({}));

      await client.cancelAction('123');

      expect(fetchSpy).toHaveBeenCalledWith(
        'https://callmelater.io/api/v1/actions/123',
        expect.objectContaining({ method: 'DELETE' }),
      );
    });

    it('sendAction sends POST with payload', async () => {
      fetchSpy.mockResolvedValueOnce(mockResponse({ data: { id: '456' } }));

      const result = await client.sendAction({ mode: 'immediate', request: { url: 'https://example.com', method: 'POST' } });

      expect(fetchSpy).toHaveBeenCalledWith(
        'https://callmelater.io/api/v1/actions',
        expect.objectContaining({
          method: 'POST',
          body: expect.any(String),
        }),
      );
      expect(result.id).toBe('456');
    });
  });

  describe('chains CRUD', () => {
    let client: CallMeLater;

    beforeEach(() => {
      client = new CallMeLater({ apiToken: 'sk_live_test' });
    });

    it('getChain fetches chain by ID', async () => {
      fetchSpy.mockResolvedValueOnce(mockResponse({ data: { id: 'ch_1', status: 'active' } }));
      const result = await client.getChain('ch_1');
      expect(result.id).toBe('ch_1');
    });

    it('listChains fetches chain list', async () => {
      fetchSpy.mockResolvedValueOnce(mockResponse({ data: [] }));
      const result = await client.listChains({ limit: '5' });
      expect(result).toHaveProperty('data');
    });

    it('cancelChain sends DELETE', async () => {
      fetchSpy.mockResolvedValueOnce(mockResponse({}));
      await client.cancelChain('ch_1');
      expect(fetchSpy).toHaveBeenCalledWith(
        expect.stringContaining('/chains/ch_1'),
        expect.objectContaining({ method: 'DELETE' }),
      );
    });

    it('sendChain sends POST', async () => {
      fetchSpy.mockResolvedValueOnce(mockResponse({ data: { id: 'ch_2' } }));
      const result = await client.sendChain({ name: 'test', steps: [] });
      expect(result.id).toBe('ch_2');
    });
  });

  describe('templates CRUD', () => {
    let client: CallMeLater;

    beforeEach(() => {
      client = new CallMeLater({ apiToken: 'sk_live_test' });
    });

    it('getTemplate fetches by ID', async () => {
      fetchSpy.mockResolvedValueOnce(mockResponse({ data: { id: 't_1', name: 'Template' } }));
      const result = await client.getTemplate('t_1');
      expect(result.name).toBe('Template');
    });

    it('listTemplates fetches list', async () => {
      fetchSpy.mockResolvedValueOnce(mockResponse({ data: [] }));
      await client.listTemplates();
      expect(fetchSpy).toHaveBeenCalledWith(
        expect.stringContaining('/templates'),
        expect.objectContaining({ method: 'GET' }),
      );
    });

    it('deleteTemplate sends DELETE', async () => {
      fetchSpy.mockResolvedValueOnce(mockResponse({}));
      await client.deleteTemplate('t_1');
      expect(fetchSpy).toHaveBeenCalledWith(
        expect.stringContaining('/templates/t_1'),
        expect.objectContaining({ method: 'DELETE' }),
      );
    });

    it('toggleTemplate sends POST', async () => {
      fetchSpy.mockResolvedValueOnce(mockResponse({ data: { id: 't_1', is_active: false } }));
      const result = await client.toggleTemplate('t_1');
      expect(result.is_active).toBe(false);
    });

    it('regenerateTemplateToken sends POST', async () => {
      fetchSpy.mockResolvedValueOnce(mockResponse({ data: { trigger_token: 'new_token' } }));
      const result = await client.regenerateTemplateToken('t_1');
      expect(result.trigger_token).toBe('new_token');
    });

    it('templateLimits sends GET', async () => {
      fetchSpy.mockResolvedValueOnce(mockResponse({ data: { max_templates: 10 } }));
      const result = await client.templateLimits();
      expect(result.max_templates).toBe(10);
    });

    it('sendTemplate sends POST', async () => {
      fetchSpy.mockResolvedValueOnce(mockResponse({ data: { id: 't_2' } }));
      const result = await client.sendTemplate({ name: 'test' });
      expect(result.id).toBe('t_2');
    });

    it('updateTemplate sends PUT', async () => {
      fetchSpy.mockResolvedValueOnce(mockResponse({ data: { id: 't_1', name: 'Updated' } }));
      const result = await client.updateTemplate('t_1', { name: 'Updated' });
      expect(result.name).toBe('Updated');
    });
  });

  describe('trigger', () => {
    it('sends POST to /t/:token', async () => {
      const client = new CallMeLater({ apiToken: 'sk_live_test' });
      fetchSpy.mockResolvedValueOnce(mockResponse({ data: { id: 'a_1' } }));

      const result = await client.trigger('tok_abc', { user_id: '42' });

      expect(fetchSpy).toHaveBeenCalledWith(
        'https://callmelater.io/t/tok_abc',
        expect.objectContaining({ method: 'POST' }),
      );
      expect(result.id).toBe('a_1');
    });
  });

  describe('error handling', () => {
    let client: CallMeLater;

    beforeEach(() => {
      client = new CallMeLater({ apiToken: 'sk_live_test' });
    });

    it('throws ApiError on 404', async () => {
      fetchSpy.mockResolvedValueOnce({
        ok: false,
        status: 404,
        json: async () => ({ message: 'Not found' }),
        text: async () => '{"message":"Not found"}',
      });

      await expect(client.getAction('bad-id')).rejects.toThrow(ApiError);

      try {
        await client.getAction('bad-id');
      } catch (e) {
        // First call already threw
      }
    });

    it('throws ApiError with validation errors on 422', async () => {
      fetchSpy.mockResolvedValueOnce({
        ok: false,
        status: 422,
        json: async () => ({ message: 'Validation failed', errors: { url: ['Invalid URL'] } }),
        text: async () => '{"message":"Validation failed","errors":{"url":["Invalid URL"]}}',
      });

      try {
        await client.sendAction({ mode: 'immediate', request: { url: 'bad' } });
        expect.unreachable('Should have thrown');
      } catch (e) {
        expect(e).toBeInstanceOf(ApiError);
        const apiErr = e as ApiError;
        expect(apiErr.statusCode).toBe(422);
        expect(apiErr.validationErrors).toHaveProperty('url');
        expect(apiErr.errorBag).toEqual(apiErr.validationErrors);
      }
    });

    it('throws ApiError with AuthenticationError name on 401', async () => {
      fetchSpy.mockResolvedValueOnce({
        ok: false,
        status: 401,
        json: async () => ({ message: 'Unauthenticated' }),
        text: async () => '{"message":"Unauthenticated"}',
      });

      try {
        await client.getAction('1');
        expect.unreachable('Should have thrown');
      } catch (e) {
        expect(e).toBeInstanceOf(ApiError);
        expect((e as ApiError).name).toBe('AuthenticationError');
        expect((e as ApiError).statusCode).toBe(401);
      }
    });
  });

  describe('webhooks', () => {
    it('returns WebhookHandler instance', () => {
      const client = new CallMeLater({ apiToken: 'sk_live_test', webhookSecret: 'whsec_test' });
      const handler = client.webhooks();
      expect(handler).toBeDefined();
    });
  });

  describe('builder factories', () => {
    it('http() returns HttpActionBuilder', () => {
      const client = new CallMeLater({ apiToken: 'sk_live_test' });
      const builder = client.http('https://example.com');
      expect(builder).toBeDefined();
      expect(typeof builder.post).toBe('function');
    });

    it('reminder() returns ReminderBuilder', () => {
      const client = new CallMeLater({ apiToken: 'sk_live_test' });
      const builder = client.reminder('Test');
      expect(builder).toBeDefined();
      expect(typeof builder.to).toBe('function');
    });

    it('chain() returns ChainBuilder', () => {
      const client = new CallMeLater({ apiToken: 'sk_live_test' });
      const builder = client.chain('Test Chain');
      expect(builder).toBeDefined();
      expect(typeof builder.addHttpStep).toBe('function');
    });

    it('template() returns TemplateBuilder', () => {
      const client = new CallMeLater({ apiToken: 'sk_live_test' });
      const builder = client.template('Test Template');
      expect(builder).toBeDefined();
      expect(typeof builder.requestConfig).toBe('function');
    });
  });
});
