import { HttpActionBuilder } from './builders/http-action.js';
import { ReminderBuilder } from './builders/reminder.js';
import { ChainBuilder } from './builders/chain.js';
import { TemplateBuilder } from './builders/template.js';
import { WebhookHandler } from './webhook.js';
import { ApiError, ConfigurationError } from './errors.js';

export interface CallMeLaterConfig {
  apiToken: string;
  apiUrl?: string;
  webhookSecret?: string;
  timezone?: string;
  retry?: {
    maxAttempts?: number;
    backoff?: string;
    initialDelay?: number;
  };
}

export class CallMeLater {
  private _apiToken: string;
  private _apiUrl: string;
  private _webhookSecret?: string;
  private _timezone?: string;
  private _retryConfig: Record<string, unknown>;

  constructor(config: CallMeLaterConfig) {
    if (!config.apiToken) {
      throw new ConfigurationError('API token is required');
    }

    this._apiToken = config.apiToken;
    this._apiUrl = (config.apiUrl ?? 'https://callmelater.io').replace(/\/+$/, '');
    this._webhookSecret = config.webhookSecret;
    this._timezone = config.timezone;
    this._retryConfig = {};

    if (config.retry) {
      if (config.retry.maxAttempts !== undefined) {
        this._retryConfig.max_attempts = config.retry.maxAttempts;
      }
      if (config.retry.backoff !== undefined) {
        this._retryConfig.backoff = config.retry.backoff;
      }
      if (config.retry.initialDelay !== undefined) {
        this._retryConfig.initial_delay = config.retry.initialDelay;
      }
    }
  }

  getTimezone(): string | undefined {
    return this._timezone;
  }

  getRetryConfig(): Record<string, unknown> {
    return { ...this._retryConfig };
  }

  // ── Builders ───────────────────────────────────────────

  http(url: string): HttpActionBuilder {
    return new HttpActionBuilder(this, url);
  }

  reminder(name: string): ReminderBuilder {
    return new ReminderBuilder(this, name);
  }

  chain(name: string): ChainBuilder {
    return new ChainBuilder(this, name);
  }

  template(name: string): TemplateBuilder {
    return new TemplateBuilder(this, name);
  }

  // ── Actions CRUD ───────────────────────────────────────

  async getAction(id: string): Promise<Record<string, unknown>> {
    const response = await this.request('GET', `/api/v1/actions/${id}`);
    return this.extractData(response);
  }

  async listActions(filters: Record<string, string> = {}): Promise<Record<string, unknown>> {
    const params = new URLSearchParams(filters);
    const query = params.toString();
    const path = query ? `/api/v1/actions?${query}` : '/api/v1/actions';
    const response = await this.request('GET', path);
    return this.extractJson(response);
  }

  async cancelAction(id: string): Promise<Record<string, unknown>> {
    const response = await this.request('DELETE', `/api/v1/actions/${id}`);
    return this.extractJson(response) ?? {};
  }

  // ── Chains CRUD ────────────────────────────────────────

  async getChain(id: string): Promise<Record<string, unknown>> {
    const response = await this.request('GET', `/api/v1/chains/${id}`);
    return this.extractData(response);
  }

  async listChains(filters: Record<string, string> = {}): Promise<Record<string, unknown>> {
    const params = new URLSearchParams(filters);
    const query = params.toString();
    const path = query ? `/api/v1/chains?${query}` : '/api/v1/chains';
    const response = await this.request('GET', path);
    return this.extractJson(response);
  }

  async cancelChain(id: string): Promise<Record<string, unknown>> {
    const response = await this.request('DELETE', `/api/v1/chains/${id}`);
    return this.extractJson(response) ?? {};
  }

  // ── Templates CRUD ─────────────────────────────────────

  async getTemplate(id: string): Promise<Record<string, unknown>> {
    const response = await this.request('GET', `/api/v1/templates/${id}`);
    return this.extractData(response);
  }

  async listTemplates(filters: Record<string, string> = {}): Promise<Record<string, unknown>> {
    const params = new URLSearchParams(filters);
    const query = params.toString();
    const path = query ? `/api/v1/templates?${query}` : '/api/v1/templates';
    const response = await this.request('GET', path);
    return this.extractJson(response);
  }

  async deleteTemplate(id: string): Promise<Record<string, unknown>> {
    const response = await this.request('DELETE', `/api/v1/templates/${id}`);
    return this.extractJson(response) ?? {};
  }

  async toggleTemplate(id: string): Promise<Record<string, unknown>> {
    const response = await this.request('POST', `/api/v1/templates/${id}/toggle-active`);
    return this.extractData(response);
  }

  async regenerateTemplateToken(id: string): Promise<Record<string, unknown>> {
    const response = await this.request('POST', `/api/v1/templates/${id}/regenerate-token`);
    return this.extractData(response);
  }

  async templateLimits(): Promise<Record<string, unknown>> {
    const response = await this.request('GET', '/api/v1/templates/limits');
    const json = await response.json() as Record<string, unknown>;
    return (json.data as Record<string, unknown>) ?? json ?? {};
  }

  // ── Trigger ────────────────────────────────────────────

  async trigger(token: string, params: Record<string, unknown> = {}): Promise<Record<string, unknown>> {
    const response = await this.request('POST', `/t/${token}`, params);
    return this.extractData(response);
  }

  // ── Webhooks ───────────────────────────────────────────

  webhooks(): WebhookHandler {
    return new WebhookHandler({ webhookSecret: this._webhookSecret });
  }

  // ── Internal (used by builders) ────────────────────────

  async sendAction(payload: Record<string, unknown>): Promise<Record<string, unknown>> {
    const response = await this.request('POST', '/api/v1/actions', payload);
    return this.extractData(response);
  }

  async sendChain(payload: Record<string, unknown>): Promise<Record<string, unknown>> {
    const response = await this.request('POST', '/api/v1/chains', payload);
    return this.extractData(response);
  }

  async sendTemplate(payload: Record<string, unknown>): Promise<Record<string, unknown>> {
    const response = await this.request('POST', '/api/v1/templates', payload);
    return this.extractData(response);
  }

  async updateTemplate(id: string, payload: Record<string, unknown>): Promise<Record<string, unknown>> {
    const response = await this.request('PUT', `/api/v1/templates/${id}`, payload);
    return this.extractData(response);
  }

  // ── HTTP helpers ───────────────────────────────────────

  private async request(method: string, path: string, body?: unknown): Promise<Response> {
    const url = `${this._apiUrl}${path}`;

    const headers: Record<string, string> = {
      'Authorization': `Bearer ${this._apiToken}`,
      'Accept': 'application/json',
    };

    const init: RequestInit = {
      method,
      headers,
    };

    if (body !== undefined) {
      headers['Content-Type'] = 'application/json';
      init.body = JSON.stringify(body);
    }

    const response = await fetch(url, init);

    if (!response.ok) {
      throw await ApiError.fromResponse(response, `${method} ${path}`);
    }

    return response;
  }

  private async extractData(response: Response): Promise<Record<string, unknown>> {
    const json = await response.json() as Record<string, unknown>;
    return (json.data as Record<string, unknown>) ?? json;
  }

  private async extractJson(response: Response): Promise<Record<string, unknown>> {
    return await response.json() as Record<string, unknown>;
  }
}
