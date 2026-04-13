import AdminToolbarPlugin from './js/admin-toolbar/admin-toolbar.plugin';

window.PluginManager.register(
    'AdminToolbar',
    AdminToolbarPlugin,
    '[data-admin-toolbar]'
);
