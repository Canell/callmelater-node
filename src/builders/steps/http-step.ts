import type { ChainBuilder } from '../chain.js';

export class HttpStepBuilder {
  private _chain: ChainBuilder;
  private _name: string;
  private _url?: string;
  private _method: string = 'POST';
  private _headers: Record<string, string> = {};
  private _body: unknown = undefined;
  private _condition?: string;
  private _maxAttempts?: number;
  private _retryStrategy?: string;

  constructor(chain: ChainBuilder, name: string) {
    this._chain = chain;
    this._name = name;
  }

  url(url: string): this {
    this._url = url;
    return this;
  }

  method(m: string): this {
    this._method = m.toUpperCase();
    return this;
  }

  get(): this { return this.method('GET'); }
  post(): this { return this.method('POST'); }
  put(): this { return this.method('PUT'); }
  patch(): this { return this.method('PATCH'); }
  delete(): this { return this.method('DELETE'); }

  headers(obj: Record<string, string>): this {
    Object.assign(this._headers, obj);
    return this;
  }

  body(data: unknown): this {
    this._body = data;
    return this;
  }

  condition(expr: string): this {
    this._condition = expr;
    return this;
  }

  maxAttempts(max: number): this {
    this._maxAttempts = max;
    return this;
  }

  retryStrategy(strategy: string): this {
    this._retryStrategy = strategy;
    return this;
  }

  toJSON(): Record<string, unknown> {
    const step: Record<string, unknown> = {
      type: 'http_call',
      name: this._name,
      url: this._url,
      method: this._method,
    };

    if (Object.keys(this._headers).length > 0) {
      step.headers = this._headers;
    }

    if (this._body !== undefined) {
      step.body = this._body;
    }

    if (this._condition !== undefined) {
      step.condition = this._condition;
    }

    if (this._maxAttempts !== undefined) {
      step.max_attempts = this._maxAttempts;
    }

    if (this._retryStrategy !== undefined) {
      step.retry_strategy = this._retryStrategy;
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
