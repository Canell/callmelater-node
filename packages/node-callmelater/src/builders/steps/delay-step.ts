import type { ChainBuilder } from '../chain.js';

export class DelayStepBuilder {
  private _chain: ChainBuilder;
  private _name: string;
  private _duration?: string;
  private _condition?: string;

  constructor(chain: ChainBuilder, name: string) {
    this._chain = chain;
    this._name = name;
  }

  duration(d: string): this {
    this._duration = d;
    return this;
  }

  minutes(n: number): this {
    this._duration = `${n}m`;
    return this;
  }

  hours(n: number): this {
    this._duration = `${n}h`;
    return this;
  }

  days(n: number): this {
    this._duration = `${n}d`;
    return this;
  }

  condition(expr: string): this {
    this._condition = expr;
    return this;
  }

  toJSON(): Record<string, unknown> {
    const step: Record<string, unknown> = {
      type: 'delay',
      name: this._name,
    };

    if (this._duration !== undefined) {
      step.delay = this._duration;
    }

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
