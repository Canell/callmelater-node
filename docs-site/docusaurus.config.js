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

  i18n: {
    defaultLocale: 'en',
    locales: ['en'],
  },

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
        additionalLanguages: ['bash', 'json', 'php'],
      },
      colorMode: {
        defaultMode: 'light',
        disableSwitch: false,
        respectPrefersColorScheme: true,
      },
    }),
};

export default config;
