const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const { test } = require('node:test');
const vm = require('node:vm');

const extensionPath = resolve(__dirname, '../../src/Resources/app/administration/src/extension/sw-page');
const source = readFileSync(`${extensionPath}/index.js`, 'utf8')
    .replace("import template from './sw-page.html.twig';", "const template = 'sw-page';")
    .replace("import meteorTemplate from './sw-meteor-page.html.twig';", "const meteorTemplate = 'sw-meteor-page';")
    .replace(
        "import { loadAdminToolbarConfig } from '../../service/admin-toolbar-config';",
        "const loadAdminToolbarConfig = (service) => service.getValues('WakoPluginAdminToolbar.config');",
    );
const definitions = {};
vm.runInNewContext(source, {
    Shopware: {
        Component: { override: (name, config) => { definitions[name] = config; } },
        Mixin: { getByName: () => ({}) },
    },
});

function createComponent({ allowed = true, configRead = true, values = {}, configError = false, page = 'sw-page' } = {}) {
    const definition = definitions[page];
    const notifications = [];
    const component = {
        ...definition.data(),
        acl: { can: (privilege) => privilege === 'system:clear:cache' ? allowed : configRead },
        systemConfigApiService: {
            async getValues(domain) {
                assert.equal(domain, 'WakoPluginAdminToolbar.config');
                if (configError) throw new Error('Forbidden');
                return values;
            },
        },
        cacheApiService: { clear: async () => {} },
        $tc: (key) => key,
        createNotificationInfo: () => notifications.push('info'),
        createNotificationSuccess: () => notifications.push('success'),
        createNotificationError: () => notifications.push('error'),
        notifications,
    };
    Object.defineProperty(component, 'canClearWakoCache', {
        get: () => definition.computed.canClearWakoCache.call(component),
    });
    for (const [name, method] of Object.entries(definition.methods)) {
        component[name] = method.bind(component);
    }
    return component;
}

test('hidden until global configuration loads, enabled by default', async () => {
    const component = createComponent();
    assert.equal(component.canClearWakoCache, false);
    await component.loadWakoCacheButtonConfig();
    assert.equal(component.canClearWakoCache, true);
});

for (const [name, options] of Object.entries({
    disabled: { values: { 'WakoPluginAdminToolbar.config.adminCacheClearButtonEnabled': false } },
    'no cache permission': { allowed: false },
    'no config permission': { configRead: false },
    'configuration failure': { configError: true },
})) {
    test(`no cache action when ${name}`, async () => {
        const component = createComponent(options);
        component.cacheApiService.clear = () => assert.fail('Unexpected cache request');
        await component.loadWakoCacheButtonConfig();
        assert.equal(component.canClearWakoCache, false);
        await component.onClearWakoCache();
        assert.deepEqual(component.notifications, []);
    });
}

test('prevents duplicate requests and reports success after completion', async () => {
    const component = createComponent();
    await component.loadWakoCacheButtonConfig();
    let finish;
    let calls = 0;
    component.cacheApiService.clear = () => {
        calls++;
        return new Promise((resolvePromise) => { finish = resolvePromise; });
    };
    const pending = component.onClearWakoCache();
    await component.onClearWakoCache();
    assert.equal(calls, 1);
    assert.equal(component.isClearingWakoCache, true);
    assert.deepEqual(component.notifications, ['info']);
    finish();
    await pending;
    assert.equal(component.isClearingWakoCache, false);
    assert.deepEqual(component.notifications, ['info', 'success']);
});

test('reports errors and restores the button', async () => {
    const component = createComponent();
    await component.loadWakoCacheButtonConfig();
    component.cacheApiService.clear = async () => { throw new Error('Network error'); };
    await component.onClearWakoCache();
    assert.equal(component.isClearingWakoCache, false);
    assert.deepEqual(component.notifications, ['info', 'error']);
});

for (const page of ['sw-page', 'sw-meteor-page']) {
    test(`${page} registers its template and loads the configuration`, async () => {
        assert.equal(definitions[page].template, page);
        const component = createComponent({ page });
        definitions[page].created.call(component);
        await new Promise((resolvePromise) => setImmediate(resolvePromise));
        assert.equal(component.canClearWakoCache, true);
        await component.onClearWakoCache();
        assert.deepEqual(component.notifications, ['info', 'success']);
    });

    test(`${page} matches the top-bar styling and preserves the parent block`, () => {
        const template = readFileSync(`${extensionPath}/${page}.html.twig`, 'utf8');
        assert.match(template, /variant="tertiary"/);
        assert.match(template, /size="default"/);
        assert.match(template, /size="var\(--scale-size-20\)"/);
        assert.match(template, /{% parent %}/);
        assert.ok(template.includes(`{% block ${page.replaceAll('-', '_')}_notification_center %}`));
    });
}
