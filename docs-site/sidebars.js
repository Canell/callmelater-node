// @ts-check

/** @type {import('@docusaurus/plugin-content-docs').SidebarsConfig} */
const sidebars = {
  docsSidebar: [
    'intro',
    {
      type: 'category',
      label: 'Concepts',
      collapsed: false,
      items: [
        'concepts/actions',
        'concepts/states',
        'concepts/retries',
        'concepts/reminders',
        'concepts/idempotency',
        'concepts/limitations',
      ],
    },
    {
      type: 'category',
      label: 'API Reference',
      collapsed: false,
      items: [
        'api/authentication',
        'api/create-action',
        'api/list-actions',
        'api/get-action',
        'api/cancel-action',
        'api/retry-action',
        'api/callbacks',
        'api/chains',
        'api/templates',
        'api/webhooks',
        'api/coordination-keys',
        'api/domains',
        'api/team-members',
        'api/quota',
      ],
    },
    {
      type: 'category',
      label: 'Guides',
      collapsed: true,
      items: [
        'guides/common-patterns',
        'guides/trial-expiration',
        'guides/approval-workflows',
        'guides/scheduled-reports',
        'guides/error-handling',
      ],
    },
    {
      type: 'category',
      label: 'Reference',
      collapsed: true,
      items: [
        'reference/rate-limits',
        'reference/retry-behavior',
        'reference/security',
        'reference/changelog',
      ],
    },
  ],
};

export default sidebars;
