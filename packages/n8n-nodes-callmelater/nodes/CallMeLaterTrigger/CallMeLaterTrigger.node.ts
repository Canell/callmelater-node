import type {
	IHookFunctions,
	IWebhookFunctions,
	INodeType,
	INodeTypeDescription,
	IWebhookResponseData,
	IDataObject,
} from 'n8n-workflow';
import { createHmac } from 'crypto';

function computeSignature(payload: string, secret: string): string {
	const hmac = createHmac('sha256', secret);
	hmac.update(payload);
	return `sha256=${hmac.digest('hex')}`;
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
				required: false,
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
				description: 'Optional. Verify webhook signatures using this secret. Must match the secret configured in CallMeLater.',
			},
		],
	};

	webhookMethods = {
		default: {
			async checkExists(this: IHookFunctions): Promise<boolean> {
				// Webhook is always available via n8n's webhook URL
				return true;
			},
			async create(this: IHookFunctions): Promise<boolean> {
				// No registration needed - user copies webhook URL to CallMeLater
				return true;
			},
			async delete(this: IHookFunctions): Promise<boolean> {
				// No cleanup needed
				return true;
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
			...(body.response && { response: body.response }),
			...(body.responder_email && { responder_email: body.responder_email }),
			...(body.responded_at && { responded_at: body.responded_at }),
			...(body.snooze_preset && { snooze_preset: body.snooze_preset }),
			...(body.next_reminder_at && { next_reminder_at: body.next_reminder_at }),
			...(body.action_status && { action_status: body.action_status }),
			...(body.comment && { comment: body.comment }),
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
