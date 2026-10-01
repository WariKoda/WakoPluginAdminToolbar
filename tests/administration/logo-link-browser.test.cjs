const assert = require('node:assert/strict');
const { mkdtempSync, readFileSync, readdirSync, rmSync, writeFileSync, existsSync } = require('node:fs');
const { tmpdir } = require('node:os');
const { resolve, join } = require('node:path');
const { spawnSync } = require('node:child_process');
const { pathToFileURL } = require('node:url');
const { test } = require('node:test');

const plugin = resolve(__dirname, '../..');
const chromium = process.env.CHROMIUM_BIN || 'chromium';
const browserAvailable = spawnSync(chromium, ['--version'], { encoding: 'utf8' }).status === 0;
const extension = join(plugin, 'src/Resources/app/administration/src/extension/sw-admin-menu');
const helper = readFileSync(join(extension, 'logo-link.helper.js'), 'utf8').replace('export function ', 'function ');
const override = readFileSync(join(extension, 'index.js'), 'utf8')
    .replace(/^import[\s\S]*?from "\.\/storefront-link\.helper";/, '');
const vueUrl = pathToFileURL(require.resolve('vue/dist/vue.global.js')).href;
const assets = join(plugin, 'src/Resources/public/administration');
const manifest = JSON.parse(readFileSync(join(assets, '.vite/manifest.json'), 'utf8'));
const pluginCss = Object.values(manifest).flatMap((entry) => entry.css || [])
    .map((file) => pathToFileURL(join(assets, file)).href);

for (const [target, vendor] of [
    ['project', resolve(plugin, '../../../vendor/shopware/administration')],
    ['standalone', join(plugin, 'vendor/shopware/administration')],
]) {
    const templatePath = join(vendor, 'Resources/app/administration/src/app/component/structure/sw-admin-menu/sw-admin-menu.html.twig');
    const cssDirectory = join(vendor, 'Resources/public/administration/assets');
    const available = browserAvailable && existsSync(templatePath) && existsSync(cssDirectory);

    test(`${target}: Chromium preserves the actual Core logo layout and sidebar controls`, { skip: !available }, () => {
        const coreTemplate = readFileSync(templatePath, 'utf8');
        const logoBlock = coreTemplate.split('{% block sw_admin_menu_header_logo %}')[1].split('{% endblock %}')[0];
        const modern = logoBlock.includes('sw-admin-menu__header-logo-wrapper');
        const cssFiles = readdirSync(cssDirectory).filter((file) => file.endsWith('.css'));
        const coreCss = cssFiles.find((file) => readFileSync(join(cssDirectory, file), 'utf8').includes('.sw-admin-menu__header-logo{'));
        const globalCss = cssFiles.find((file) => readFileSync(join(cssDirectory, file), 'utf8').includes('a[target=_blank]:not('));
        assert.ok(coreCss, 'Core menu stylesheet must be available');
        assert.ok(globalCss, 'Core global link styles must be available');
        const cssUrls = [...new Set([globalCss, coreCss])]
            .map((file) => pathToFileURL(join(cssDirectory, file)).href).concat(pluginCss);
        const template = `${modern ? '<div v-if="false"></div>' : ''}
            <aside ${modern ? 'ref="swAdminMenu"' : ''} class="sw-admin-menu"
                :class="isExpanded ? 'is--expanded' : 'is--collapsed'">
                <div class="sw-admin-menu__header">${logoBlock}</div>
                <button id="toggle" @click="isExpanded = !isExpanded">Toggle</button>
            </aside>`;
        const script = `
            let definition;
            const Shopware = { Component: { override(name, config) { definition = config; } }, Data: { Criteria: class {} } };
            ${helper}
            ${override}
            const app = Vue.createApp({
                template: ${JSON.stringify(template)},
                data: () => ({ storefrontUrl: null, isExpanded: true }),
                methods: { ...definition.methods, onToggleSidebar() { this.isExpanded = !this.isExpanded; } },
                watch: definition.watch,
                mounted: definition.mounted,
                updated: definition.updated,
                beforeUnmount: definition.beforeUnmount,
                components: { 'mt-icon': { render: () => Vue.h('span', [Vue.h('svg', {
                    viewBox: '0 0 34 34',
                }, [Vue.h('rect', { width: 34, height: 34, fill: 'currentColor' })])]) } },
            });
            app.config.globalProperties.$t = (key) => key;
            const component = app.mount('#app');
            (async () => {
                const errors = [];
                const logo = document.querySelector('.sw-admin-menu__header-logo');
                const before = logo.getBoundingClientRect().toJSON();
                component.storefrontUrl = 'https://shop.example/';
                await Vue.nextTick();
                const link = document.querySelector('a.wako-admin-toolbar-logo-link');
                const after = logo.getBoundingClientRect().toJSON();
                if (JSON.stringify(before) !== JSON.stringify(after)) errors.push('Logo layout changed');
                if (!after.width || !after.height) errors.push('Logo has no visible size');
                link.focus();
                if (document.activeElement !== link) errors.push('Link is not keyboard focusable');
                if (!['none', 'normal'].includes(getComputedStyle(link, '::after').content)) errors.push('External-link decoration remains');
                document.querySelector('#toggle').click();
                await Vue.nextTick();
                const expand = document.querySelector('.sw-admin-menu__header-logo-expand-button');
                if (expand) {
                    if (expand.closest('a')) errors.push('Expand button is nested in the link');
                    expand.click();
                } else {
                    document.querySelector('#toggle').click();
                }
                await Vue.nextTick();
                if (!component.isExpanded) errors.push('Sidebar did not expand');
                component.storefrontUrl = null;
                await Vue.nextTick();
                if (document.querySelector('.wako-admin-toolbar-logo-link')) errors.push('Link was not removed');
                if (document.querySelector('.sw-admin-menu__header-logo') !== logo) errors.push('Core logo was replaced');
                app.unmount();
                document.querySelector('#result').textContent = JSON.stringify({ errors, before, after });
            })().catch((error) => {
                document.querySelector('#result').textContent = JSON.stringify({ errors: [error.stack] });
            });
        `;
        const directory = mkdtempSync(join(tmpdir(), 'wako-toolbar-browser-'));
        try {
            const htmlPath = join(directory, 'index.html');
            writeFileSync(htmlPath, `<!doctype html><meta charset="utf-8">
                ${cssUrls.map((url) => `<link rel="stylesheet" href="${url}">`).join('')}
                <div id="app"></div><pre id="result">pending</pre>
                <script src="${vueUrl}"></script><script>${script}</script>`);
            const run = spawnSync(chromium, [
                '--headless', '--no-sandbox', '--disable-gpu', '--allow-file-access-from-files',
                '--window-size=1600,900', '--virtual-time-budget=2000', '--dump-dom', pathToFileURL(htmlPath).href,
            ], { encoding: 'utf8', timeout: 30000, maxBuffer: 2 * 1024 * 1024 });
            assert.equal(run.status, 0, run.stderr);
            const result = run.stdout.match(/<pre id="result">(.*?)<\/pre>/s);
            assert.ok(result, 'Browser result must be present');
            const data = JSON.parse(result[1].replaceAll('&quot;', '"').replaceAll('&amp;', '&').replaceAll('&lt;', '<').replaceAll('&gt;', '>'));
            assert.deepEqual(data.errors, []);
        } finally {
            rmSync(directory, { recursive: true, force: true });
        }
    });
}
