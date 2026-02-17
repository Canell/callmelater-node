import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { CallMeLater } from '../src/index.js';

describe('TemplateBuilder', () => {
  let client: CallMeLater;
  let fetchSpy: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    fetchSpy = vi.fn();
    vi.stubGlobal('fetch', fetchSpy);
    client = new CallMeLater({ apiToken: 'sk_live_test' });
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('basic configuration', () => {
    it('creates minimal template', () => {
      const payload = client.template('My Template').toJSON();
      expect(payload).toEqual({ name: 'My Template' });
    });

    it('sets description', () => {
      const payload = client.template('Test')
        .description('A test template')
        .toJSON();

      expect(payload.description).toBe('A test template');
    });

    it('sets type', () => {
      const payload = client.template('Test')
        .type('action')
        .toJSON();

      expect(payload.type).toBe('action');
    });

    it('sets mode', () => {
      const payload = client.template('Test')
        .mode('immediate')
        .toJSON();

      expect(payload.mode).toBe('immediate');
    });

    it('sets timezone', () => {
      const payload = client.template('Test')
        .timezone('Europe/London')
        .toJSON();

      expect(payload.timezone).toBe('Europe/London');
    });
  });

  describe('request config', () => {
    it('sets request_config', () => {
      const payload = client.template('Test')
        .requestConfig({
          url: 'https://example.com/api',
          method: 'POST',
          body: { user_id: '{{user_id}}' },
        })
        .toJSON();

      expect(payload.request_config).toEqual({
        url: 'https://example.com/api',
        method: 'POST',
        body: { user_id: '{{user_id}}' },
      });
    });

    it('sets gate_config', () => {
      const payload = client.template('Test')
        .gateConfig({
          recipients: ['email:{{recipient}}'],
          message: 'Please approve',
        })
        .toJSON();

      expect(payload.gate_config).toEqual({
        recipients: ['email:{{recipient}}'],
        message: 'Please approve',
      });
    });
  });

  describe('retry config', () => {
    it('sets max_attempts', () => {
      const payload = client.template('Test')
        .maxAttempts(5)
        .toJSON();

      expect(payload.max_attempts).toBe(5);
    });

    it('sets retry_strategy', () => {
      const payload = client.template('Test')
        .retryStrategy('linear')
        .toJSON();

      expect(payload.retry_strategy).toBe('linear');
    });
  });

  describe('placeholders', () => {
    it('adds a single placeholder', () => {
      const payload = client.template('Test')
        .placeholder('user_id', true, 'The user ID')
        .toJSON();

      expect(payload.placeholders).toEqual([
        { name: 'user_id', required: true, description: 'The user ID' },
      ]);
    });

    it('adds placeholder with default value', () => {
      const payload = client.template('Test')
        .placeholder('action', false, 'The action', 'default_action')
        .toJSON();

      expect(payload.placeholders).toEqual([
        { name: 'action', required: false, description: 'The action', default: 'default_action' },
      ]);
    });

    it('adds multiple placeholders', () => {
      const payload = client.template('Test')
        .placeholder('user_id', true, 'User ID')
        .placeholder('action', false, 'Action', 'process')
        .toJSON();

      expect((payload.placeholders as unknown[]).length).toBe(2);
    });

    it('placeholders() sets all at once', () => {
      const payload = client.template('Test')
        .placeholders([
          { name: 'user_id', required: true },
          { name: 'action', required: false, default: 'test' },
        ])
        .toJSON();

      expect(payload.placeholders).toEqual([
        { name: 'user_id', required: true },
        { name: 'action', required: false, default: 'test' },
      ]);
    });
  });

  describe('chain template config', () => {
    it('sets chain_steps', () => {
      const steps = [
        { type: 'http_call', name: 'Step 1', url: 'https://a.com', method: 'POST' },
        { type: 'delay', name: 'Wait', delay: '1h' },
      ];

      const payload = client.template('Test')
        .chainSteps(steps)
        .toJSON();

      expect(payload.chain_steps).toEqual(steps);
    });

    it('sets chain_error_handling', () => {
      const payload = client.template('Test')
        .chainErrorHandling('skip_step')
        .toJSON();

      expect(payload.chain_error_handling).toBe('skip_step');
    });
  });

  describe('coordination config', () => {
    it('sets coordination keys', () => {
      const payload = client.template('Test')
        .coordinationKeys(['user_id', 'order_id'])
        .toJSON();

      expect(payload.default_coordination_keys).toEqual(['user_id', 'order_id']);
    });

    it('sets coordination config', () => {
      const payload = client.template('Test')
        .coordinationConfig({ strategy: 'replace' })
        .toJSON();

      expect(payload.coordination_config).toEqual({ strategy: 'replace' });
    });
  });

  describe('full payload example', () => {
    it('builds a complete template payload', () => {
      const payload = client.template('Process Order')
        .description('Template for order processing')
        .type('action')
        .mode('immediate')
        .timezone('UTC')
        .requestConfig({
          url: 'https://api.example.com/process',
          method: 'POST',
          body: {
            order_id: '{{order_id}}',
            amount: '{{amount}}',
          },
        })
        .maxAttempts(3)
        .retryStrategy('exponential')
        .placeholder('order_id', true, 'The order ID')
        .placeholder('amount', false, 'Order amount', '0.00')
        .toJSON();

      expect(payload).toEqual({
        name: 'Process Order',
        description: 'Template for order processing',
        type: 'action',
        mode: 'immediate',
        timezone: 'UTC',
        request_config: {
          url: 'https://api.example.com/process',
          method: 'POST',
          body: {
            order_id: '{{order_id}}',
            amount: '{{amount}}',
          },
        },
        max_attempts: 3,
        retry_strategy: 'exponential',
        placeholders: [
          { name: 'order_id', required: true, description: 'The order ID' },
          { name: 'amount', required: false, description: 'Order amount', default: '0.00' },
        ],
      });
    });
  });

  describe('send() / create() / update()', () => {
    it('send() creates template via API', async () => {
      fetchSpy.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({ data: { id: 't_123', name: 'Test', trigger_token: 'tok_abc' } }),
      });

      const result = await client.template('Test')
        .type('action')
        .send();

      expect(result.id).toBe('t_123');
      expect(fetchSpy).toHaveBeenCalledWith(
        expect.stringContaining('/templates'),
        expect.objectContaining({ method: 'POST' }),
      );
    });

    it('create() is an alias for send()', async () => {
      fetchSpy.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({ data: { id: 't_456' } }),
      });

      const result = await client.template('Test').create();
      expect(result.id).toBe('t_456');
    });

    it('update() sends PUT to template ID', async () => {
      fetchSpy.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({ data: { id: 't_123', name: 'Updated' } }),
      });

      const result = await client.template('Updated')
        .description('New description')
        .update('t_123');

      expect(result.name).toBe('Updated');
      expect(fetchSpy).toHaveBeenCalledWith(
        expect.stringContaining('/templates/t_123'),
        expect.objectContaining({ method: 'PUT' }),
      );
    });
  });
});
