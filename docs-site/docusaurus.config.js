// @ts-check
import { themes as prismThemes } from 'prism-react-renderer';

/** @type {import('@docusaurus/types').Config} */
const config = {
  title: 'CallMeLater',
  tagline: 'Make things happen later — without surprises',
  favicon: 'img/favicon.ico',

  url: 'https://docs.callmelater.io',
  baseUrl: '/',

  organizationName: 'callmelater',
  projectName: 'callmelater-docs',

  onBrokenLinks: 'throw',
  onBrokenMarkdownLinks: 'warn',

  headTags: [
    {
      tagName: 'link',
      attributes: {
        rel: 'alternate',
        type: 'text/plain',
        title: 'LLMs.txt',
        href: 'https://docs.callmelater.io/llms.txt',
      },
    },
    {
      tagName: 'link',
      attributes: {
        rel: 'alternate',
        type: 'text/plain',
        title: 'LLMs Full',
        href: 'https://docs.callmelater.io/llms-full.txt',
      },
    },
  ],

  i18n: {
    defaultLocale: 'en',
    locales: ['en'],
  },

  plugins: [
    [
      'docusaurus-plugin-llms',
      {
        generateLLMsTxt: true,
        generateLLMsFullTxt: true,
        docsDir: 'docs',
        title: 'CallMeLater Documentation',
        description: 'CallMeLater is a developer-first API for scheduling durable HTTP calls and interactive human reminders. Authenticate with Bearer tokens (sk_live_...). Two core primitives: (1) Scheduled webhooks — fire HTTP requests at a future time with automatic retries, (2) Approval reminders — send yes/no/snooze prompts via email or SMS with escalation. Also supports multi-step chains (workflows) and reusable templates. SDKs available for Node.js, Laravel/PHP, and n8n.',
        excludeImports: true,
        removeDuplicateHeadings: true,
        pathTransformation: {
          ignorePaths: ['docs'],
        },
        includeOrder: [
          'index.md',
          'quick-start.md',
          'sdks/*',
          'concepts/*',
          'guides/*',
          'api/*',
          'reference/*',
        ],
      },
    ],
  ],

  presets: [
    [
      'classic',
      /** @type {import('@docusaurus/preset-classic').Options} */
      ({
        docs: {
          sidebarPath: './sidebars.js',
          routeBasePath: '/',
        },
        blog: false,
        theme: {
          customCss: './src/css/custom.css',
        },
      }),
    ],
  ],

  themeConfig:
    /** @type {import('@docusaurus/preset-classic').ThemeConfig} */
    ({
      image: 'img/callmelater-social.png',
      navbar: {
        title: 'CallMeLater',
        logo: {
          alt: 'CallMeLater Logo',
          src: 'img/logo.svg',
        },
        items: [
          {
            type: 'docSidebar',
            sidebarId: 'docsSidebar',
            position: 'left',
            label: 'Documentation',
          },
          {
            href: 'https://callmelater.io',
            label: 'Homepage',
            position: 'right',
          },
          {
            href: 'https://app.callmelater.io',
            label: 'Dashboard',
            position: 'right',
          },
        ],
      },
      footer: {
        style: 'dark',
        links: [
          {
            title: 'Docs',
            items: [
              {
                label: 'Getting Started',
                to: '/',
              },
              {
                label: 'API Reference',
                to: '/api/authentication',
              },
            ],
          },
          {
            title: 'Product',
            items: [
              {
                label: 'Homepage',
                href: 'https://callmelater.io',
              },
              {
                label: 'Pricing',
                href: 'https://callmelater.io/pricing',
              },
              {
                label: 'Dashboard',
                href: 'https://app.callmelater.io',
              },
            ],
          },
          {
            title: 'Legal',
            items: [
              {
                label: 'Privacy Policy',
                href: 'https://callmelater.io/privacy',
              },
              {
                label: 'Terms of Service',
                href: 'https://callmelater.io/terms',
              },
            ],
          },
        ],
        copyright: `Copyright © ${new Date().getFullYear()} CallMeLater. All rights reserved.`,
      },
      prism: {
        theme: prismThemes.github,
        darkTheme: prismThemes.dracula,
        additionalLanguages: ['bash', 'json', 'php', 'python', 'java', 'go', 'ruby'],
      },
      colorMode: {
        defaultMode: 'light',
        disableSwitch: false,
        respectPrefersColorScheme: true,
      },
    }),
};

export default config;
