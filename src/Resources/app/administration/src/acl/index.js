Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'additional_permissions',
    parent: null,
    key: 'wako_admin_toolbar',
    roles: {
        viewer: {
            privileges: [
                'wako_admin_toolbar:use',
            ],
            dependencies: [],
        },
    },
});
