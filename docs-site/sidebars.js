// @ts-check

/** @type {import('@docusaurus/plugin-content-docs').SidebarsConfig} */
const sidebars = {
  docsSidebar: [
    {
      type: 'category',
      label: 'Getting Started',
      collapsible: false,
      items: [
        'index',
        'quick-start',
      ],
    },
    {
      type: 'category',
      label: 'SDKs & Integrations',
      collapsed: false,
      items: [
        'sdks/nodejs',
        'sdks/laravel',
        'sdks/n8n',
      ],
    },
    {
      type: 'category',
      label: 'Core Concepts',
      collapsed: true,
      items: [
        'concepts/actions',
        'concepts/approvals',
        'concepts/reliability',
      ],
    },
    {
      type: 'category',
      label: 'Guides',
      collapsed: true,
      items: [
        'guides/patterns',
        'guides/chains',
        'guides/templates',
      ],
    },
    {
      type: 'category',
      label: 'API Reference',
      collapsed: true,
      items: [
        'api/authentication',
        'api/actions',
        'api/chains',
        'api/templates',
        'api/account',
      ],
    },
    {
      type: 'category',
      label: 'Reference',
      collapsed: true,
      items: [
        'reference/security',
        'reference/limits',
      ],
    },
  ],
};

export default sidebars;
