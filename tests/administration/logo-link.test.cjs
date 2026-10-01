const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const { test } = require('node:test');
const vm = require('node:vm');
const { JSDOM } = require('jsdom');

const dom = new JSDOM('<div id="app"></div>', { url: 'https://admin.example/' });
for (const key of ['window', 'document', 'Element', 'HTMLElement', 'SVGElement', 'Node']) {
    global[key] = key === 'window' ? dom.window : key === 'document' ? dom.window.document : dom.window[key];
}
const { createApp, nextTick, h } = require('vue');
const extensionPath = resolve(__dirname, '../../src/Resources/app/administration/src/extension/sw-admin-menu');
const helpers = {};
for (const file of ['storefront-link.helper.js', 'logo-link.helper.js']) {
    const source = readFileSync(`${extensionPath}/${file}`, 'utf8').replaceAll('export function ', 'function ');
    vm.runInNewContext(`${source}\nObject.assign(helpers, { ${file === 'logo-link.helper.js'
        ? 'updateLogoLink' : 'getConfiguredSalesChannelId, isLogoStorefrontLinkEnabled, resolveStorefrontUrl'} });`, { helpers });
}
let definition;
const source = readFileSync(`${extensionPath}/index.js`, 'utf8')
    .replace(/^import[\s\S]*?from "\.\/storefront-link\.helper";/, '');
vm.runInNewContext(source, {
    ...helpers,
    Shopware: {
        Component: { override: (name, config) => { assert.equal(name, 'sw-admin-menu'); definition = config; } },
        Data: { Criteria: class {
            static equals() {}
            static not() {}
            static sort() {}
            addAssociation() {}
            addFilter() {}
            addSorting() {}
        } },
        Context: { api: {} },
        Defaults: { storefrontSalesChannelTypeId: 'storefront' },
    },
});

function createComponent({ privileges = ['system_config:read', 'sales_channel:read'], values = {}, failure = false } = {}) {
    const requests = [];
    const component = {
        ...definition.data(),
        acl: { can: (privilege) => privileges.includes(privilege) },
        systemConfigApiService: { getValues: async () => {
            requests.push('config');
            if (failure) throw new Error('Network error');
            return values;
        } },
        domainLinkService: { getDomainLink: () => 'https://shop.example/' },
        salesChannelRepository: {
            get: async () => { requests.push('get'); return { id: 'configured' }; },
            search: async () => { requests.push('search'); return { first: () => ({ id: 'fallback' }) }; },
        },
        requests,
    };
    for (const [name, method] of Object.entries(definition.methods)) component[name] = method.bind(component);
    return component;
}

for (const privileges of [[], ['system_config:read'], ['sales_channel:read']]) {
    test(`logo performs no requests with privileges ${JSON.stringify(privileges)}`, async () => {
        const component = createComponent({ privileges });
        await component.loadStorefrontLink();
        assert.equal(component.storefrontUrl, null);
        assert.deepEqual(component.requests, []);
    });
}

test('disabled logo link does not load sales channels', async () => {
    const component = createComponent({ values: { 'WakoPluginAdminToolbar.config.logoStorefrontLinkEnabled': false } });
    await component.loadStorefrontLink();
    assert.equal(component.storefrontUrl, null);
    assert.deepEqual(component.requests, ['config']);
});

test('logo uses the configured sales channel', async () => {
    const component = createComponent({ values: { 'WakoPluginAdminToolbar.config.logoStorefrontSalesChannelId': 'configured' } });
    await component.loadStorefrontLink();
    assert.equal(component.storefrontUrl, 'https://shop.example/');
    assert.deepEqual(component.requests, ['config', 'get']);
});

test('logo uses the fallback channel and handles configuration errors', async () => {
    const component = createComponent();
    await component.loadStorefrontLink();
    assert.equal(component.storefrontUrl, 'https://shop.example/');
    assert.deepEqual(component.requests, ['config', 'search']);
    const failed = createComponent({ failure: true });
    await failed.loadStorefrontLink();
    assert.equal(failed.storefrontUrl, null);
    assert.deepEqual(failed.requests, ['config']);
});

const coreLogos = {
    'legacy CSS logo': '<div class="sw-admin-menu__header-logo" role="img"></div>',
    '6.7.15 icon and expand control': `<div class="sw-admin-menu__header-logo-wrapper">
        <div class="sw-admin-menu__header-logo-box"><mt-icon class="sw-admin-menu__header-logo" /></div>
        <button v-if="!isExpanded" class="sw-admin-menu__header-logo-expand-button" @click.stop="isExpanded = true">Expand</button>
    </div>`,
};

for (const [name, markup] of Object.entries(coreLogos)) {
    test(`${name}: preserves Core nodes, updates links and restores the original markup`, async () => {
        const app = createApp({
            template: `${name.startsWith('6.7.15') ? '<div v-if="false" class="backdrop"></div>' : ''}
                <aside ${name.startsWith('6.7.15') ? 'ref="swAdminMenu"' : ''}
                    class="sw-admin-menu" :class="{ 'is--expanded': isExpanded }">
                    <div class="sw-admin-menu__header">${markup}</div>
                    <button class="core-toggle" @click="isExpanded = !isExpanded">Toggle</button>
                </aside>`,
            data: () => ({ storefrontUrl: null, isExpanded: true }),
            methods: definition.methods,
            watch: definition.watch,
            mounted: definition.mounted,
            updated: definition.updated,
            beforeUnmount: definition.beforeUnmount,
            components: { 'mt-icon': { render: () => h('span', [h('svg')]) } },
        });
        app.config.globalProperties.$t = (key) => key;
        const component = app.mount(document.getElementById('app'));
        const menu = document.querySelector('.sw-admin-menu');
        const originalMarkup = menu.innerHTML;
        const originalLogo = menu.querySelector('.sw-admin-menu__header-logo');
        assert.equal(menu.querySelector('a'), null);

        component.storefrontUrl = 'https://shop.example/';
        await nextTick();
        const link = menu.querySelector('a.wako-admin-toolbar-logo-link');
        assert.equal(link.firstElementChild, originalLogo);
        assert.equal(link.href, 'https://shop.example/');
        assert.equal(link.classList.contains('sw-external-link'), true);
        assert.equal(link.target, '_blank');
        assert.equal(link.rel, 'noopener noreferrer');
        assert.equal(link.getAttribute('aria-label'), 'wako-admin-toolbar.adminMenu.openStorefront');
        assert.equal(menu.querySelector('.core-toggle').closest('a'), null);

        menu.querySelector('.core-toggle').click();
        await nextTick();
        assert.equal(component.isExpanded, false);
        if (name.startsWith('6.7.15')) {
            const expand = menu.querySelector('.sw-admin-menu__header-logo-expand-button');
            assert.equal(expand.closest('a'), null);
            expand.click();
        } else {
            menu.querySelector('.core-toggle').click();
        }
        await nextTick();
        assert.equal(component.isExpanded, true);
        assert.equal(menu.querySelector('.sw-admin-menu__header-logo'), originalLogo);

        component.storefrontUrl = 'https://other.example/';
        await nextTick();
        assert.equal(menu.querySelector('a'), link);
        assert.equal(link.href, 'https://other.example/');
        assert.equal(menu.querySelectorAll('a').length, 1);

        component.storefrontUrl = null;
        await nextTick();
        assert.equal(menu.innerHTML, originalMarkup);
        component.storefrontUrl = 'https://shop.example/';
        await nextTick();
        app.unmount();
        assert.equal(document.getElementById('app').innerHTML, '');
    });
}

test('logo extension does not replace the Core template', () => {
    assert.equal(definition.template, undefined);
});
