import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { CallMeLater } from '../src/index.js';

describe('ChainBuilder', () => {
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
    it('creates minimal chain with name and steps', () => {
      const payload = client.chain('My Chain').toJSON();
      expect(payload).toEqual({ name: 'My Chain', steps: [] });
    });

    it('sets input data', () => {
      const payload = client.chain('Test')
        .input({ user_id: 123, action: 'process' })
        .toJSON();

      expect(payload.input).toEqual({ user_id: 123, action: 'process' });
    });

    it('sets error handling strategy', () => {
      const payload = client.chain('Test')
        .errorHandling('skip_step')
        .toJSON();

      expect(payload.error_handling).toBe('skip_step');
    });
  });

  describe('HTTP steps', () => {
    it('adds an HTTP step', () => {
      const payload = client.chain('Test')
        .addHttpStep('Step 1')
          .url('https://example.com/api')
          .post()
          .body({ key: 'value' })
          .done()
        .toJSON();

      expect(payload.steps).toEqual([
        {
          type: 'http_call',
          name: 'Step 1',
          url: 'https://example.com/api',
          method: 'POST',
          body: { key: 'value' },
        },
      ]);
    });

    it('HTTP step supports all methods', () => {
      const chain = client.chain('Test');

      const getStep = chain.addHttpStep('GET').url('https://a.com').get().toJSON();
      expect(getStep.method).toBe('GET');

      const putStep = chain.addHttpStep('PUT').url('https://a.com').put().toJSON();
      expect(putStep.method).toBe('PUT');

      const patchStep = chain.addHttpStep('PATCH').url('https://a.com').patch().toJSON();
      expect(patchStep.method).toBe('PATCH');

      const deleteStep = chain.addHttpStep('DELETE').url('https://a.com').delete().toJSON();
      expect(deleteStep.method).toBe('DELETE');
    });

    it('HTTP step supports headers', () => {
      const payload = client.chain('Test')
        .addHttpStep('Step 1')
          .url('https://example.com')
          .headers({ 'X-Api-Key': 'abc' })
          .done()
        .toJSON();

      const step = (payload.steps as Record<string, unknown>[])[0];
      expect(step.headers).toEqual({ 'X-Api-Key': 'abc' });
    });

    it('HTTP step supports condition', () => {
      const payload = client.chain('Test')
        .addHttpStep('Step 2')
          .url('https://example.com')
          .condition('{{steps.1.response.action}} == confirmed')
          .done()
        .toJSON();

      const step = (payload.steps as Record<string, unknown>[])[0];
      expect(step.condition).toBe('{{steps.1.response.action}} == confirmed');
    });

    it('HTTP step supports retry config', () => {
      const payload = client.chain('Test')
        .addHttpStep('Step 1')
          .url('https://example.com')
          .maxAttempts(5)
          .retryStrategy('exponential')
          .done()
        .toJSON();

      const step = (payload.steps as Record<string, unknown>[])[0];
      expect(step.max_attempts).toBe(5);
      expect(step.retry_strategy).toBe('exponential');
    });

    it('add() is an alias for done()', () => {
      const payload = client.chain('Test')
        .addHttpStep('Step 1')
          .url('https://example.com')
          .add()
        .toJSON();

      expect((payload.steps as unknown[]).length).toBe(1);
    });
  });

  describe('gate steps', () => {
    it('adds a gate step', () => {
      const payload = client.chain('Test')
        .addGateStep('Approval')
          .message('Please approve')
          .to('user@example.com')
          .done()
        .toJSON();

      expect(payload.steps).toEqual([
        {
          type: 'gated',
          name: 'Approval',
          gate: {
            message: 'Please approve',
            recipients: ['email:user@example.com'],
          },
        },
      ]);
    });

    it('gate step supports multiple recipients', () => {
      const payload = client.chain('Test')
        .addGateStep('Approval')
          .toMany(['alice@test.com', 'bob@test.com'])
          .done()
        .toJSON();

      const gate = ((payload.steps as Record<string, unknown>[])[0].gate as Record<string, unknown>);
      expect(gate.recipients).toEqual(['email:alice@test.com', 'email:bob@test.com']);
    });

    it('gate step supports raw recipient URI', () => {
      const payload = client.chain('Test')
        .addGateStep('Approval')
          .toRecipient('slack:#deploy')
          .done()
        .toJSON();

      const gate = ((payload.steps as Record<string, unknown>[])[0].gate as Record<string, unknown>);
      expect(gate.recipients).toEqual(['slack:#deploy']);
    });

    it('gate step supports max snoozes', () => {
      const payload = client.chain('Test')
        .addGateStep('Approval')
          .maxSnoozes(3)
          .done()
        .toJSON();

      const gate = ((payload.steps as Record<string, unknown>[])[0].gate as Record<string, unknown>);
      expect(gate.max_snoozes).toBe(3);
    });

    it('gate step supports confirmation modes', () => {
      let payload = client.chain('Test')
        .addGateStep('All')
          .requireAll()
          .done()
        .toJSON();

      let gate = ((payload.steps as Record<string, unknown>[])[0].gate as Record<string, unknown>);
      expect(gate.confirmation_mode).toBe('all_required');

      payload = client.chain('Test')
        .addGateStep('First')
          .firstResponse()
          .done()
        .toJSON();

      gate = ((payload.steps as Record<string, unknown>[])[0].gate as Record<string, unknown>);
      expect(gate.confirmation_mode).toBe('first_response');
    });

    it('gate step supports timeout', () => {
      const payload = client.chain('Test')
        .addGateStep('Approval')
          .timeout('2d')
          .onTimeout('continue')
          .done()
        .toJSON();

      const gate = ((payload.steps as Record<string, unknown>[])[0].gate as Record<string, unknown>);
      expect(gate.timeout).toBe('2d');
      expect(gate.on_timeout).toBe('continue');
    });

    it('gate step supports condition', () => {
      const payload = client.chain('Test')
        .addGateStep('Approval')
          .condition('{{steps.1.status}} == success')
          .done()
        .toJSON();

      const step = (payload.steps as Record<string, unknown>[])[0];
      expect(step.condition).toBe('{{steps.1.status}} == success');
    });
  });

  describe('delay steps', () => {
    it('adds a delay step with minutes', () => {
      const payload = client.chain('Test')
        .addDelayStep('Wait')
          .minutes(30)
          .done()
        .toJSON();

      expect(payload.steps).toEqual([
        { type: 'delay', name: 'Wait', delay: '30m' },
      ]);
    });

    it('delay step with hours', () => {
      const payload = client.chain('Test')
        .addDelayStep('Wait')
          .hours(2)
          .done()
        .toJSON();

      const step = (payload.steps as Record<string, unknown>[])[0];
      expect(step.delay).toBe('2h');
    });

    it('delay step with days', () => {
      const payload = client.chain('Test')
        .addDelayStep('Wait')
          .days(1)
          .done()
        .toJSON();

      const step = (payload.steps as Record<string, unknown>[])[0];
      expect(step.delay).toBe('1d');
    });

    it('delay step with raw duration', () => {
      const payload = client.chain('Test')
        .addDelayStep('Wait')
          .duration('45m')
          .done()
        .toJSON();

      const step = (payload.steps as Record<string, unknown>[])[0];
      expect(step.delay).toBe('45m');
    });

    it('delay step supports condition', () => {
      const payload = client.chain('Test')
        .addDelayStep('Wait')
          .minutes(5)
          .condition('{{steps.1.response.status}} == pending')
          .done()
        .toJSON();

      const step = (payload.steps as Record<string, unknown>[])[0];
      expect(step.condition).toBe('{{steps.1.response.status}} == pending');
    });
  });

  describe('multi-step chains', () => {
    it('builds a complete multi-step chain', () => {
      const payload = client.chain('Process Order')
        .input({ order_id: 42 })
        .errorHandling('fail_chain')
        .addHttpStep('Validate')
          .url('https://api.example.com/validate')
          .post()
          .body({ order_id: '{{input.order_id}}' })
          .done()
        .addGateStep('Approve')
          .message('Approve order #{{input.order_id}}?')
          .to('manager@example.com')
          .timeout('24h')
          .onTimeout('cancel')
          .done()
        .addDelayStep('Cooling Period')
          .hours(1)
          .done()
        .addHttpStep('Execute')
          .url('https://api.example.com/execute')
          .post()
          .body({ order_id: '{{input.order_id}}' })
          .condition('{{steps.2.response.action}} == confirmed')
          .done()
        .toJSON();

      expect(payload.name).toBe('Process Order');
      expect(payload.input).toEqual({ order_id: 42 });
      expect(payload.error_handling).toBe('fail_chain');
      expect((payload.steps as unknown[]).length).toBe(4);

      const steps = payload.steps as Record<string, unknown>[];
      expect(steps[0].type).toBe('http_call');
      expect(steps[1].type).toBe('gated');
      expect(steps[2].type).toBe('delay');
      expect(steps[3].type).toBe('http_call');
      expect(steps[3].condition).toBe('{{steps.2.response.action}} == confirmed');
    });
  });

  describe('send()', () => {
    it('sends chain to API', async () => {
      fetchSpy.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({ data: { id: 'ch_123', status: 'active' } }),
      });

      const result = await client.chain('Test')
        .addHttpStep('Step 1')
          .url('https://example.com')
          .done()
        .send();

      expect(result.id).toBe('ch_123');

      const sentBody = JSON.parse(fetchSpy.mock.calls[0][1].body);
      expect(sentBody.name).toBe('Test');
      expect(sentBody.steps.length).toBe(1);
    });
  });
});
