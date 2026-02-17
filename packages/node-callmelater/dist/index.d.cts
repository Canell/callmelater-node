import { EventEmitter } from 'node:events';

declare class HttpActionBuilder {
    private _client;
    private _url;
    private _method;
    private _headers;
    private _payload;
    private _name?;
    private _idempotencyKey?;
    private _timezone?;
    private _intent;
    private _retry;
    private _callbackUrl?;
    private _metadata;
    constructor(client: CallMeLater, url: string);
    method(m: string): this;
    get(): this;
    post(): this;
    put(): this;
    patch(): this;
    delete(): this;
    headers(obj: Record<string, string>): this;
    header(key: string, value: string): this;
    payload(data: unknown): this;
    body(data: unknown): this;
    name(n: string): this;
    idempotencyKey(key: string): this;
    timezone(tz: string): this;
    at(time: string | Date): this;
    delay(amount: number, unit?: string): this;
    inMinutes(n: number): this;
    inHours(n: number): this;
    inDays(n: number): this;
    retry(maxAttempts: number, backoff?: string, initialDelay?: number): this;
    noRetry(): this;
    callback(url: string): this;
    onComplete(url: string): this;
    metadata(obj: Record<string, unknown>): this;
    meta(key: string, value: unknown): this;
    toJSON(): Record<string, unknown>;
    send(): Promise<Record<string, unknown>>;
    dispatch(): Promise<Record<string, unknown>>;
    private buildIntent;
}

declare class ReminderBuilder {
    private _client;
    private _name;
    private _recipients;
    private _message?;
    private _idempotencyKey?;
    private _timezone?;
    private _intent;
    private _gate;
    private _callbackUrl?;
    private _metadata;
    constructor(client: CallMeLater, name: string);
    to(email: string): this;
    toMany(emails: string[]): this;
    toPhone(phone: string): this;
    toRecipient(recipientUri: string): this;
    toChannel(channelUuid: string): this;
    message(msg: string): this;
    idempotencyKey(key: string): this;
    timezone(tz: string): this;
    at(time: string | Date): this;
    delay(amount: number, unit?: string): this;
    inMinutes(n: number): this;
    inHours(n: number): this;
    inDays(n: number): this;
    confirmButton(text: string): this;
    declineButton(text: string): this;
    buttons(confirm: string, decline: string): this;
    allowSnooze(maxSnoozes?: number): this;
    noSnooze(): this;
    expiresInDays(days: number): this;
    requireAll(): this;
    firstResponse(): this;
    escalateTo(contacts: string[], afterHours?: number): this;
    attach(url: string, name?: string): this;
    callback(url: string): this;
    onResponse(url: string): this;
    metadata(obj: Record<string, unknown>): this;
    meta(key: string, value: unknown): this;
    toJSON(): Record<string, unknown>;
    send(): Promise<Record<string, unknown>>;
    dispatch(): Promise<Record<string, unknown>>;
    private buildIntent;
}

declare class HttpStepBuilder {
    private _chain;
    private _name;
    private _url?;
    private _method;
    private _headers;
    private _body;
    private _condition?;
    private _maxAttempts?;
    private _retryStrategy?;
    constructor(chain: ChainBuilder, name: string);
    url(url: string): this;
    method(m: string): this;
    get(): this;
    post(): this;
    put(): this;
    patch(): this;
    delete(): this;
    headers(obj: Record<string, string>): this;
    body(data: unknown): this;
    condition(expr: string): this;
    maxAttempts(max: number): this;
    retryStrategy(strategy: string): this;
    toJSON(): Record<string, unknown>;
    done(): ChainBuilder;
    add(): ChainBuilder;
}

declare class GateStepBuilder {
    private _chain;
    private _name;
    private _message?;
    private _recipients;
    private _maxSnoozes?;
    private _confirmationMode?;
    private _timeout?;
    private _onTimeout?;
    private _condition?;
    constructor(chain: ChainBuilder, name: string);
    message(msg: string): this;
    to(email: string): this;
    toMany(emails: string[]): this;
    toRecipient(recipientUri: string): this;
    maxSnoozes(max: number): this;
    requireAll(): this;
    firstResponse(): this;
    timeout(timeout: string): this;
    onTimeout(action: string): this;
    condition(expr: string): this;
    toJSON(): Record<string, unknown>;
    done(): ChainBuilder;
    add(): ChainBuilder;
}

declare class DelayStepBuilder {
    private _chain;
    private _name;
    private _duration?;
    private _condition?;
    constructor(chain: ChainBuilder, name: string);
    duration(d: string): this;
    minutes(n: number): this;
    hours(n: number): this;
    days(n: number): this;
    condition(expr: string): this;
    toJSON(): Record<string, unknown>;
    done(): ChainBuilder;
    add(): ChainBuilder;
}

declare class ChainBuilder {
    private _client;
    private _name;
    private _input;
    private _steps;
    private _errorHandling?;
    constructor(client: CallMeLater, name: string);
    input(data: Record<string, unknown>): this;
    errorHandling(strategy: string): this;
    addHttpStep(name: string): HttpStepBuilder;
    addGateStep(name: string): GateStepBuilder;
    addDelayStep(name: string): DelayStepBuilder;
    pushStep(step: Record<string, unknown>): void;
    toJSON(): Record<string, unknown>;
    send(): Promise<Record<string, unknown>>;
}

