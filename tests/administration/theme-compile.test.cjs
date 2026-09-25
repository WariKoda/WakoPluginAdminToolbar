const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const { test } = require('node:test');
const vm = require('node:vm');

const source = readFileSync(resolve(__dirname, '../../src/Resources/app/administration/src/component/wako-admin-toolbar-theme-compile/index.js'), 'utf8')
    .replace("import template from './wako-admin-toolbar-theme-compile.html.twig';", "const template = '';")
    .replace(
        "import { loadAdminToolbarConfig } from '../../service/admin-toolbar-config';",
        "const loadAdminToolbarConfig = (service) => service.getValues('WakoPluginAdminToolbar.config');",
    );

function createComponent({ denied, enabled, configError = false, post = async () => {} } = {}) {
    let definition;
    const notifications = [];
    const Shopware = {
        Component: { register: (name, config) => { definition = config; } },
        Mixin: { getByName: () => ({}) },
        Data: { Criteria: class {} },
        Application: { getContainer: () => ({ httpClient: { post } }) },
        Service: () => ({ getToken: () => 'test-token' }),
    };
    vm.runInNewContext(source, { Shopware });
    const component = {
        ...definition.data(),
        acl: { can: (privilege) => privilege !== denied },
        systemConfigApiService: { getValues: async () => {
            if (configError) throw new Error('Config failed');
            return { 'WakoPluginAdminToolbar.config.adminThemeCompileButtonEnabled': enabled };
        } },
        $tc: (key) => key,
        createNotificationInfo: () => notifications.push('requested'),
        createNotificationError: () => notifications.push('error'),
        notifications,
    };
    for (const key of ['hasCompilePermission', 'canCompile']) {
        Object.defineProperty(component, key, { get: () => definition.computed[key].call(component) });
    }
    for (const [name, method] of Object.entries(definition.methods)) component[name] = method.bind(component);
    return component;
}

for (const denied of ['theme:update', 'theme:read', 'sales_channel:read', 'system_config:read']) {
    test(`hidden without ${denied}`, async () => {
        const component = createComponent({ denied, post: () => assert.fail('Unexpected request') });
        await component.loadConfig();
        component.openModal();
        component.salesChannelId = 'channel';
        await component.compileTheme();
        assert.equal(component.canCompile, false);
        assert.equal(component.showModal, false);
    });
}
for (const options of [{ enabled: false }, { configError: true }]) {
    test(`hidden for config ${JSON.stringify(options)}`, async () => {
        const component = createComponent(options);
        await component.loadConfig();
        assert.equal(component.canCompile, false);
    });
}

test('requires selection, sends only channel ID and prevents duplicate submissions', async () => {
    let finish;
    let calls = 0;
    const component = createComponent({ post: (url, body, options) => {
        calls++;
        assert.equal(url, '/_action/wako-admin-toolbar/theme-compile/selected-channel');
        assert.equal(Object.keys(body).length, 0);
        assert.equal(options.headers.Authorization, 'Bearer test-token');
        return new Promise((resolvePromise) => { finish = resolvePromise; });
    } });
    await component.loadConfig();
    component.openModal();
    await component.compileTheme();
    assert.equal(calls, 0);
    component.salesChannelId = 'selected-channel';
    const pending = component.compileTheme();
    component.closeModal();
    assert.equal(component.showModal, true);
    await component.compileTheme();
    assert.equal(calls, 1);
    assert.equal(component.isCompiling, true);
    assert.deepEqual(component.notifications, []);
    finish();
    await pending;
    assert.equal(component.isCompiling, false);
    assert.equal(component.showModal, false);
    assert.deepEqual(component.notifications, ['requested']);
});

test('failure preserves selection and allows retry', async () => {
    const component = createComponent({ post: async () => { throw new Error('Compile failed'); } });
    await component.loadConfig();
    component.openModal();
    component.salesChannelId = 'selected-channel';
    await component.compileTheme();
    assert.equal(component.isCompiling, false);
    assert.equal(component.showModal, true);
    assert.equal(component.salesChannelId, 'selected-channel');
    assert.deepEqual(component.notifications, ['error']);
});
