import type { CallMeLater } from '../client.js';
import { HttpStepBuilder } from './steps/http-step.js';
import { GateStepBuilder } from './steps/gate-step.js';
import { DelayStepBuilder } from './steps/delay-step.js';

export class ChainBuilder {
  private _client: CallMeLater;
  private _name: string;
  private _input: Record<string, unknown> = {};
  private _steps: Record<string, unknown>[] = [];
  private _errorHandling?: string;

  constructor(client: CallMeLater, name: string) {
    this._client = client;
    this._name = name;
  }

  input(data: Record<string, unknown>): this {
    this._input = data;
    return this;
  }

  errorHandling(strategy: string): this {
    this._errorHandling = strategy;
    return this;
  }

  addHttpStep(name: string): HttpStepBuilder {
    return new HttpStepBuilder(this, name);
  }

  addGateStep(name: string): GateStepBuilder {
    return new GateStepBuilder(this, name);
  }

  addDelayStep(name: string): DelayStepBuilder {
    return new DelayStepBuilder(this, name);
  }

  pushStep(step: Record<string, unknown>): void {
    this._steps.push(step);
  }

  toJSON(): Record<string, unknown> {
    const payload: Record<string, unknown> = {
      name: this._name,
      steps: this._steps,
    };

    if (Object.keys(this._input).length > 0) {
      payload.input = this._input;
    }

    if (this._errorHandling !== undefined) {
      payload.error_handling = this._errorHandling;
    }

    return payload;
  }

  async send(): Promise<Record<string, unknown>> {
    return this._client.sendChain(this.toJSON());
  }
}