interface Placeholder {
    name: string;
    required?: boolean;
    description?: string;
    default?: unknown;
}
declare class TemplateBuilder {
    private _client;
    private _name;
    private _description?;
    private _type?;
    private _mode?;
    private _timezone?;
    private _requestConfig?;
    private _gateConfig?;
    private _maxAttempts?;
    private _retryStrategy?;
    private _placeholders;
    private _chainSteps?;
    private _chainErrorHandling?;
    private _coordinationKeys?;
    private _coordinationConfig?;
    constructor(client: CallMeLater, name: string);
    description(desc: string): this;
    type(t: string): this;
    mode(m: string): this;
    timezone(tz: string): this;
    requestConfig(config: Record<string, unknown>): this;
    gateConfig(config: Record<string, unknown>): this;
    maxAttempts(max: number): this;
    retryStrategy(strategy: string): this;
    placeholder(key: string, required?: boolean, description?: string, defaultValue?: unknown): this;
    placeholders(arr: Placeholder[]): this;
    chainSteps(steps: Record<string, unknown>[]): this;
    chainErrorHandling(strategy: string): this;
    coordinationKeys(keys: string[]): this;
    coordinationConfig(config: Record<string, unknown>): this;
    toJSON(): Record<string, unknown>;
    send(): Promise<Record<string, unknown>>;
    create(): Promise<Record<string, unknown>>;
    update(id: string): Promise<Record<string, unknown>>;
}

interface WebhookEvent {
    event: string;
    action_id?: string;
    action_name?: string;
    payload: Record<string, unknown>;
}
interface WebhookHandlerOptions {
    webhookSecret?: string;
}
declare class WebhookHandler extends EventEmitter {
    private _webhookSecret?;
    private _verifySignature;
    constructor(options: WebhookHandlerOptions);
    skipVerification(): this;
    handle(body: string | Record<string, unknown>, signature?: string): WebhookEvent;
    verifySignature(body: string, signature?: string): void;
    isValidSignature(body: string, signature?: string): boolean;
}

interface CallMeLaterConfig {
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
declare class CallMeLater {
    private _apiToken;
    private _apiUrl;
    private _webhookSecret?;
    private _timezone?;
    private _retryConfig;
    constructor(config: CallMeLaterConfig);
    getTimezone(): string | undefined;
    getRetryConfig(): Record<string, unknown>;
    http(url: string): HttpActionBuilder;
    reminder(name: string): ReminderBuilder;
    chain(name: string): ChainBuilder;
    template(name: string): TemplateBuilder;
    getAction(id: string): Promise<Record<string, unknown>>;
    listActions(filters?: Record<string, string>): Promise<Record<string, unknown>>;
    cancelAction(id: string): Promise<Record<string, unknown>>;
    getChain(id: string): Promise<Record<string, unknown>>;
    listChains(filters?: Record<string, string>): Promise<Record<string, unknown>>;
    cancelChain(id: string): Promise<Record<string, unknown>>;
    getTemplate(id: string): Promise<Record<string, unknown>>;
    listTemplates(filters?: Record<string, string>): Promise<Record<string, unknown>>;
    deleteTemplate(id: string): Promise<Record<string, unknown>>;
    toggleTemplate(id: string): Promise<Record<string, unknown>>;
    regenerateTemplateToken(id: string): Promise<Record<string, unknown>>;
    templateLimits(): Promise<Record<string, unknown>>;
    trigger(token: string, params?: Record<string, unknown>): Promise<Record<string, unknown>>;
    webhooks(): WebhookHandler;
    sendAction(payload: Record<string, unknown>): Promise<Record<string, unknown>>;
    sendChain(payload: Record<string, unknown>): Promise<Record<string, unknown>>;
    sendTemplate(payload: Record<string, unknown>): Promise<Record<string, unknown>>;
    updateTemplate(id: string, payload: Record<string, unknown>): Promise<Record<string, unknown>>;
    private request;
    private extractData;
    private extractJson;
}

declare class CallMeLaterError extends Error {
    constructor(message: string);
}
declare class ApiError extends CallMeLaterError {
    readonly statusCode: number;
    readonly validationErrors: Record<string, string[]>;
    readonly responseBody: string;
    constructor(message: string, statusCode: number, validationErrors?: Record<string, string[]>, responseBody?: string);
    get errorBag(): Record<string, string[]>;
    static fromResponse(response: Response, context?: string): Promise<ApiError>;
}
declare class ConfigurationError extends CallMeLaterError {
    constructor(message: string);
}
declare class SignatureVerificationError extends CallMeLaterError {
    constructor(message: string);
}

export { ApiError, CallMeLater, type CallMeLaterConfig, CallMeLaterError, ChainBuilder, ConfigurationError, DelayStepBuilder, GateStepBuilder, HttpActionBuilder, HttpStepBuilder, type Placeholder, ReminderBuilder, SignatureVerificationError, TemplateBuilder, type WebhookEvent, WebhookHandler, type WebhookHandlerOptions };
