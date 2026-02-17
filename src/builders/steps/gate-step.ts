import type { ChainBuilder } from '../chain.js';

export class GateStepBuilder {
  private _chain: ChainBuilder;
  private _name: string;
  private _message?: string;
  private _recipients: string[] = [];
  private _maxSnoozes?: number;
  private _confirmationMode?: string;
  private _timeout?: string;
  private _onTimeout?: string;
  private _condition?: string;

  constructor(chain: ChainBuilder, name: string) {
    this._chain = chain;
    this._name = name;
  }

  message(msg: string): this {
    this._message = msg;
    return this;
  }

  to(email: string): this {
    this._recipients.push(`email:${email}`);
    return this;
  }

  toMany(emails: string[]): this {
    for (const email of emails) {
      this.to(email);
    }
    return this;
  }

  toRecipient(recipientUri: string): this {
    this._recipients.push(recipientUri);
    return this;
  }

  maxSnoozes(max: number): this {
    this._maxSnoozes = max;
    return this;
  }

  requireAll(): this {
    this._confirmationMode = 'all_required';
    return this;
  }

  firstResponse(): this {
    this._confirmationMode = 'first_response';
    return this;
  }

  timeout(timeout: string): this {
    this._timeout = timeout;
    return this;
  }

  onTimeout(action: string): this {
    this._onTimeout = action;
    return this;
  }

  condition(expr: string): this {
    this._condition = expr;
    return this;
  }

  toJSON(): Record<string, unknown> {
    const gate: Record<string, unknown> = {};

    if (this._message !== undefined) {
      gate.message = this._message;
    }

    if (this._recipients.length > 0) {
      gate.recipients = this._recipients;
    }

    if (this._maxSnoozes !== undefined) {
      gate.max_snoozes = this._maxSnoozes;
    }

    if (this._confirmationMode !== undefined) {
      gate.confirmation_mode = this._confirmationMode;
    }

    if (this._timeout !== undefined) {
      gate.timeout = this._timeout;
    }

    if (this._onTimeout !== undefined) {
      gate.on_timeout = this._onTimeout;
    }

    const step: Record<string, unknown> = {
      type: 'gated',
      name: this._name,
      gate,
    };

    if (this._condition !== undefined) {
      step.condition = this._condition;
    }

    return step;
  }

  done(): ChainBuilder {
    this._chain.pushStep(this.toJSON());
    return this._chain;
  }

  add(): ChainBuilder {
    return this.done();
  }
}
