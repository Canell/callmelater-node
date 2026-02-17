export class CallMeLaterError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'CallMeLaterError';
  }
}

export class ApiError extends CallMeLaterError {
  public readonly statusCode: number;
  public readonly validationErrors: Record<string, string[]>;
  public readonly responseBody: string;

  constructor(
    message: string,
    statusCode: number,
    validationErrors: Record<string, string[]> = {},
    responseBody: string = '',
  ) {
    super(message);
    this.name = statusCode === 401 ? 'AuthenticationError' : 'ApiError';
    this.statusCode = statusCode;
    this.validationErrors = validationErrors;
    this.responseBody = responseBody;
  }

  get errorBag(): Record<string, string[]> {
    return this.validationErrors;
  }

  static async fromResponse(response: Response, context: string = 'API request'): Promise<ApiError> {
    const body = await response.text();
    let message: string = body;
    let errors: Record<string, string[]> = {};

    try {
      const json = JSON.parse(body);
      message = json.message ?? body;
      errors = json.errors ?? {};
    } catch {
      // Body is not JSON
    }

    return new ApiError(
      `Failed to ${context}: ${message}`,
      response.status,
      errors,
      body,
    );
  }
}

export class ConfigurationError extends CallMeLaterError {
  constructor(message: string) {
    super(message);
    this.name = 'ConfigurationError';
  }
}

export class SignatureVerificationError extends CallMeLaterError {
  constructor(message: string) {
    super(message);
    this.name = 'SignatureVerificationError';
  }
}
