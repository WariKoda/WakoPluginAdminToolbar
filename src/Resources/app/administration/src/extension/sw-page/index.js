import template from './sw-page.html.twig';
import meteorTemplate from './sw-meteor-page.html.twig';
import { loadAdminToolbarConfig } from '../../service/admin-toolbar-config';

const { Component, Mixin } = Shopware;

const cacheButtonBehavior = {

    inject: [
        'acl',
        'cacheApiService',
        'systemConfigApiService',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isClearingWakoCache: false,
            wakoCacheButtonEnabled: false,
        };
    },

    computed: {
        canClearWakoCache() {
            return this.wakoCacheButtonEnabled && this.acl.can('system:clear:cache');
        },
    },

    created() {
        this.loadWakoCacheButtonConfig();
    },

    methods: {
        async loadWakoCacheButtonConfig() {
            this.wakoCacheButtonEnabled = false;

            if (!this.acl.can('system:clear:cache') || !this.acl.can('system_config:read')) {
                return;
            }

            try {
                const values = await loadAdminToolbarConfig(this.systemConfigApiService);
                this.wakoCacheButtonEnabled = values['WakoPluginAdminToolbar.config.adminCacheClearButtonEnabled'] ?? true;
            } catch {
                this.wakoCacheButtonEnabled = false;
            }
        },

        async onClearWakoCache() {
            if (!this.canClearWakoCache || this.isClearingWakoCache) {
                return;
            }

            this.isClearingWakoCache = true;
            this.createNotificationInfo({
                message: this.$tc('wako-admin-toolbar.cacheClear.started'),
            });

            try {
                await this.cacheApiService.clear();
                this.createNotificationSuccess({
                    message: this.$tc('wako-admin-toolbar.cacheClear.success'),
                });
            } catch {
                this.createNotificationError({
                    message: this.$tc('wako-admin-toolbar.cacheClear.error'),
                });
            } finally {
                this.isClearingWakoCache = false;
            }
        },
    },
};

Component.override('sw-page', {
    ...cacheButtonBehavior,
    template,
});

Component.override('sw-meteor-page', {
    ...cacheButtonBehavior,
    template: meteorTemplate,
});
