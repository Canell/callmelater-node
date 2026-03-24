import type { CallMeLater } from '../client.js';
import { CallMeLaterError } from '../errors.js';

const PRESETS = [
  'tomorrow', 'next_week',
  'next_monday', 'next_tuesday', 'next_wednesday', 'next_thursday',
  'next_friday', 'next_saturday', 'next_sunday',
  '1_hour', '1h', '2_hours', '2h', '4_hours', '4h',
  '1_day', '1d', '3_days', '3d', '1_week', '1w', '1_month', '1M',
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

export class ReminderBuilder {
  private _client: CallMeLater;
  private _name: string;
  private _description?: string;
  private _recipients: string[] = [];
  private _message?: string;
  private _idempotencyKey?: string;
  private _timezone?: string;
  private _intent: Record<string, unknown> = {};
  private _gate: Record<string, unknown> = {};
  private _callbackUrl?: string;
  private _recurrence: Record<string, unknown> = {};
  private _coordinationKeys?: string[];
  private _coordination?: Record<string, unknown>;
  private _notifyCreatorOnResponse?: boolean;

  constructor(client: CallMeLater, name: string) {
    this._client = client;
    this._name = name;
    this._timezone = client.getTimezone() ?? undefined;
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

  toPhone(phone: string): this {
    this._recipients.push(`phone:${phone}`);
    return this;
  }

  toRecipient(recipientUri: string): this {
    this._recipients.push(recipientUri);
    return this;
  }

  toChannel(channelUuid: string): this {
    this._recipients.push(`channel:${channelUuid}`);
    return this;
  }

  message(msg: string): this {
    this._message = msg;
    return this;
  }

  description(desc: string): this {
    this._description = desc;
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
      this._intent = { type: 'execute_at', value: time.toISOString() };
    } else if ((PRESETS as readonly string[]).includes(time)) {
      this._intent = { type: 'preset', value: time };
    } else if (/^\d{2}:\d{2}(:\d{2})?$/.test(time)) {
      this._intent = { type: 'time', value: time };
    } else {
      // Full datetime string → use execute_at
      this._intent = { type: 'execute_at', value: time };
    }
    return this;
  }

  /** Schedule at a specific time, optionally on a specific date. */
  atTime(time: string, on?: string): this {
    this._intent = { type: 'time', value: time, on };
    return this;
  }

  delay(amount: number, unit: string = 'minutes'): this {
    this._intent = { type: 'relative', value: amount, unit };
    return this;
  }

  inMinutes(n: number): this { return this.delay(n, 'minutes'); }
  inHours(n: number): this { return this.delay(n, 'hours'); }
  inDays(n: number): this { return this.delay(n, 'days'); }

  /** Set notification channels (e.g., 'email', 'sms', 'teams', 'slack', 'push'). */
  channels(channels: string[]): this {
    this._gate.channels = channels;
    return this;
  }

  /** Set integration IDs for Teams/Slack connections. */
  integrationIds(ids: string[]): this {
    this._gate.integration_ids = ids;
    return this;
  }

  allowSnooze(maxSnoozes: number = 5): this {
    this._gate.max_snoozes = maxSnoozes;
    return this;
  }

  noSnooze(): this {
    this._gate.max_snoozes = 0;
    return this;
  }

  /** Set the gate timeout (e.g., '4h', '7d', '1w'). */
  timeout(duration: string): this {
    this._gate.timeout = duration;
    return this;
  }

  /** Set what happens when the gate times out ('cancel', 'expire', or 'approve'). */
  onTimeout(action: string): this {
    this._gate.on_timeout = action;
    return this;
  }

  requireAll(): this {
    this._gate.confirmation_mode = 'all_required';
    return this;
  }

  firstResponse(): this {
    this._gate.confirmation_mode = 'first_response';
    return this;
  }

  escalateTo(contacts: string[], afterHours: number = 24): this {
    this._gate.escalation = {
      contacts: contacts.map(c => c.includes(':') ? c : `email:${c}`),
      after_hours: afterHours,
    };
    return this;
  }

  attach(url: string, name?: string): this {
    if (!this._gate.attachments) {
      this._gate.attachments = [];
    }
    const attachment: Record<string, string> = { url };
    if (name) {
      attachment.name = name;
    }
    (this._gate.attachments as Record<string, string>[]).push(attachment);
    return this;
  }

  callback(url: string): this {
    this._callbackUrl = url;
    return this;
  }

  onResponse(url: string): this {
    return this.callback(url);
  }

  notifyCreatorOnResponse(notify: boolean = true): this {
    this._notifyCreatorOnResponse = notify;
    return this;
  }

  coordinationKeys(keys: string[]): this {
    this._coordinationKeys = keys;
    return this;
  }

  coordination(config: Record<string, unknown>): this {
    this._coordination = config;
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
    if (this._recipients.length === 0) {
      throw new CallMeLaterError('At least one recipient is required');
    }

    const gate: Record<string, unknown> = {
      recipients: this._recipients,
    };

    if (this._message) {
      gate.message = this._message;
    }

    // Merge additional gate options
    Object.assign(gate, this._gate);

    const payload: Record<string, unknown> = {
      mode: 'gated',
      name: this._name,
      gate,
    };

    if (this._description) {
      payload.description = this._description;
    }

    if (this._idempotencyKey) {
      payload.idempotency_key = this._idempotencyKey;
    }

    if (this._timezone) {
      payload.timezone = this._timezone;
    }

    if (Object.keys(this._intent).length > 0) {
      const intentType = this._intent.type as string | undefined;

      if (intentType === 'execute_at') {
        payload.execute_at = this._intent.value;
      } else {
        payload.intent = this.buildIntent();
      }
    }

    if (this._callbackUrl) {
      payload.callback_url = this._callbackUrl;
    }

    if (this._notifyCreatorOnResponse !== undefined) {
      payload.notify_creator_on_response = this._notifyCreatorOnResponse;
    }

    if (this._coordinationKeys) {
      payload.coordination_keys = this._coordinationKeys;
    }

    if (this._coordination) {
      payload.coordination = this._coordination;
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

    if (type === 'time') {
      const intent: Record<string, unknown> = { at: this._intent.value as string };
      if (this._intent.on) {
        intent.on = this._intent.on as string;
      }
      return intent;
    }

    return { ...this._intent };
  }
}
