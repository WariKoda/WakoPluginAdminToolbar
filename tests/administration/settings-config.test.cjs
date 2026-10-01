const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const { test } = require('node:test');
const vm = require('node:vm');

const path = resolve(__dirname, '../../src/Resources/app/administration/src/module/wako-admin-toolbar-settings/page/wako-admin-toolbar-settings-index/index.js');
const source = readFileSync(path, 'utf8').replace(/^import template[^\n]+/, 'const template = "settings";');
let definition;
let service;
vm.runInNewContext(source, {
    console,
    Shopware: {
        Component: { register: (name, config) => { definition = config; } },
        Mixin: { getByName: () => ({}) },
        Service: () => service,
    },
});

function createComponent(configRead) {
    const component = {
        ...definition.data(),
        acl: { can: (privilege) => configRead && privilege === 'system_config:read' },
        $tc: (key) => key,
        userService: { getUser: async () => ({ data: { id: 'current-user' } }) },
        userRepository: { get: async (id) => {
            assert.equal(id, 'current-user');
            return { customFields: { wako_admin_toolbar_enabled: true, wako_admin_toolbar_feature_product_links: false } };
        } },
        createNotificationError: () => assert.fail('Unexpected error notification'),
    };
    for (const [name, method] of Object.entries(definition.methods)) component[name] = method.bind(component);
    return component;
}

test('personal preferences load without a forbidden system configuration request', async () => {
    service = { getValues: () => assert.fail('Unexpected system configuration request') };
    const component = createComponent(false);
    await component.loadCurrentUserSettings();
    assert.equal(component.userId, 'current-user');
    assert.equal(component.toolbarEnabled, true);
    assert.equal(component.featurePreferences.productLinks, false);
    assert.equal(component.isLoading, false);
    assert.equal(component.customerContextConfig.showEmail, false);
    assert.equal(component.customerContextConfig.showRules, false);
});

test('configuration readers receive the global feature switches', async () => {
    let requests = 0;
    service = { getValues: async (domain) => {
        assert.equal(domain, 'WakoPluginAdminToolbar.config');
        requests++;
        return {
            'WakoPluginAdminToolbar.config.featureProductLinksEnabled': false,
            'WakoPluginAdminToolbar.config.customerContextShowEmail': true,
        };
    } };
    const component = createComponent(true);
    await component.loadPluginConfig();
    assert.equal(requests, 1);
    assert.equal(component.featureConfig.productLinks, false);
    assert.equal(component.customerContextConfig.showEmail, true);
});

test('configuration failure does not block personal preferences', async () => {
    service = { getValues: async () => { throw new Error('Network error'); } };
    const component = createComponent(true);
    await component.loadCurrentUserSettings();
    assert.equal(component.toolbarEnabled, true);
    assert.equal(component.isLoading, false);
    assert.equal(component.customerContextConfig.showEmail, false);
});
