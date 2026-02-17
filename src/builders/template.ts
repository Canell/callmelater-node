import type { CallMeLater } from '../client.js';

export interface Placeholder {
  name: string;
  required?: boolean;
  description?: string;
  default?: unknown;
}

export class TemplateBuilder {
  private _client: CallMeLater;
  private _name: string;
  private _description?: string;
  private _type?: string;
  private _mode?: string;
  private _timezone?: string;
  private _requestConfig?: Record<string, unknown>;
  private _gateConfig?: Record<string, unknown>;
  private _maxAttempts?: number;
  private _retryStrategy?: string;
  private _placeholders: Placeholder[] = [];
  private _chainSteps?: Record<string, unknown>[];
  private _chainErrorHandling?: string;
  private _coordinationKeys?: string[];
  private _coordinationConfig?: Record<string, unknown>;

  constructor(client: CallMeLater, name: string) {
    this._client = client;
    this._name = name;
  }

  description(desc: string): this {
    this._description = desc;
    return this;
  }

  type(t: string): this {
    this._type = t;
    return this;
  }

  mode(m: string): this {
    this._mode = m;
    return this;
  }

  timezone(tz: string): this {
    this._timezone = tz;
    return this;
  }

  requestConfig(config: Record<string, unknown>): this {
    this._requestConfig = config;
    return this;
  }

  gateConfig(config: Record<string, unknown>): this {
    this._gateConfig = config;
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

  placeholder(key: string, required: boolean = false, description?: string, defaultValue?: unknown): this {
    const p: Placeholder = { name: key, required };
    if (description !== undefined) {
      p.description = description;
    }
    if (defaultValue !== undefined) {
      p.default = defaultValue;
    }
    this._placeholders.push(p);
    return this;
  }

  placeholders(arr: Placeholder[]): this {
    this._placeholders = arr;
    return this;
  }

  chainSteps(steps: Record<string, unknown>[]): this {
    this._chainSteps = steps;
    return this;
  }

  chainErrorHandling(strategy: string): this {
    this._chainErrorHandling = strategy;
    return this;
  }

  coordinationKeys(keys: string[]): this {
    this._coordinationKeys = keys;
    return this;
  }

  coordinationConfig(config: Record<string, unknown>): this {
    this._coordinationConfig = config;
    return this;
  }

  toJSON(): Record<string, unknown> {
    const payload: Record<string, unknown> = {
      name: this._name,
    };

    if (this._description !== undefined) {
      payload.description = this._description;
    }

    if (this._type !== undefined) {
      payload.type = this._type;
    }

    if (this._mode !== undefined) {
      payload.mode = this._mode;
    }

    if (this._timezone !== undefined) {
      payload.timezone = this._timezone;
    }

    if (this._requestConfig !== undefined) {
      payload.request_config = this._requestConfig;
    }

    if (this._gateConfig !== undefined) {
      payload.gate_config = this._gateConfig;
    }

    if (this._maxAttempts !== undefined) {
      payload.max_attempts = this._maxAttempts;
    }

    if (this._retryStrategy !== undefined) {
      payload.retry_strategy = this._retryStrategy;
    }

    if (this._placeholders.length > 0) {
      payload.placeholders = this._placeholders;
    }

    if (this._chainSteps !== undefined) {
      payload.chain_steps = this._chainSteps;
    }

    if (this._chainErrorHandling !== undefined) {
      payload.chain_error_handling = this._chainErrorHandling;
    }

    if (this._coordinationKeys !== undefined) {
      payload.default_coordination_keys = this._coordinationKeys;
    }

    if (this._coordinationConfig !== undefined) {
      payload.coordination_config = this._coordinationConfig;
    }

    return payload;
  }

  async send(): Promise<Record<string, unknown>> {
    return this._client.sendTemplate(this.toJSON());
  }

  async create(): Promise<Record<string, unknown>> {
    return this.send();
  }

  async update(id: string): Promise<Record<string, unknown>> {
    return this._client.updateTemplate(id, this.toJSON());
  }
}
