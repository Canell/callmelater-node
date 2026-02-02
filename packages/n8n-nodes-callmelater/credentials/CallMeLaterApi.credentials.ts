import type {
	IAuthenticateGeneric,
	ICredentialTestRequest,
	ICredentialType,
	INodeProperties,
} from 'n8n-workflow';

export class CallMeLaterApi implements ICredentialType {
	name = 'callMeLaterApi';
	displayName = 'CallMeLater API';
	documentationUrl = 'https://docs.callmelater.io/api/authentication';
	properties: INodeProperties[] = [
		{
			displayName: 'API Token',
			name: 'apiToken',
			type: 'string',
			typeOptions: {
				password: true,
			},
			default: '',
			required: true,
			placeholder: 'sk_live_...',
			description: 'Your CallMeLater API token. Find it in Settings → API Tokens.',
		},
		{
			displayName: 'API URL',
			name: 'apiUrl',
			type: 'string',
			default: 'https://api.callmelater.io',
			description: 'The CallMeLater API URL. Change only for self-hosted instances.',
		},
	];

	authenticate: IAuthenticateGeneric = {
		type: 'generic',
		properties: {
			headers: {
				Authorization: '=Bearer {{$credentials.apiToken}}',
			},
		},
	};

	test: ICredentialTestRequest = {
		request: {
			baseURL: '={{$credentials.apiUrl}}',
			url: '/api/v1/quota',
			method: 'GET',
		},
	};
}
