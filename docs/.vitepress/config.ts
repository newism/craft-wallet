import { defineConfig } from 'vitepress'
import { configPlugin } from '../../../docs/plugins/configPlugin'
import { consoleCommandPlugin } from '../../../docs/plugins/consoleCommandPlugin'
import { head } from '../../../docs'

export default defineConfig({
  head,
  base: '/wallet/',
  srcDir: '.',
  title: 'Wallet Passes',
  description: 'Digital membership and loyalty cards for Apple Wallet and Google Wallet, built into Craft CMS.',
  ignoreDeadLinks: true,

  srcExclude: [
    'node_modules/**',
    'plans/**',
  ],

  markdown: {
    config(md) {
      md.use(configPlugin)
      md.use(consoleCommandPlugin)
    },
  },

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'View on Plugin Store', link: 'https://plugins.craftcms.com/wallet' },
      { text: 'All Plugins', link: 'https://plugins.newism.com.au/', target: '_self' },
    ],

    sidebar: [
      {
        text: 'Getting Started',
        items: [
          { text: 'Features', link: '/features' },
          { text: 'Installation', link: '/installation' },
          { text: 'Setup', link: '/setup' },
          { text: 'Configuration', link: '/configuration' },
        ],
      },
      {
        text: 'Usage',
        items: [
          { text: 'Usage', link: '/usage' },
          { text: 'Customising Passes', link: '/customising-passes' },
        ],
      },
      {
        text: 'Extending',
        items: [
          { text: 'Generators', link: '/generators' },
        ],
      },
      {
        text: 'Developers',
        items: [
          { text: 'How It Works', link: '/how-it-works' },
          { text: 'Apple Wallet Setup', link: '/apple-wallet-setup' },
          { text: 'Google Wallet Setup', link: '/google-wallet-setup' },
          { text: 'Webhooks', link: '/webhooks' },
          { text: 'Events', link: '/events' },
          { text: 'GraphQL', link: '/graphql' },
          { text: 'Logging', link: '/logging' },
        ],
      },
      { text: 'Support', link: '/support' },
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/newism' },
      { icon: 'linkedin', link: 'https://www.linkedin.com/company/newism' },
    ],
  },
})
