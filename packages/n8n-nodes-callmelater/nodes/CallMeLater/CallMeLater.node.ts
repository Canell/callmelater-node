import type {
	IExecuteFunctions,
	INodeExecutionData,
	INodeType,
	INodeTypeDescription,
	IDataObject,
	IHttpRequestMethods,
} from 'n8n-workflow';
import { NodeOperationError } from 'n8n-workflow';

export class CallMeLater implements INodeType {
	description: INodeTypeDescription = {
		displayName: 'CallMeLater',
		name: 'callMeLater',
		icon: 'file:callmelater.svg',
		group: ['transform'],
		version: 1,
		subtitle: '={{$parameter["operation"] + ": " + $parameter["resource"]}}',
		description: 'Schedule webhooks and human approvals with CallMeLater',
		defaults: {
			name: 'CallMeLater',
		},
		inputs: ['main'],
		outputs: ['main'],
		credentials: [
			{
				name: 'callMeLaterApi',
				required: true,
			},
		],
		properties: [
			// Resource selector
			{
				displayName: 'Resource',
				name: 'resource',
				type: 'options',
				noDataExpression: true,
				options: [
					{
						name: 'Action',
						value: 'action',
					},
				],
				default: 'action',
			},
			// Operation selector
			{
				displayName: 'Operation',
				name: 'operation',
				type: 'options',
				noDataExpression: true,
				displayOptions: {
					show: {
						resource: ['action'],
					},
				},
				options: [
					{
						name: 'Create Webhook',
						value: 'createWebhook',
						description: 'Schedule an HTTP request to be executed later',
						action: 'Create a scheduled webhook',
					},
					{
						name: 'Create Approval',
						value: 'createApproval',
						description: 'Send an approval request to recipients',
						action: 'Create an approval request',
					},
					{
						name: 'Get',
						value: 'get',
						description: 'Get details of a scheduled action',
						action: 'Get an action',
					},
					{
						name: 'Cancel',
						value: 'cancel',
						description: 'Cancel a scheduled action',
						action: 'Cancel an action',
					},
				],
				default: 'createWebhook',
			},

			// ==========================================
			// Create Webhook fields
			// ==========================================
			{
				displayName: 'Name',
				name: 'name',
				type: 'string',
				default: '',
				required: true,
				displayOptions: {
					show: {
						resource: ['action'],
						operation: ['createWebhook'],
					},
				},
				description: 'A name for this scheduled webhook',
			},
			{
				displayName: 'Schedule',
				name: 'schedule',
				type: 'string',
				default: '1h',
				required: true,
				displayOptions: {
					show: {
						resource: ['action'],
						operation: ['createWebhook'],
					},
				},
				placeholder: '1h, 3d, 2025-02-15T14:30:00Z',
				description: 'When to execute. Use relative delays (1h, 3d) or ISO datetime.',
			},
			{
				displayName: 'Webhook URL',
				name: 'webhookUrl',
				type: 'string',
				default: '',
				required: true,
				displayOptions: {
					show: {
						resource: ['action'],
						operation: ['createWebhook'],
					},
				},
				placeholder: 'https://api.example.com/webhook',
				description: 'The URL to call when the schedule fires',
			},
			{
				displayName: 'Method',
				name: 'method',
				type: 'options',
				options: [
					{ name: 'GET', value: 'GET' },
					{ name: 'POST', value: 'POST' },
					{ name: 'PUT', value: 'PUT' },
					{ name: 'PATCH', value: 'PATCH' },
					{ name: 'DELETE', value: 'DELETE' },
				],
				default: 'POST',
				displayOptions: {
					show: {
						resource: ['action'],
						operation: ['createWebhook'],
					},
				},
			},
			{
				displayName: 'Additional Options',
				name: 'webhookOptions',
				type: 'collection',
				placeholder: 'Add Option',
				default: {},
				displayOptions: {
					show: {
						resource: ['action'],
						operation: ['createWebhook'],
					},
				},
				options: [
					{
						displayName: 'Body (JSON)',
						name: 'body',
						type: 'json',
						default: '',
						description: 'JSON body to send with the request',
					},
					{
						displayName: 'Headers',
						name: 'headers',
						type: 'json',
						default: '',
						description: 'Custom headers as JSON object',
					},
					{
						displayName: 'Max Attempts',
						name: 'maxAttempts',
						type: 'number',
						default: 5,
						description: 'Maximum retry attempts on failure',
					},
					{
						displayName: 'Retry Strategy',
						name: 'retryStrategy',
						type: 'options',
						options: [
							{ name: 'Exponential Backoff', value: 'exponential' },
							{ name: 'Fixed Interval', value: 'fixed' },
						],
						default: 'exponential',
					},
					{
						displayName: 'Idempotency Key',
						name: 'idempotencyKey',
						type: 'string',
						default: '',
						description: 'Unique key to prevent duplicate actions',
					},
					{
						displayName: 'Callback URL',
						name: 'callbackUrl',
						type: 'string',
						default: '',
						description: 'URL to receive webhook on completion/failure',
					},
				],
			},

			// ==========================================
			// Create Approval fields
			// ==========================================
			{
				displayName: 'Name',
				name: 'approvalName',
				type: 'string',
				default: '',
				required: true,
				displayOptions: {
					show: {
						resource: ['action'],
						operation: ['createApproval'],
					},
				},
				description: 'A name for this approval request',
			},
			{
				displayName: 'Message',
				name: 'message',
				type: 'string',
				typeOptions: {
					rows: 4,
				},
				default: '',
				required: true,
				displayOptions: {
					show: {
						resource: ['action'],
						operation: ['createApproval'],
					},
				},
				placeholder: 'Please approve this request...',
				description: 'The message recipients will see',
			},
			{
				displayName: 'Recipients',
				name: 'recipients',
				type: 'string',
				default: '',
				required: true,
				displayOptions: {
					show: {
						resource: ['action'],
						operation: ['createApproval'],
					},
				},
				placeholder: 'email@example.com, +15551234567',
				description: 'Comma-separated list of email addresses or phone numbers',
			},
			{
				displayName: 'Channels',
				name: 'channels',
				type: 'multiOptions',
				options: [
					{ name: 'Email', value: 'email' },
					{ name: 'SMS', value: 'sms' },
					{ name: 'Microsoft Teams', value: 'teams' },
					{ name: 'Slack', value: 'slack' },
				],
				default: ['email'],
				displayOptions: {
					show: {
						resource: ['action'],
						operation: ['createApproval'],
					},
				},
				description: 'How to deliver the approval request',
			},
			{
				displayName: 'Schedule',
				name: 'approvalSchedule',
				type: 'string',
				default: '5m',
				displayOptions: {
					show: {
						resource: ['action'],
						operation: ['createApproval'],
					},
				},
				placeholder: '5m, 1h, now',
				description: 'When to send the approval request (default: 5 minutes)',
			},
			{
				displayName: 'Additional Options',
				name: 'approvalOptions',
				type: 'collection',
				placeholder: 'Add Option',
				default: {},
				displayOptions: {
					show: {
						resource: ['action'],
						operation: ['createApproval'],
					},
				},
				options: [
					{
						displayName: 'Timeout',
						name: 'timeout',
						type: 'string',
						default: '4h',
						description: 'How long to wait for a response (e.g., 4h, 1d)',
					},
					{
						displayName: 'On Timeout',
						name: 'onTimeout',
						type: 'options',
						options: [
							{ name: 'Expire', value: 'expire' },
							{ name: 'Cancel', value: 'cancel' },
							{ name: 'Auto-Approve', value: 'approve' },
						],
						default: 'expire',
						description: 'What to do if nobody responds',
					},
					{
						displayName: 'Max Snoozes',
						name: 'maxSnoozes',
						type: 'number',
						default: 5,
						description: 'Maximum times a recipient can snooze',
					},
					{
						displayName: 'Confirmation Mode',
						name: 'confirmationMode',
						type: 'options',
						options: [
							{ name: 'First Response', value: 'first_response' },
							{ name: 'All Required', value: 'all_required' },
						],
						default: 'first_response',
						description: 'Whether one response is enough or all must respond',
					},
					{
						displayName: 'Callback URL',
						name: 'callbackUrl',
						type: 'string',
						default: '',
						description: 'URL to receive webhook when someone responds',
					},
				],
			},

			// ==========================================
			// Get Action fields
			// ==========================================
			{
				displayName: 'Action ID',
				name: 'actionId',
				type: 'string',
				default: '',
				required: true,
				displayOptions: {
					show: {
						resource: ['action'],
						operation: ['get', 'cancel'],
					},
				},
				description: 'The ID of the action',
			},
		],
	};

