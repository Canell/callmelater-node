import type {
	IHookFunctions,
	IWebhookFunctions,
	INodeType,
	INodeTypeDescription,
	IWebhookResponseData,
	IDataObject,
	IHttpRequestMethods,
} from 'n8n-workflow';
import { createHmac } from 'crypto';

function computeSignature(payload: string, secret: string): string {
	const hmac = createHmac('sha256', secret);
	hmac.update(payload);
	return `sha256=${hmac.digest('hex')}`;
}

// Map event filter to API event types
function getEventsForFilter(filter: string): string[] {
	if (filter === 'any') {
		return [
			'action.executed',
			'action.failed',
			'action.expired',
			'reminder.responded',
		];
	}
	return [filter];
}

export class CallMeLaterTrigger implements INodeType {
	description: INodeTypeDescription = {
		displayName: 'CallMeLater Trigger',
		name: 'callMeLaterTrigger',
		icon: 'file:callmelater.svg',
		group: ['trigger'],
		version: 1,
		subtitle: '={{$parameter["event"]}}',
		description: 'Triggers when CallMeLater events occur (responses, completions, failures)',
		defaults: {
			name: 'CallMeLater Trigger',
		},
		inputs: [],
		outputs: ['main'],
		credentials: [
			{
				name: 'callMeLaterApi',
				required: true,
			},
		],
		webhooks: [
			{
				name: 'default',
				httpMethod: 'POST',
				responseMode: 'onReceived',
				path: 'webhook',
			},
		],
		properties: [
			{
				displayName: 'Event',
				name: 'event',
				type: 'options',
				options: [
					{
						name: 'Any Event',
						value: 'any',
						description: 'Trigger on any CallMeLater event',
					},
					{
						name: 'Reminder Responded',
						value: 'reminder.responded',
						description: 'Someone confirmed, declined, or snoozed a reminder',
					},
					{
						name: 'Action Executed',
						value: 'action.executed',
						description: 'A scheduled webhook was successfully executed',
					},
					{
						name: 'Action Failed',
						value: 'action.failed',
						description: 'A scheduled webhook failed after all retries',
					},
					{
						name: 'Action Expired',
						value: 'action.expired',
						description: 'A reminder expired without response',
					},
				],
				default: 'any',
				description: 'Which event should trigger this workflow',
			},
			{
				displayName: 'Webhook Secret',
				name: 'webhookSecret',
				type: 'string',
				typeOptions: {
					password: true,
				},
				default: '',
				description: 'Optional. Secret for verifying webhook signatures. If not provided, a random secret will be generated.',
			},
		],
	};

