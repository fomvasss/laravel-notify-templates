import { fileURLToPath } from 'node:url';
import { defineConfig, passthroughImageService } from 'astro/config';
import starlight from '@astrojs/starlight';
import mermaid from 'astro-mermaid';
import starlightGitHubAlerts from 'starlight-github-alerts';
import starlightLinksValidator from 'starlight-links-validator';
import { remarkPlainMarkdown } from './src/remark-plain-markdown.mjs';

const base = '/laravel-notify-templates';

export default defineConfig({
	site: 'https://fomvasss.github.io',
	base,
	image: { service: passthroughImageService() },
	markdown: {
		remarkPlugins: [[remarkPlainMarkdown, { root: fileURLToPath(new URL('.', import.meta.url)), base }]],
	},
	integrations: [
		mermaid(),
		starlight({
			title: 'Laravel Notify Templates',
			description: 'Database-driven notification templates, role subscriptions and channel resolution for Laravel',
			social: [{ icon: 'github', label: 'GitHub', href: 'https://github.com/fomvasss/laravel-notify-templates' }],
			// the pages live in docs/ itself, not in src/content/docs/
			markdown: { processedDirs: ['.'] },
			expressiveCode: { shiki: { langAlias: { env: 'dotenv' } } },
			editLink: { baseUrl: 'https://github.com/fomvasss/laravel-notify-templates/edit/master/docs/' },
			plugins: [starlightGitHubAlerts(), starlightLinksValidator()],
			sidebar: [
				{ label: 'Getting started', items: [{ label: 'Overview', slug: 'index' }, 'installation', 'configuration'] },
				{
					label: 'Usage',
					items: [
						'usage/notify-types',
						'usage/sending',
						'usage/recipients',
						'usage/templates',
						'usage/channels',
						'usage/user-preferences',
						'usage/custom-channels',
						'usage/messenger-buttons',
						'usage/delivery-log',
						'usage/multi-tenancy',
						'usage/translations',
						'usage/queues-octane',
					],
				},
				{
					label: 'Reference',
					items: [
						'reference/facade',
						'reference/base-notify',
						'reference/type-definition',
						'reference/models',
						'reference/contracts',
						'reference/commands',
					],
				},
				'upgrading',
			],
		}),
	],
});
