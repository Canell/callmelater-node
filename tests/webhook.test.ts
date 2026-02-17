import { describe, it, expect, vi } from 'vitest';
import { createHmac } from 'node:crypto';
import { WebhookHandler, ConfigurationError, SignatureVerificationError } from '../src/index.js';

function sign(body: string, secret: string): string {
  return 'sha256=' + createHmac('sha256', secret).update(body).digest('hex');
}

describe('WebhookHandler', () => {
  const secret = 'whsec_test_secret';

  describe('signature verification', () => {
    it('verifies valid signature', () => {
      const handler = new WebhookHandler({ webhookSecret: secret });
      const body = JSON.stringify({ event: 'action.executed' });
      const signature = sign(body, secret);

      expect(() => handler.verifySignature(body, signature)).not.toThrow();
    });

    it('rejects invalid signature', () => {
      const handler = new WebhookHandler({ webhookSecret: secret });
      const body = JSON.stringify({ event: 'action.executed' });

      expect(() => handler.verifySignature(body, 'sha256=invalid')).toThrow(SignatureVerificationError);
      expect(() => handler.verifySignature(body, 'sha256=invalid')).toThrow('Invalid webhook signature');
    });

    it('rejects missing signature', () => {
      const handler = new WebhookHandler({ webhookSecret: secret });
      const body = JSON.stringify({ event: 'action.executed' });

      expect(() => handler.verifySignature(body, undefined)).toThrow(SignatureVerificationError);
      expect(() => handler.verifySignature(body, undefined)).toThrow('Missing webhook signature');
    });

    it('throws ConfigurationError when no secret configured', () => {
      const handler = new WebhookHandler({});
      const body = JSON.stringify({ event: 'action.executed' });

      expect(() => handler.verifySignature(body, 'sha256=anything')).toThrow(ConfigurationError);
      expect(() => handler.verifySignature(body, 'sha256=anything')).toThrow('Webhook secret not configured');
    });

    it('isValidSignature returns true for valid', () => {
      const handler = new WebhookHandler({ webhookSecret: secret });
      const body = JSON.stringify({ event: 'action.executed' });
      const signature = sign(body, secret);

      expect(handler.isValidSignature(body, signature)).toBe(true);
    });

    it('isValidSignature returns false for invalid', () => {
      const handler = new WebhookHandler({ webhookSecret: secret });
      const body = JSON.stringify({ event: 'action.executed' });

      expect(handler.isValidSignature(body, 'sha256=bad')).toBe(false);
    });

    it('isValidSignature returns false when no secret', () => {
      const handler = new WebhookHandler({});
      expect(handler.isValidSignature('{}', 'sha256=anything')).toBe(false);
    });
  });

  describe('handle()', () => {
    it('parses webhook event from string body', () => {
      const handler = new WebhookHandler({ webhookSecret: secret });
      const body = JSON.stringify({
        event: 'action.executed',
        action_id: 'act_123',
        action_name: 'My Action',
        execution: { status: 'success' },
      });
      const signature = sign(body, secret);

      const result = handler.handle(body, signature);

      expect(result.event).toBe('action.executed');
      expect(result.action_id).toBe('act_123');
      expect(result.action_name).toBe('My Action');
      expect(result.payload.execution).toEqual({ status: 'success' });
    });

    it('parses webhook event from object body', () => {
      const handler = new WebhookHandler({ webhookSecret: secret });
      const payload = {
        event: 'action.failed',
        action_id: 'act_456',
        action_name: 'Failed Action',
      };
      const body = JSON.stringify(payload);
      const signature = sign(body, secret);

      const result = handler.handle(payload, signature);

      expect(result.event).toBe('action.failed');
      expect(result.action_id).toBe('act_456');
    });

    it('skips verification when configured', () => {
      const handler = new WebhookHandler({}).skipVerification();
      const payload = { event: 'action.executed', action_id: 'act_1' };

      const result = handler.handle(payload);

      expect(result.event).toBe('action.executed');
    });

    it('throws on invalid signature during handle', () => {
      const handler = new WebhookHandler({ webhookSecret: secret });
      const body = JSON.stringify({ event: 'action.executed' });

      expect(() => handler.handle(body, 'sha256=invalid')).toThrow(SignatureVerificationError);
    });
  });

  describe('event emitting', () => {
    it('emits action.executed event', () => {
      const handler = new WebhookHandler({ webhookSecret: secret });
      const listener = vi.fn();
      handler.on('action.executed', listener);

      const body = JSON.stringify({ event: 'action.executed', action_id: 'act_1' });
      const signature = sign(body, secret);

      handler.handle(body, signature);

      expect(listener).toHaveBeenCalledOnce();
      expect(listener).toHaveBeenCalledWith(expect.objectContaining({
        event: 'action.executed',
        action_id: 'act_1',
      }));
    });

    it('emits action.failed event', () => {
      const handler = new WebhookHandler({ webhookSecret: secret });
      const listener = vi.fn();
      handler.on('action.failed', listener);

      const body = JSON.stringify({ event: 'action.failed', action_id: 'act_2' });
      const signature = sign(body, secret);

      handler.handle(body, signature);

      expect(listener).toHaveBeenCalledOnce();
    });

    it('emits action.expired event', () => {
      const handler = new WebhookHandler({ webhookSecret: secret });
      const listener = vi.fn();
      handler.on('action.expired', listener);

      const body = JSON.stringify({ event: 'action.expired', action_id: 'act_3' });
      const signature = sign(body, secret);

      handler.handle(body, signature);

      expect(listener).toHaveBeenCalledOnce();
    });

    it('emits reminder.responded event', () => {
      const handler = new WebhookHandler({ webhookSecret: secret });
      const listener = vi.fn();
      handler.on('reminder.responded', listener);

      const body = JSON.stringify({
        event: 'reminder.responded',
        action_id: 'act_4',
        response: 'confirmed',
        responder_email: 'user@example.com',
      });
      const signature = sign(body, secret);

      handler.handle(body, signature);

      expect(listener).toHaveBeenCalledOnce();
      expect(listener).toHaveBeenCalledWith(expect.objectContaining({
        event: 'reminder.responded',
      }));
    });

    it('does not emit when event is empty', () => {
      const handler = new WebhookHandler({}).skipVerification();
      const listener = vi.fn();
      handler.on('', listener);

      handler.handle({ action_id: 'act_1' });

      expect(listener).not.toHaveBeenCalled();
    });

    it('supports multiple listeners on same event', () => {
      const handler = new WebhookHandler({ webhookSecret: secret });
      const listener1 = vi.fn();
      const listener2 = vi.fn();
      handler.on('action.executed', listener1);
      handler.on('action.executed', listener2);

      const body = JSON.stringify({ event: 'action.executed', action_id: 'act_1' });
      const signature = sign(body, secret);

      handler.handle(body, signature);

      expect(listener1).toHaveBeenCalledOnce();
      expect(listener2).toHaveBeenCalledOnce();
    });
  });
});
