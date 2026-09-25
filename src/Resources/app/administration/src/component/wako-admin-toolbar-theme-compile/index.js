import template from './wako-admin-toolbar-theme-compile.html.twig';
import { loadAdminToolbarConfig } from '../../service/admin-toolbar-config';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('wako-admin-toolbar-theme-compile', {
    template,

    inject: ['acl', 'systemConfigApiService'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            enabled: false,
            showModal: false,
            salesChannelId: null,
            isCompiling: false,
        };
    },

    computed: {
        hasCompilePermission() {
            return ['theme:update', 'theme:read', 'sales_channel:read']
                .every((privilege) => this.acl.can(privilege));
        },

        canCompile() {
            return this.enabled && this.hasCompilePermission;
        },

        salesChannelCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('typeId', Shopware.Defaults.storefrontSalesChannelTypeId));
            criteria.addAssociation('themes');
            criteria.addFilter(Criteria.not('AND', [Criteria.equals('themes.id', null)]));
            criteria.addSorting(Criteria.sort('name', 'ASC'));

            return criteria;
        },
    },

    created() {
        this.loadConfig();
    },

    methods: {
        async loadConfig() {
            this.enabled = false;
            if (!this.hasCompilePermission || !this.acl.can('system_config:read')) return;

            try {
                const values = await loadAdminToolbarConfig(this.systemConfigApiService);
                this.enabled = values['WakoPluginAdminToolbar.config.adminThemeCompileButtonEnabled'] ?? true;
            } catch {
                this.enabled = false;
            }
        },

        openModal() {
            if (!this.canCompile || this.isCompiling) return;
            this.salesChannelId = null;
            this.showModal = true;
        },

        closeModal() {
            if (!this.isCompiling) this.showModal = false;
        },

        async compileTheme() {
            if (!this.canCompile || !this.salesChannelId || this.isCompiling) return;

            this.isCompiling = true;
            try {
                const httpClient = Shopware.Application.getContainer('init').httpClient;
                const loginService = Shopware.Service('loginService');
                await httpClient.post(
                    `/_action/wako-admin-toolbar/theme-compile/${encodeURIComponent(this.salesChannelId)}`,
                    {},
                    { headers: { Authorization: `Bearer ${loginService.getToken()}` } },
                );
                this.createNotificationInfo({
                    message: this.$tc('wako-admin-toolbar.themeCompile.requested'),
                });
                this.showModal = false;
            } catch {
                this.createNotificationError({
                    message: this.$tc('wako-admin-toolbar.themeCompile.error'),
                });
            } finally {
                this.isCompiling = false;
            }
        },
    },
});
