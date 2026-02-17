import type { CallMeLater } from '../client.js';
import { CallMeLaterError } from '../errors.js';

const PRESETS = [
  'tomorrow', 'next_week', 'next_monday', 'next_tuesday',
  'next_wednesday', 'next_thursday', 'next_friday', 'end_of_day',
  'end_of_week', 'end_of_month',
] as const;

const UNIT_MAP: Record<string, string> = {
  minutes: 'm',
  hours: 'h',
  days: 'd',
  weeks: 'w',
};

export class ReminderBuilder {
  private _client: CallMeLater;
  private _name: string;
  private _recipients: string[] = [];
  private _message?: string;
  private _idempotencyKey?: string;
  private _timezone?: string;
  private _intent: Record<string, unknown> = {};
  private _gate: Record<string, unknown> = {};
  private _callbackUrl?: string;
  private _metadata: Record<string, unknown> = {};

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

  confirmButton(text: string): this {
    this._gate.confirm_text = text;
    return this;
  }

  declineButton(text: string): this {
    this._gate.decline_text = text;
    return this;
  }

  buttons(confirm: string, decline: string): this {
    this._gate.confirm_text = confirm;
    this._gate.decline_text = decline;
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

  expiresInDays(days: number): this {
    this._gate.token_expiry_days = days;
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

  metadata(obj: Record<string, unknown>): this {
    Object.assign(this._metadata, obj);
    return this;
  }

  meta(key: string, value: unknown): this {
    this._metadata[key] = value;
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

    if (this._idempotencyKey) {
      payload.idempotency_key = this._idempotencyKey;
    }

    if (Object.keys(this._intent).length > 0) {
      payload.intent = this.buildIntent();
      if (this._timezone) {
        (payload.intent as Record<string, unknown>).timezone = this._timezone;
      }
    }

    if (this._callbackUrl) {
      payload.callback_url = this._callbackUrl;
    }

    if (Object.keys(this._metadata).length > 0) {
      payload.metadata = this._metadata;
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
