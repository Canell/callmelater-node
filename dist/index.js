// src/builders/http-action.ts
var PRESETS = [
  "tomorrow",
  "next_week",
  "next_monday",
  "next_tuesday",
  "next_wednesday",
  "next_thursday",
  "next_friday",
  "end_of_day",
  "end_of_week",
  "end_of_month"
];
var UNIT_MAP = {
  minutes: "m",
  hours: "h",
  days: "d",
  weeks: "w"
};
var HttpActionBuilder = class {
  _client;
  _url;
  _method = "POST";
  _headers = {};
  _payload = void 0;
  _name;
  _idempotencyKey;
  _timezone;
  _intent = {};
  _retry = {};
  _callbackUrl;
  _metadata = {};
  constructor(client, url) {
    this._client = client;
    this._url = url;
    this._timezone = client.getTimezone() ?? void 0;
    this._retry = { ...client.getRetryConfig() };
  }
  method(m) {
    this._method = m.toUpperCase();
    return this;
  }
  get() {
    return this.method("GET");
  }
  post() {
    return this.method("POST");
  }
  put() {
    return this.method("PUT");
  }
  patch() {
    return this.method("PATCH");
  }
  delete() {
    return this.method("DELETE");
  }
  headers(obj) {
    Object.assign(this._headers, obj);
    return this;
  }
  header(key, value) {
    this._headers[key] = value;
    return this;
  }
  payload(data) {
    this._payload = data;
    return this;
  }
  body(data) {
    return this.payload(data);
  }
  name(n) {
    this._name = n;
    return this;
  }
  idempotencyKey(key) {
    this._idempotencyKey = key;
    return this;
  }
  timezone(tz) {
    this._timezone = tz;
    return this;
  }
  at(time) {
    if (time instanceof Date) {
      this._intent = { type: "datetime", value: formatDate(time) };
    } else if (PRESETS.includes(time)) {
      this._intent = { type: "preset", value: time };
    } else {
      this._intent = { type: "datetime", value: time };
    }
    return this;
  }
  delay(amount, unit = "minutes") {
    this._intent = { type: "relative", value: amount, unit };
    return this;
  }
  inMinutes(n) {
    return this.delay(n, "minutes");
  }
  inHours(n) {
    return this.delay(n, "hours");
  }
  inDays(n) {
    return this.delay(n, "days");
  }
  retry(maxAttempts, backoff = "exponential", initialDelay = 60) {
    this._retry = { max_attempts: maxAttempts, backoff, initial_delay: initialDelay };
    return this;
  }
  noRetry() {
    this._retry = { max_attempts: 1 };
    return this;
  }
  callback(url) {
    this._callbackUrl = url;
    return this;
  }
  onComplete(url) {
    return this.callback(url);
  }
  metadata(obj) {
    Object.assign(this._metadata, obj);
    return this;
  }
  meta(key, value) {
    this._metadata[key] = value;
    return this;
  }
  toJSON() {
    const request = {
      url: this._url,
      method: this._method
    };
    if (Object.keys(this._headers).length > 0) {
      request.headers = this._headers;
    }
    if (this._payload !== void 0) {
      request.body = this._payload;
    }
    const payload = {
      mode: "immediate",
      request
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
        payload.intent.timezone = this._timezone;
      }
    }
    if (Object.keys(this._retry).length > 0) {
      if (this._retry.max_attempts !== void 0) {
        payload.max_attempts = this._retry.max_attempts;
      }
      if (this._retry.backoff !== void 0) {
        payload.retry_strategy = this._retry.backoff;
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
  async send() {
    return this._client.sendAction(this.toJSON());
  }
  async dispatch() {
    return this.send();
  }
  buildIntent() {
    const type = this._intent.type;
    if (type === "relative") {
      const value = this._intent.value;
      const unit = this._intent.unit ?? "minutes";
      const shortUnit = UNIT_MAP[unit] ?? unit;
      return { delay: `${value}${shortUnit}` };
    }
    if (type === "preset") {
      return { preset: this._intent.value };
    }
    if (type === "datetime") {
      return { at: this._intent.value };
    }
    return { ...this._intent };
  }
};
function formatDate(date) {
  const pad = (n) => String(n).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
}

// src/errors.ts
var CallMeLaterError = class extends Error {
  constructor(message) {
    super(message);
    this.name = "CallMeLaterError";
  }
};
var ApiError = class _ApiError extends CallMeLaterError {
  statusCode;
  validationErrors;
  responseBody;
  constructor(message, statusCode, validationErrors = {}, responseBody = "") {
    super(message);
    this.name = statusCode === 401 ? "AuthenticationError" : "ApiError";
    this.statusCode = statusCode;
    this.validationErrors = validationErrors;
    this.responseBody = responseBody;
  }
  get errorBag() {
    return this.validationErrors;
  }
  static async fromResponse(response, context = "API request") {
    const body = await response.text();
    let message = body;
    let errors = {};
    try {
      const json = JSON.parse(body);
      message = json.message ?? body;
      errors = json.errors ?? {};
    } catch {
    }
    return new _ApiError(
      `Failed to ${context}: ${message}`,
      response.status,
      errors,
      body
    );
  }
};
var ConfigurationError = class extends CallMeLaterError {
  constructor(message) {
    super(message);
    this.name = "ConfigurationError";
  }
};
var SignatureVerificationError = class extends CallMeLaterError {
  constructor(message) {
    super(message);
    this.name = "SignatureVerificationError";
  }
};

// src/builders/reminder.ts
var PRESETS2 = [
  "tomorrow",
  "next_week",
  "next_monday",
  "next_tuesday",
  "next_wednesday",
  "next_thursday",
  "next_friday",
  "end_of_day",
  "end_of_week",
  "end_of_month"
];
var UNIT_MAP2 = {
  minutes: "m",
  hours: "h",
  days: "d",
  weeks: "w"
};
var ReminderBuilder = class {
  _client;
  _name;
  _recipients = [];
  _message;
  _idempotencyKey;
  _timezone;
  _intent = {};
  _gate = {};
  _callbackUrl;
  _metadata = {};
  constructor(client, name) {
    this._client = client;
    this._name = name;
    this._timezone = client.getTimezone() ?? void 0;
  }
  to(email) {
    this._recipients.push(`email:${email}`);
    return this;
  }
  toMany(emails) {
    for (const email of emails) {
      this.to(email);
    }
    return this;
  }
  toPhone(phone) {
    this._recipients.push(`phone:${phone}`);
    return this;
  }
  toRecipient(recipientUri) {
    this._recipients.push(recipientUri);
    return this;
  }
  toChannel(channelUuid) {
    this._recipients.push(`channel:${channelUuid}`);
    return this;
  }
  message(msg) {
    this._message = msg;
    return this;
  }
  idempotencyKey(key) {
    this._idempotencyKey = key;
    return this;
  }
  timezone(tz) {
    this._timezone = tz;
    return this;
  }
  at(time) {
    if (time instanceof Date) {
      this._intent = { type: "datetime", value: formatDate2(time) };
    } else if (PRESETS2.includes(time)) {
      this._intent = { type: "preset", value: time };
    } else {
      this._intent = { type: "datetime", value: time };
    }
    return this;
  }
  delay(amount, unit = "minutes") {
    this._intent = { type: "relative", value: amount, unit };
    return this;
  }
  inMinutes(n) {
    return this.delay(n, "minutes");
  }
  inHours(n) {
    return this.delay(n, "hours");
  }
  inDays(n) {
    return this.delay(n, "days");
  }
  confirmButton(text) {
    this._gate.confirm_text = text;
    return this;
  }
  declineButton(text) {
    this._gate.decline_text = text;
    return this;
  }
  buttons(confirm, decline) {
    this._gate.confirm_text = confirm;
    this._gate.decline_text = decline;
    return this;
  }
  allowSnooze(maxSnoozes = 5) {
    this._gate.max_snoozes = maxSnoozes;
    return this;
  }
  noSnooze() {
    this._gate.max_snoozes = 0;
    return this;
  }
  expiresInDays(days) {
    this._gate.token_expiry_days = days;
    return this;
  }
  requireAll() {
    this._gate.confirmation_mode = "all_required";
    return this;
  }
  firstResponse() {
    this._gate.confirmation_mode = "first_response";
    return this;
  }
  escalateTo(contacts, afterHours = 24) {
    this._gate.escalation = {
      contacts: contacts.map((c) => c.includes(":") ? c : `email:${c}`),
      after_hours: afterHours
    };
    return this;
  }
  attach(url, name) {
    if (!this._gate.attachments) {
      this._gate.attachments = [];
    }
    const attachment = { url };
    if (name) {
      attachment.name = name;
    }
    this._gate.attachments.push(attachment);
    return this;
  }
  callback(url) {
    this._callbackUrl = url;
    return this;
  }
  onResponse(url) {
    return this.callback(url);
  }
  metadata(obj) {
    Object.assign(this._metadata, obj);
    return this;
  }
  meta(key, value) {
    this._metadata[key] = value;
    return this;
  }
  toJSON() {
    if (this._recipients.length === 0) {
      throw new CallMeLaterError("At least one recipient is required");
    }
    const gate = {
      recipients: this._recipients
    };
    if (this._message) {
      gate.message = this._message;
    }
    Object.assign(gate, this._gate);
    const payload = {
      mode: "gated",
      name: this._name,
      gate
    };
    if (this._idempotencyKey) {
      payload.idempotency_key = this._idempotencyKey;
    }
    if (Object.keys(this._intent).length > 0) {
      payload.intent = this.buildIntent();
      if (this._timezone) {
        payload.intent.timezone = this._timezone;
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
  async send() {
    return this._client.sendAction(this.toJSON());
  }
  async dispatch() {
    return this.send();
  }
  buildIntent() {
    const type = this._intent.type;
    if (type === "relative") {
      const value = this._intent.value;
      const unit = this._intent.unit ?? "minutes";
      const shortUnit = UNIT_MAP2[unit] ?? unit;
      return { delay: `${value}${shortUnit}` };
    }
    if (type === "preset") {
      return { preset: this._intent.value };
    }
    if (type === "datetime") {
      return { at: this._intent.value };
    }
    return { ...this._intent };
  }
};
function formatDate2(date) {
  const pad = (n) => String(n).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
}

// src/builders/steps/http-step.ts
var HttpStepBuilder = class {
  _chain;
  _name;
  _url;
  _method = "POST";
  _headers = {};
  _body = void 0;
  _condition;
  _maxAttempts;
  _retryStrategy;
  constructor(chain, name) {
    this._chain = chain;
    this._name = name;
  }
  url(url) {
    this._url = url;
    return this;
  }
  method(m) {
    this._method = m.toUpperCase();
    return this;
  }
  get() {
    return this.method("GET");
  }
  post() {
    return this.method("POST");
  }
  put() {
    return this.method("PUT");
  }
  patch() {
    return this.method("PATCH");
  }
  delete() {
    return this.method("DELETE");
  }
  headers(obj) {
    Object.assign(this._headers, obj);
    return this;
  }
  body(data) {
    this._body = data;
    return this;
  }
  condition(expr) {
    this._condition = expr;
    return this;
  }
  maxAttempts(max) {
    this._maxAttempts = max;
    return this;
  }
  retryStrategy(strategy) {
    this._retryStrategy = strategy;
    return this;
  }
  toJSON() {
    const step = {
      type: "http_call",
      name: this._name,
      url: this._url,
      method: this._method
    };
    if (Object.keys(this._headers).length > 0) {
      step.headers = this._headers;
    }
    if (this._body !== void 0) {
      step.body = this._body;
    }
    if (this._condition !== void 0) {
      step.condition = this._condition;
    }
    if (this._maxAttempts !== void 0) {
      step.max_attempts = this._maxAttempts;
    }
    if (this._retryStrategy !== void 0) {
      step.retry_strategy = this._retryStrategy;
    }
    return step;
  }
  done() {
    this._chain.pushStep(this.toJSON());
    return this._chain;
  }
  add() {
    return this.done();
  }
};

// src/builders/steps/gate-step.ts
var GateStepBuilder = class {
  _chain;
  _name;
  _message;
  _recipients = [];
  _maxSnoozes;
  _confirmationMode;
  _timeout;
  _onTimeout;
  _condition;
  constructor(chain, name) {
    this._chain = chain;
    this._name = name;
  }
  message(msg) {
    this._message = msg;
    return this;
  }
  to(email) {
    this._recipients.push(`email:${email}`);
    return this;
  }
  toMany(emails) {
    for (const email of emails) {
      this.to(email);
    }
    return this;
  }
  toRecipient(recipientUri) {
    this._recipients.push(recipientUri);
    return this;
  }
  maxSnoozes(max) {
    this._maxSnoozes = max;
    return this;
  }
  requireAll() {
    this._confirmationMode = "all_required";
    return this;
  }
  firstResponse() {
    this._confirmationMode = "first_response";
    return this;
  }
  timeout(timeout) {
    this._timeout = timeout;
    return this;
  }
  onTimeout(action) {
    this._onTimeout = action;
    return this;
  }
  condition(expr) {
    this._condition = expr;
    return this;
  }
  toJSON() {
    const gate = {};
    if (this._message !== void 0) {
      gate.message = this._message;
    }
    if (this._recipients.length > 0) {
      gate.recipients = this._recipients;
    }
    if (this._maxSnoozes !== void 0) {
      gate.max_snoozes = this._maxSnoozes;
    }
    if (this._confirmationMode !== void 0) {
      gate.confirmation_mode = this._confirmationMode;
    }
    if (this._timeout !== void 0) {
      gate.timeout = this._timeout;
    }
    if (this._onTimeout !== void 0) {
      gate.on_timeout = this._onTimeout;
    }
    const step = {
      type: "gated",
      name: this._name,
      gate
    };
    if (this._condition !== void 0) {
      step.condition = this._condition;
    }
    return step;
  }
  done() {
    this._chain.pushStep(this.toJSON());
    return this._chain;
  }
  add() {
    return this.done();
  }
};

// src/builders/steps/delay-step.ts
var DelayStepBuilder = class {
  _chain;
  _name;
  _duration;
  _condition;
  constructor(chain, name) {
    this._chain = chain;
    this._name = name;
  }
  duration(d) {
    this._duration = d;
    return this;
  }
  minutes(n) {
    this._duration = `${n}m`;
    return this;
  }
  hours(n) {
    this._duration = `${n}h`;
    return this;
  }
  days(n) {
    this._duration = `${n}d`;
    return this;
  }
  condition(expr) {
    this._condition = expr;
    return this;
  }
  toJSON() {
    const step = {
      type: "delay",
      name: this._name
    };
    if (this._duration !== void 0) {
      step.delay = this._duration;
    }
    if (this._condition !== void 0) {
      step.condition = this._condition;
    }
    return step;
  }
  done() {
    this._chain.pushStep(this.toJSON());
    return this._chain;
  }
  add() {
    return this.done();
  }
};

// src/builders/chain.ts
var ChainBuilder = class {
  _client;
  _name;
  _input = {};
  _steps = [];
  _errorHandling;
  constructor(client, name) {
    this._client = client;
    this._name = name;
  }
  input(data) {
    this._input = data;
    return this;
  }
  errorHandling(strategy) {
    this._errorHandling = strategy;
    return this;
  }
  addHttpStep(name) {
    return new HttpStepBuilder(this, name);
  }
  addGateStep(name) {
    return new GateStepBuilder(this, name);
  }
  addDelayStep(name) {
    return new DelayStepBuilder(this, name);
  }
  pushStep(step) {
    this._steps.push(step);
  }
  toJSON() {
    const payload = {
      name: this._name,
      steps: this._steps
    };
    if (Object.keys(this._input).length > 0) {
      payload.input = this._input;
    }
    if (this._errorHandling !== void 0) {
      payload.error_handling = this._errorHandling;
    }
    return payload;
  }
  async send() {
    return this._client.sendChain(this.toJSON());
  }
};

// src/builders/template.ts
var TemplateBuilder = class {
  _client;
  _name;
  _description;
  _type;
  _mode;
  _timezone;
  _requestConfig;
  _gateConfig;
  _maxAttempts;
  _retryStrategy;
  _placeholders = [];
  _chainSteps;
  _chainErrorHandling;
  _coordinationKeys;
  _coordinationConfig;
  constructor(client, name) {
    this._client = client;
    this._name = name;
  }
  description(desc) {
    this._description = desc;
    return this;
  }
  type(t) {
    this._type = t;
    return this;
  }
  mode(m) {
    this._mode = m;
    return this;
  }
  timezone(tz) {
    this._timezone = tz;
    return this;
  }
  requestConfig(config) {
    this._requestConfig = config;
    return this;
  }
  gateConfig(config) {
    this._gateConfig = config;
    return this;
  }
  maxAttempts(max) {
    this._maxAttempts = max;
    return this;
  }
  retryStrategy(strategy) {
    this._retryStrategy = strategy;
    return this;
  }
  placeholder(key, required = false, description, defaultValue) {
    const p = { name: key, required };
    if (description !== void 0) {
      p.description = description;
    }
    if (defaultValue !== void 0) {
      p.default = defaultValue;
    }
    this._placeholders.push(p);
    return this;
  }
  placeholders(arr) {
    this._placeholders = arr;
    return this;
  }
  chainSteps(steps) {
    this._chainSteps = steps;
    return this;
  }
  chainErrorHandling(strategy) {
    this._chainErrorHandling = strategy;
    return this;
  }
  coordinationKeys(keys) {
    this._coordinationKeys = keys;
    return this;
  }
  coordinationConfig(config) {
    this._coordinationConfig = config;
    return this;
  }
  toJSON() {
    const payload = {
      name: this._name
    };
    if (this._description !== void 0) {
      payload.description = this._description;
    }
    if (this._type !== void 0) {
      payload.type = this._type;
    }
    if (this._mode !== void 0) {
      payload.mode = this._mode;
    }
    if (this._timezone !== void 0) {
      payload.timezone = this._timezone;
    }
    if (this._requestConfig !== void 0) {
      payload.request_config = this._requestConfig;
    }
    if (this._gateConfig !== void 0) {
      payload.gate_config = this._gateConfig;
    }
    if (this._maxAttempts !== void 0) {
      payload.max_attempts = this._maxAttempts;
    }
    if (this._retryStrategy !== void 0) {
      payload.retry_strategy = this._retryStrategy;
    }
    if (this._placeholders.length > 0) {
      payload.placeholders = this._placeholders;
    }
    if (this._chainSteps !== void 0) {
      payload.chain_steps = this._chainSteps;
    }
    if (this._chainErrorHandling !== void 0) {
      payload.chain_error_handling = this._chainErrorHandling;
    }
    if (this._coordinationKeys !== void 0) {
      payload.default_coordination_keys = this._coordinationKeys;
    }
    if (this._coordinationConfig !== void 0) {
      payload.coordination_config = this._coordinationConfig;
    }
    return payload;
  }
  async send() {
    return this._client.sendTemplate(this.toJSON());
  }
  async create() {
    return this.send();
  }
  async update(id) {
    return this._client.updateTemplate(id, this.toJSON());
  }
};

// src/webhook.ts
import { createHmac, timingSafeEqual } from "crypto";
import { EventEmitter } from "events";
var WebhookHandler = class extends EventEmitter {
  _webhookSecret;
  _verifySignature = true;
  constructor(options) {
    super();
    this._webhookSecret = options.webhookSecret;
  }
  skipVerification() {
    this._verifySignature = false;
    return this;
  }
  handle(body, signature) {
    const rawBody = typeof body === "string" ? body : JSON.stringify(body);
    const payload = typeof body === "string" ? JSON.parse(body) : body;
    if (this._verifySignature) {
      this.verifySignature(rawBody, signature);
    }
    const event = payload.event ?? "";
    const result = {
      event,
      action_id: payload.action_id,
      action_name: payload.action_name,
      payload
    };
    if (event) {
      this.emit(event, result);
    }
    return result;
  }
  verifySignature(body, signature) {
    if (!this._webhookSecret) {
      throw new ConfigurationError(
        "Webhook secret not configured. Pass webhookSecret in the constructor options."
      );
    }
    if (!signature) {
      throw new SignatureVerificationError("Missing webhook signature header");
    }
    const expectedSignature = "sha256=" + createHmac("sha256", this._webhookSecret).update(body).digest("hex");
    const sigBuffer = Buffer.from(signature);
    const expectedBuffer = Buffer.from(expectedSignature);
    if (sigBuffer.length !== expectedBuffer.length || !timingSafeEqual(sigBuffer, expectedBuffer)) {
      throw new SignatureVerificationError("Invalid webhook signature");
    }
  }
  isValidSignature(body, signature) {
    try {
      this.verifySignature(body, signature);
      return true;
    } catch {
      return false;
    }
  }
};

// src/client.ts
var CallMeLater = class {
  _apiToken;
  _apiUrl;
  _webhookSecret;
  _timezone;
  _retryConfig;
  constructor(config) {
    if (!config.apiToken) {
      throw new ConfigurationError("API token is required");
    }
    this._apiToken = config.apiToken;
    this._apiUrl = (config.apiUrl ?? "https://callmelater.io").replace(/\/+$/, "");
    this._webhookSecret = config.webhookSecret;
    this._timezone = config.timezone;
    this._retryConfig = {};
    if (config.retry) {
      if (config.retry.maxAttempts !== void 0) {
        this._retryConfig.max_attempts = config.retry.maxAttempts;
      }
      if (config.retry.backoff !== void 0) {
        this._retryConfig.backoff = config.retry.backoff;
      }
      if (config.retry.initialDelay !== void 0) {
        this._retryConfig.initial_delay = config.retry.initialDelay;
      }
    }
  }
  getTimezone() {
    return this._timezone;
  }
  getRetryConfig() {
    return { ...this._retryConfig };
  }
  // ── Builders ───────────────────────────────────────────
  http(url) {
    return new HttpActionBuilder(this, url);
  }
  reminder(name) {
    return new ReminderBuilder(this, name);
  }
  chain(name) {
    return new ChainBuilder(this, name);
  }
  template(name) {
    return new TemplateBuilder(this, name);
  }
  // ── Actions CRUD ───────────────────────────────────────
  async getAction(id) {
    const response = await this.request("GET", `/api/v1/actions/${id}`);
    return this.extractData(response);
  }
  async listActions(filters = {}) {
    const params = new URLSearchParams(filters);
    const query = params.toString();
    const path = query ? `/api/v1/actions?${query}` : "/api/v1/actions";
    const response = await this.request("GET", path);
    return this.extractJson(response);
  }
  async cancelAction(id) {
    const response = await this.request("DELETE", `/api/v1/actions/${id}`);
    return this.extractJson(response) ?? {};
  }
  // ── Chains CRUD ────────────────────────────────────────
  async getChain(id) {
    const response = await this.request("GET", `/api/v1/chains/${id}`);
    return this.extractData(response);
  }
  async listChains(filters = {}) {
    const params = new URLSearchParams(filters);
    const query = params.toString();
    const path = query ? `/api/v1/chains?${query}` : "/api/v1/chains";
    const response = await this.request("GET", path);
    return this.extractJson(response);
  }
  async cancelChain(id) {
    const response = await this.request("DELETE", `/api/v1/chains/${id}`);
    return this.extractJson(response) ?? {};
  }
  // ── Templates CRUD ─────────────────────────────────────
  async getTemplate(id) {
    const response = await this.request("GET", `/api/v1/templates/${id}`);
    return this.extractData(response);
  }
  async listTemplates(filters = {}) {
    const params = new URLSearchParams(filters);
    const query = params.toString();
    const path = query ? `/api/v1/templates?${query}` : "/api/v1/templates";
    const response = await this.request("GET", path);
    return this.extractJson(response);
  }
  async deleteTemplate(id) {
    const response = await this.request("DELETE", `/api/v1/templates/${id}`);
    return this.extractJson(response) ?? {};
  }
  async toggleTemplate(id) {
    const response = await this.request("POST", `/api/v1/templates/${id}/toggle-active`);
    return this.extractData(response);
  }
  async regenerateTemplateToken(id) {
    const response = await this.request("POST", `/api/v1/templates/${id}/regenerate-token`);
    return this.extractData(response);
  }
  async templateLimits() {
    const response = await this.request("GET", "/api/v1/templates/limits");
    const json = await response.json();
    return json.data ?? json ?? {};
  }
  // ── Trigger ────────────────────────────────────────────
  async trigger(token, params = {}) {
    const response = await this.request("POST", `/t/${token}`, params);
    return this.extractData(response);
  }
  // ── Webhooks ───────────────────────────────────────────
  webhooks() {
    return new WebhookHandler({ webhookSecret: this._webhookSecret });
  }
  // ── Internal (used by builders) ────────────────────────
  async sendAction(payload) {
    const response = await this.request("POST", "/api/v1/actions", payload);
    return this.extractData(response);
  }
  async sendChain(payload) {
    const response = await this.request("POST", "/api/v1/chains", payload);
    return this.extractData(response);
  }
  async sendTemplate(payload) {
    const response = await this.request("POST", "/api/v1/templates", payload);
    return this.extractData(response);
  }
  async updateTemplate(id, payload) {
    const response = await this.request("PUT", `/api/v1/templates/${id}`, payload);
    return this.extractData(response);
  }
  // ── HTTP helpers ───────────────────────────────────────
  async request(method, path, body) {
    const url = `${this._apiUrl}${path}`;
    const headers = {
      "Authorization": `Bearer ${this._apiToken}`,
      "Accept": "application/json"
    };
    const init = {
      method,
      headers
    };
    if (body !== void 0) {
      headers["Content-Type"] = "application/json";
      init.body = JSON.stringify(body);
    }
    const response = await fetch(url, init);
    if (!response.ok) {
      throw await ApiError.fromResponse(response, `${method} ${path}`);
    }
    return response;
  }
  async extractData(response) {
    const json = await response.json();
    return json.data ?? json;
  }
  async extractJson(response) {
    return await response.json();
  }
};
export {
  ApiError,
  CallMeLater,
  CallMeLaterError,
  ChainBuilder,
  ConfigurationError,
  DelayStepBuilder,
  GateStepBuilder,
  HttpActionBuilder,
  HttpStepBuilder,
  ReminderBuilder,
  SignatureVerificationError,
  TemplateBuilder,
  WebhookHandler
};
//# sourceMappingURL=index.js.map