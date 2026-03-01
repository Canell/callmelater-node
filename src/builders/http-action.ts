import type { CallMeLater } from '../client.js';

const PRESETS = [
  'tomorrow', 'next_week', 'next_monday', 'next_tuesday',
  'next_wednesday', 'next_thursday', 'next_friday', 'end_of_day',
  'end_of_week', 'end_of_month',
] as const;

const UNIT_MAP: Record<string, string> = {
  minutes: 'm',
  minute: 'm',
  min: 'm',
  hours: 'h',
  hour: 'h',
  days: 'd',
  day: 'd',
  weeks: 'w',
  week: 'w',
  months: 'M',
  month: 'M',
};

export class HttpActionBuilder {
  private _client: CallMeLater;
  private _url: string;
  private _method: string = 'POST';
  private _headers: Record<string, string> = {};
  private _payload: unknown = undefined;
  private _name?: string;
  private _idempotencyKey?: string;
  private _timezone?: string;
  private _intent: Record<string, unknown> = {};
  private _retry: Record<string, unknown> = {};
  private _callbackUrl?: string;
  private _metadata: Record<string, unknown> = {};
  private _recurrence: Record<string, unknown> = {};

  constructor(client: CallMeLater, url: string) {
    this._client = client;
    this._url = url;
    this._timezone = client.getTimezone() ?? undefined;
    this._retry = { ...client.getRetryConfig() };
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

  header(key: string, value: string): this {
    this._headers[key] = value;
    return this;
  }

  payload(data: unknown): this {
    this._payload = data;
    return this;
  }

  body(data: unknown): this {
    return this.payload(data);
  }

  name(n: string): this {
    this._name = n;
    return this;
  }

  idempotencyKey(key: string): this {
    this._idempotencyKey = key;
    return this;
  }

  timezone(tz: string): this {
    this._timezone = tz;
    return this;
  }

  at(time: string | Date): this {
    if (time instanceof Date) {
      this._intent = { type: 'datetime', value: formatDate(time) };
    } else if ((PRESETS as readonly string[]).includes(time)) {
      this._intent = { type: 'preset', value: time };
    } else {
      this._intent = { type: 'datetime', value: time };
    }
    return this;
  }

  delay(amount: number, unit: string = 'minutes'): this {
    this._intent = { type: 'relative', value: amount, unit };
    return this;
  }

  inMinutes(n: number): this { return this.delay(n, 'minutes'); }
  inHours(n: number): this { return this.delay(n, 'hours'); }
  inDays(n: number): this { return this.delay(n, 'days'); }

  retry(maxAttempts: number, backoff: string = 'exponential', initialDelay: number = 60): this {
    this._retry = { max_attempts: maxAttempts, backoff, initial_delay: initialDelay };
    return this;
  }

  noRetry(): this {
    this._retry = { max_attempts: 1 };
    return this;
  }

  callback(url: string): this {
    this._callbackUrl = url;
    return this;
  }

  onComplete(url: string): this {
    return this.callback(url);
  }

  metadata(obj: Record<string, unknown>): this {
    Object.assign(this._metadata, obj);
    return this;
  }

  meta(key: string, value: unknown): this {
    this._metadata[key] = value;
    return this;
  }

  /** Enable recurrence with a frequency and unit. */
  repeat(frequency: number, unit: string): this {
    this._recurrence.frequency = frequency;
    this._recurrence.unit = UNIT_MAP[unit] ?? unit;
    if (!this._recurrence.end_type) {
      this._recurrence.end_type = 'never';
    }
    return this;
  }

  /** Alias for repeat(). */
  every(frequency: number, unit: string): this {
    return this.repeat(frequency, unit);
  }

  everyMinutes(n: number): this { return this.repeat(n, 'minutes'); }
  everyHours(n: number): this { return this.repeat(n, 'hours'); }
  everyDays(n: number): this { return this.repeat(n, 'days'); }
  everyWeeks(n: number): this { return this.repeat(n, 'weeks'); }
  everyMonths(n: number): this { return this.repeat(n, 'months'); }

  /** Set a maximum number of occurrences. */
  maxOccurrences(count: number): this {
    this._recurrence.end_type = 'count';
    this._recurrence.max_occurrences = count;
    return this;
  }

  /** Repeat until a specific date. */
  until(date: string | Date): this {
    this._recurrence.end_type = 'date';
    this._recurrence.end_date = date instanceof Date ? date.toISOString() : date;
    return this;
  }

  /** Repeat forever (no end condition). */
  repeatForever(): this {
    this._recurrence.end_type = 'never';
    return this;
  }

  toJSON(): Record<string, unknown> {
    const request: Record<string, unknown> = {
      url: this._url,
      method: this._method,
    };

    if (Object.keys(this._headers).length > 0) {
      request.headers = this._headers;
    }

    if (this._payload !== undefined) {
      request.body = this._payload;
    }

    const payload: Record<string, unknown> = {
      mode: 'immediate',
      request,
    };

    if (this._name) {
      payload.name = this._name;
    }

    if (this._idempotencyKey) {
      payload.idempotency_key = this._idempotencyKey;
    }

    if (Object.keys(this._intent).length > 0) {
      payload.intent = this.buildIntent();
      if (this._timezone) {
        (payload.intent as Record<string, unknown>).timezone = this._timezone;
      }
    }

    if (Object.keys(this._retry).length > 0) {
      if (this._retry.max_attempts !== undefined) {
        payload.max_attempts = this._retry.max_attempts;
      }
      if (this._retry.backoff !== undefined) {
        payload.retry_strategy = this._retry.backoff;
      }
    }

    if (this._callbackUrl) {
      payload.callback_url = this._callbackUrl;
    }

    if (Object.keys(this._metadata).length > 0) {
      payload.metadata = this._metadata;
    }

    if (Object.keys(this._recurrence).length > 0) {
      payload.recurrence = this._recurrence;
    }

    return payload;
  }

  async send(): Promise<Record<string, unknown>> {
    return this._client.sendAction(this.toJSON());
  }

  async dispatch(): Promise<Record<string, unknown>> {
    return this.send();
  }

  private buildIntent(): Record<string, unknown> {
    const type = this._intent.type as string | undefined;

    if (type === 'relative') {
      const value = this._intent.value as number;
      const unit = (this._intent.unit as string) ?? 'minutes';
      const shortUnit = UNIT_MAP[unit] ?? unit;
      return { delay: `${value}${shortUnit}` };
    }

    if (type === 'preset') {
      return { preset: this._intent.value as string };
    }

    if (type === 'datetime') {
      return { at: this._intent.value as string };
    }

    return { ...this._intent };
  }
}

function formatDate(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
}