	async execute(this: IExecuteFunctions): Promise<INodeExecutionData[][]> {
		const items = this.getInputData();
		const returnData: INodeExecutionData[] = [];

		const credentials = await this.getCredentials('callMeLaterApi');
		const apiUrl = credentials.apiUrl as string;

		for (let i = 0; i < items.length; i++) {
			try {
				const resource = this.getNodeParameter('resource', i) as string;
				const operation = this.getNodeParameter('operation', i) as string;

				let responseData: IDataObject;

				if (resource === 'action') {
					if (operation === 'createWebhook') {
						const name = this.getNodeParameter('name', i) as string;
						const schedule = this.getNodeParameter('schedule', i) as string;
						const webhookUrl = this.getNodeParameter('webhookUrl', i) as string;
						const method = this.getNodeParameter('method', i) as string;
						const options = this.getNodeParameter('webhookOptions', i) as IDataObject;

						const body: IDataObject = {
							name,
							mode: 'immediate',
							request: {
								url: webhookUrl,
								method,
							},
						};

						// Parse schedule
						if (schedule.match(/^\d+[mhdw]$/)) {
							body.schedule = { wait: schedule };
						} else {
							body.schedule = { at: schedule };
						}

						// Add optional request fields
						if (options.body) {
							(body.request as IDataObject).body = JSON.parse(options.body as string);
						}
						if (options.headers) {
							(body.request as IDataObject).headers = JSON.parse(options.headers as string);
						}
						if (options.maxAttempts) {
							body.max_attempts = options.maxAttempts;
						}
						if (options.retryStrategy) {
							body.retry_strategy = options.retryStrategy;
						}
						if (options.idempotencyKey) {
							body.idempotency_key = options.idempotencyKey;
						}
						if (options.callbackUrl) {
							body.callback_url = options.callbackUrl;
						}

						const response = await this.helpers.httpRequest({
							method: 'POST' as IHttpRequestMethods,
							url: `${apiUrl}/api/v1/actions`,
							body,
							json: true,
						});

						responseData = (response as IDataObject).data as IDataObject;

					} else if (operation === 'createApproval') {
						const name = this.getNodeParameter('approvalName', i) as string;
						const message = this.getNodeParameter('message', i) as string;
						const recipientsStr = this.getNodeParameter('recipients', i) as string;
						const channels = this.getNodeParameter('channels', i) as string[];
						const schedule = this.getNodeParameter('approvalSchedule', i) as string;
						const options = this.getNodeParameter('approvalOptions', i) as IDataObject;

						const recipients = recipientsStr.split(',').map((r) => r.trim()).filter((r) => r);

						const body: IDataObject = {
							name,
							mode: 'gated',
							gate: {
								message,
								recipients,
								channels,
								timeout: options.timeout || '4h',
								on_timeout: options.onTimeout || 'expire',
								max_snoozes: options.maxSnoozes ?? 5,
								confirmation_mode: options.confirmationMode || 'first_response',
							},
						};

						// Parse schedule
						if (schedule.match(/^\d+[mhdw]$/)) {
							body.schedule = { wait: schedule };
						} else if (schedule === 'now') {
							body.schedule = { wait: '0m' };
						} else {
							body.schedule = { at: schedule };
						}

						if (options.callbackUrl) {
							body.callback_url = options.callbackUrl;
						}

						const response = await this.helpers.httpRequest({
							method: 'POST' as IHttpRequestMethods,
							url: `${apiUrl}/api/v1/actions`,
							body,
							json: true,
						});

						responseData = (response as IDataObject).data as IDataObject;

					} else if (operation === 'get') {
						const actionId = this.getNodeParameter('actionId', i) as string;

						const response = await this.helpers.httpRequest({
							method: 'GET' as IHttpRequestMethods,
							url: `${apiUrl}/api/v1/actions/${actionId}`,
							json: true,
						});

						responseData = (response as IDataObject).data as IDataObject;

					} else if (operation === 'cancel') {
						const actionId = this.getNodeParameter('actionId', i) as string;

						const response = await this.helpers.httpRequest({
							method: 'DELETE' as IHttpRequestMethods,
							url: `${apiUrl}/api/v1/actions/${actionId}`,
							json: true,
						});

						responseData = response as IDataObject;
					} else {
						throw new NodeOperationError(this.getNode(), `Unknown operation: ${operation}`);
					}
				} else {
					throw new NodeOperationError(this.getNode(), `Unknown resource: ${resource}`);
				}

				returnData.push({ json: responseData! });
			} catch (error) {
				if (this.continueOnFail()) {
					returnData.push({ json: { error: (error as Error).message } });
					continue;
				}
				throw error;
			}
		}

		return [returnData];
	}
}
