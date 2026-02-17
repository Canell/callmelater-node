import { createHmac, timingSafeEqual } from 'node:crypto';
import { EventEmitter } from 'node:events';
import { ConfigurationError, SignatureVerificationError } from './errors.js';

export interface WebhookEvent {
  event: string;
  action_id?: string;
  action_name?: string;
  payload: Record<string, unknown>;
}

export interface WebhookHandlerOptions {
  webhookSecret?: string;
}

export class WebhookHandler extends EventEmitter {
  private _webhookSecret?: string;
  private _verifySignature: boolean = true;

  constructor(options: WebhookHandlerOptions) {
    super();
    this._webhookSecret = options.webhookSecret;
  }

  skipVerification(): this {
    this._verifySignature = false;
    return this;
  }

  handle(body: string | Record<string, unknown>, signature?: string): WebhookEvent {
    const rawBody = typeof body === 'string' ? body : JSON.stringify(body);
    const payload = typeof body === 'string' ? JSON.parse(body) as Record<string, unknown> : body;

    if (this._verifySignature) {
      this.verifySignature(rawBody, signature);
    }

    const event = (payload.event as string) ?? '';

    const result: WebhookEvent = {
      event,
      action_id: payload.action_id as string | undefined,
      action_name: payload.action_name as string | undefined,
      payload,
    };

    if (event) {
      this.emit(event, result);
    }

    return result;
  }

  verifySignature(body: string, signature?: string): void {
    if (!this._webhookSecret) {
      throw new ConfigurationError(
        'Webhook secret not configured. Pass webhookSecret in the constructor options.',
      );
    }

    if (!signature) {
      throw new SignatureVerificationError('Missing webhook signature header');
    }

    const expectedSignature = 'sha256=' + createHmac('sha256', this._webhookSecret).update(body).digest('hex');

    const sigBuffer = Buffer.from(signature);
    const expectedBuffer = Buffer.from(expectedSignature);

    if (sigBuffer.length !== expectedBuffer.length || !timingSafeEqual(sigBuffer, expectedBuffer)) {
      throw new SignatureVerificationError('Invalid webhook signature');
    }
  }

  isValidSignature(body: string, signature?: string): boolean {
    try {
      this.verifySignature(body, signature);
      return true;
    } catch {
      return false;
    }
  }
}
