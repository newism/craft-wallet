import { h } from 'vue'
import type { Theme } from 'vitepress'
import DefaultTheme from 'vitepress/theme'
import '@newism/vitepress-shared/styles/custom.css'
import PluginHero from '@newism/vitepress-shared/components/PluginHero.vue'
import ContactNewism from '@newism/vitepress-shared/components/ContactNewism.vue'

export default {
  extends: DefaultTheme,
  Layout: () => {
    return h(DefaultTheme.Layout, null, {
      'home-hero-info-before': () => h(PluginHero),
      'doc-before': () => h(ContactNewism),
    })
  },
  enhanceApp({ app }) {},
} satisfies Theme
