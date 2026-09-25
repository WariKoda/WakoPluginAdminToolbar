const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const { test } = require('node:test');
const vm = require('node:vm');

const source = readFileSync(resolve(__dirname, '../../src/Resources/app/administration/src/service/admin-toolbar-config.js'), 'utf8')
    .replace('export function loadAdminToolbarConfig', 'function loadAdminToolbarConfig');

function createLoader() {
    const context = {};
    vm.runInNewContext(`${source}\nthis.loadAdminToolbarConfig = loadAdminToolbarConfig;`, context);
    return context.loadAdminToolbarConfig;
}

test('page header buttons share a single config request', async () => {
    const loadAdminToolbarConfig = createLoader();
    let calls = 0;
    const service = {
        async getValues(domain) {
            calls++;
            assert.equal(domain, 'WakoPluginAdminToolbar.config');
            return { key: true };
        },
    };

    const [first, second] = await Promise.all([
        loadAdminToolbarConfig(service),
        loadAdminToolbarConfig(service),
    ]);
    await loadAdminToolbarConfig(service);

    assert.equal(calls, 1);
    assert.equal(first, second);
});

test('failed requests are not cached', async () => {
    const loadAdminToolbarConfig = createLoader();
    let calls = 0;
    const service = {
        async getValues() {
            calls++;
            if (calls === 1) throw new Error('Forbidden');
            return { key: true };
        },
    };

    await assert.rejects(loadAdminToolbarConfig(service));
    assert.deepEqual(await loadAdminToolbarConfig(service), { key: true });
    assert.equal(calls, 2);
});
