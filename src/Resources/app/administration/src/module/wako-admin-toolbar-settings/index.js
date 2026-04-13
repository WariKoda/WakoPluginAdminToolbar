import './page/wako-admin-toolbar-settings-index';

Shopware.Module.register('wako-admin-toolbar-settings', {
    type: 'plugin',
    name: 'wako-admin-toolbar.settings.moduleTitle',
    title: 'wako-admin-toolbar.settings.moduleTitle',
    description: 'wako-admin-toolbar.settings.moduleDescription',
    color: '#116fff',
    icon: 'regular-cog',

    routes: {
        index: {
            component: 'wako-admin-toolbar-settings-index',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'user.update_profile',
            },
        },
    },

    settingsItem: {
        group: 'plugins',
        to: 'wako.admin.toolbar.settings.index',
        icon: 'regular-cog',
        privilege: 'user.update_profile',
    },
});