	webhookMethods = {
		default: {
			async checkExists(this: IHookFunctions): Promise<boolean> {
				const webhookData = this.getWorkflowStaticData('node');
				const webhookUrl = this.getNodeWebhookUrl('default');

				// If we have a stored webhook ID, verify it still exists
				if (webhookData.webhookId) {
					try {
						const credentials = await this.getCredentials('callMeLaterApi');
						const apiUrl = (credentials.apiUrl as string) || 'https://callmelater.io';

						const response = await this.helpers.httpRequest({
							method: 'GET' as IHttpRequestMethods,
							url: `${apiUrl}/api/v1/webhooks/${webhookData.webhookId}`,
							headers: {
								Authorization: `Bearer ${credentials.apiKey}`,
							},
							returnFullResponse: true,
							ignoreHttpStatusErrors: true,
						});

						if (response.statusCode === 200) {
							const webhook = response.body as IDataObject;
							// Check if URL matches
							if ((webhook.data as IDataObject)?.url === webhookUrl) {
								return true;
							}
						}
					} catch {
						// Webhook doesn't exist or error checking
					}
				}

				return false;
			},

			async create(this: IHookFunctions): Promise<boolean> {
				const webhookData = this.getWorkflowStaticData('node');
				const webhookUrl = this.getNodeWebhookUrl('default');
				const eventFilter = this.getNodeParameter('event') as string;
				const webhookSecret = this.getNodeParameter('webhookSecret') as string;

				try {
					const credentials = await this.getCredentials('callMeLaterApi');
					const apiUrl = (credentials.apiUrl as string) || 'https://callmelater.io';

					const events = getEventsForFilter(eventFilter);

					const response = await this.helpers.httpRequest({
						method: 'POST' as IHttpRequestMethods,
						url: `${apiUrl}/api/v1/webhooks`,
						headers: {
							Authorization: `Bearer ${credentials.apiKey}`,
							'Content-Type': 'application/json',
						},
						body: {
							name: `n8n: ${this.getWorkflow().name || 'Workflow'}`,
							url: webhookUrl,
							events,
							...(webhookSecret && { secret: webhookSecret }),
						},
					});

					const data = (response as IDataObject).data as IDataObject;
					webhookData.webhookId = data.id;

					return true;
				} catch (error) {
					// If webhook already exists for this URL, that's fine
					if ((error as Error).message?.includes('Webhook updated')) {
						return true;
					}
					throw error;
				}
			},

			async delete(this: IHookFunctions): Promise<boolean> {
				const webhookData = this.getWorkflowStaticData('node');

				if (!webhookData.webhookId) {
					return true;
				}

				try {
					const credentials = await this.getCredentials('callMeLaterApi');
					const apiUrl = (credentials.apiUrl as string) || 'https://callmelater.io';

					await this.helpers.httpRequest({
						method: 'DELETE' as IHttpRequestMethods,
						url: `${apiUrl}/api/v1/webhooks/${webhookData.webhookId}`,
						headers: {
							Authorization: `Bearer ${credentials.apiKey}`,
						},
						ignoreHttpStatusErrors: true,
					});

					delete webhookData.webhookId;
					return true;
				} catch {
					// Ignore errors on delete
					return true;
				}
			},
		},
	};

	async webhook(this: IWebhookFunctions): Promise<IWebhookResponseData> {
		const body = this.getBodyData() as IDataObject;
		const headers = this.getHeaderData() as IDataObject;

		// Get configured event filter
		const eventFilter = this.getNodeParameter('event') as string;
		const webhookSecret = this.getNodeParameter('webhookSecret') as string;

		// Verify signature if secret is configured
		if (webhookSecret) {
			const signature = headers['x-callmelater-signature'] as string;
			if (!signature) {
				return {
					webhookResponse: {
						status: 401,
						body: { error: 'Missing signature header' },
					},
				};
			}

			const expectedSignature = computeSignature(
				JSON.stringify(body),
				webhookSecret,
			);

			if (signature !== expectedSignature) {
				return {
					webhookResponse: {
						status: 401,
						body: { error: 'Invalid signature' },
					},
				};
			}
		}

		// Filter by event type
		const eventType = body.event as string;
		if (eventFilter !== 'any' && eventType !== eventFilter) {
			// Event doesn't match filter, acknowledge but don't trigger
			return {
				webhookResponse: {
					status: 200,
					body: { received: true, filtered: true },
				},
			};
		}

		// Build output data
		const outputData: IDataObject = {
			event: eventType,
			action_id: body.action_id,
			action_name: body.action_name,
			timestamp: body.timestamp,
			// Reminder response fields
			...(body.response && { response: body.response }),
			...(body.responder_email && { responder_email: body.responder_email }),
			...(body.responded_at && { responded_at: body.responded_at }),
			...(body.snooze_preset && { snooze_preset: body.snooze_preset }),
			...(body.next_reminder_at && { next_reminder_at: body.next_reminder_at }),
			...(body.action_status && { action_status: body.action_status }),
			...(body.comment && { comment: body.comment }),
			// Action callback fields
			...(body.execution && { execution: body.execution }),
			...(body.failure && { failure: body.failure }),
			...(body.expiration && { expiration: body.expiration }),
			// Include full body for advanced use
			_raw: body,
		};

		return {
			workflowData: [this.helpers.returnJsonArray([outputData])],
			webhookResponse: {
				status: 200,
				body: { received: true },
			},
		};
	}
}
