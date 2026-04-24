import template from './wako-admin-toolbar-settings-index.html.twig';

const { Component, Mixin } = Shopware;
const FIELD_NAME = 'wako_admin_toolbar_enabled';
const FEATURE_FIELDS = {
    productLinks: 'wako_admin_toolbar_feature_product_links',
    categoryLinks: 'wako_admin_toolbar_feature_category_links',
    cmsLinks: 'wako_admin_toolbar_feature_cms_links',
    customerContext: 'wako_admin_toolbar_feature_customer_context',
};

Component.register('wako-admin-toolbar-settings-index', {
    template,

    inject: [
        'repositoryFactory',
        'userService',
        'acl',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            userId: null,
            toolbarEnabled: false,
            isLoading: false,
            isSaving: false,
            featurePreferences: {
                productLinks: true,
                categoryLinks: true,
                cmsLinks: true,
                customerContext: true,
            },
            featureConfig: {
                productLinks: true,
                categoryLinks: true,
                cmsLinks: true,
            },
            customerContextConfig: {
                showEmail: false,
                showCustomerNumber: true,
                showRules: false,
            },
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        userRepository() {
            return this.repositoryFactory.create('user');
        },

        canUpdateProfile() {
            return this.acl.can('user.update_profile');
        },

        canUseToolbar() {
            return this.acl.can('wako_admin_toolbar.viewer');
        },

        canEditToggle() {
            return this.canUpdateProfile && this.canUseToolbar;
        },

        toolbarStatusVariant() {
            if (!this.canUseToolbar) {
                return 'info';
            }

            return this.toolbarEnabled ? 'positive' : 'neutral';
        },

        toolbarStatusMessage() {
            if (!this.canUseToolbar) {
                return this.$tc('wako-admin-toolbar.settings.missingPrivilegeNotice');
            }

            return this.toolbarEnabled
                ? this.$tc('wako-admin-toolbar.settings.statusEnabledNotice')
                : this.$tc('wako-admin-toolbar.settings.statusDisabledNotice');
        },

        featureSwitches() {
            return [
                {
                    name: 'productLinks',
                    label: this.$tc('wako-admin-toolbar.settings.featureProductLinks'),
                    helpText: this.getFeatureHelpText(
                        'productLinks',
                        this.$tc('wako-admin-toolbar.settings.featureProductLinksHelp'),
                    ),
                    allowed: this.featureConfig.productLinks && this.acl.can('product:update'),
                },
                {
                    name: 'categoryLinks',
                    label: this.$tc('wako-admin-toolbar.settings.featureCategoryLinks'),
                    helpText: this.getFeatureHelpText(
                        'categoryLinks',
                        this.$tc('wako-admin-toolbar.settings.featureCategoryLinksHelp'),
                    ),
                    allowed: this.featureConfig.categoryLinks && this.acl.can('category:update'),
                },
                {
                    name: 'cmsLinks',
                    label: this.$tc('wako-admin-toolbar.settings.featureCmsLinks'),
                    helpText: this.getFeatureHelpText(
                        'cmsLinks',
                        this.$tc('wako-admin-toolbar.settings.featureCmsLinksHelp'),
                    ),
                    allowed: this.featureConfig.cmsLinks && this.acl.can('cms_page:update'),
                },
                {
                    name: 'customerContext',
                    label: this.$tc('wako-admin-toolbar.settings.featureCustomerContext'),
                    helpText: this.getFeatureHelpText(
                        'customerContext',
                        this.$tc('wako-admin-toolbar.settings.featureCustomerContextHelp'),
                    ),
                    allowed: this.acl.can('customer:read') && this.hasCustomerContextConfigData,
                },
            ];
        },

        hasCustomerContextConfigData() {
            return this.customerContextConfig.showEmail
                || this.customerContextConfig.showCustomerNumber
                || this.customerContextConfig.showRules;
        },
    },

    created() {
        this.loadCurrentUserSettings();
    },

    methods: {
        _getToggleErrorMessage(error) {
            const status = error?.response?.status;
            const statusText = error?.response?.statusText;
            const url = error?.config?.url;
            const apiError = error?.response?.data?.errors?.[0];
            const apiDetail = apiError?.detail || apiError?.title || apiError?.code;
            const responseData = error?.response?.data;
            const responseText = typeof responseData === 'string'
                ? responseData.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim()
                : '';
            const fallbackText = error?.message || responseText;
            const details = [status, statusText, apiDetail || fallbackText, url].filter(Boolean);

            return details.length > 0
                ? `${this.$tc('wako-admin-toolbar.profile.saveError')} (${details.join(' | ')})`
                : this.$tc('wako-admin-toolbar.profile.saveError');
        },

        async loadCurrentUserSettings() {
            this.isLoading = true;

            try {
                await this.loadPluginConfig();

                const currentUser = await this.userService.getUser();
                this.userId = currentUser?.data?.id ?? null;

                if (!this.userId) {
                    throw new Error('Current user id could not be resolved.');
                }

                const user = await this.userRepository.get(this.userId);
                const customFields = user?.customFields ?? {};

                this.toolbarEnabled = customFields[FIELD_NAME] ?? false;
                this.featurePreferences = Object.entries(FEATURE_FIELDS).reduce(
                    (preferences, [feature, fieldName]) => {
                        preferences[feature] = customFields[fieldName] ?? true;

                        return preferences;
                    },
                    { ...this.featurePreferences },
                );
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error('Wako Admin Toolbar settings load failed', error);
                this.createNotificationError({
                    message: this.$tc('wako-admin-toolbar.settings.loadError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        async loadPluginConfig() {
            try {
                const values = await Shopware.Service('systemConfigApiService')
                    .getValues('WakoPluginAdminToolbar.config');

                this.featureConfig = {
                    productLinks: values['WakoPluginAdminToolbar.config.featureProductLinksEnabled'] ?? true,
                    categoryLinks: values['WakoPluginAdminToolbar.config.featureCategoryLinksEnabled'] ?? true,
                    cmsLinks: values['WakoPluginAdminToolbar.config.featureCmsLinksEnabled'] ?? true,
                };
                this.customerContextConfig = {
                    showEmail: values['WakoPluginAdminToolbar.config.customerContextShowEmail'] ?? false,
                    showCustomerNumber: values['WakoPluginAdminToolbar.config.customerContextShowCustomerNumber'] ?? true,
                    showRules: values['WakoPluginAdminToolbar.config.customerContextShowRules'] ?? false,
                };
            } catch (error) {
                // Missing system_config:read should not block the profile page; the backend still enforces config.
                this.customerContextConfig = {
                    showEmail: false,
                    showCustomerNumber: true,
                    showRules: false,
                };
            }
        },

        getFeatureHelpText(feature, defaultHelpText) {
            if (!this.isFeatureGloballyEnabled(feature)) {
                return this.$tc('wako-admin-toolbar.settings.featureGloballyDisabledHelp');
            }

            if (feature === 'customerContext' && !this.hasCustomerContextConfigData) {
                return this.$tc('wako-admin-toolbar.settings.featureCustomerContextConfigDisabledHelp');
            }

            const requiredPrivilege = {
                productLinks: 'product:update',
                categoryLinks: 'category:update',
                cmsLinks: 'cms_page:update',
                customerContext: 'customer:read',
            }[feature];

            if (requiredPrivilege && !this.acl.can(requiredPrivilege)) {
                return this.$tc('wako-admin-toolbar.settings.featureMissingPrivilegeHelp');
            }

            return defaultHelpText;
        },

        isFeatureGloballyEnabled(feature) {
            return {
                productLinks: this.featureConfig.productLinks,
                categoryLinks: this.featureConfig.categoryLinks,
                cmsLinks: this.featureConfig.cmsLinks,
                customerContext: true,
            }[feature] ?? true;
        },

        canEditFeature(feature) {
            return this.canEditToggle
                && this.featureSwitches.some((entry) => entry.name === feature && entry.allowed);
        },

        async onToolbarToggleChange(value) {
            if (!this.canEditToggle || this.isSaving) {
                return;
            }

            const previousValue = this.toolbarEnabled;
            this.toolbarEnabled = value;

            await this.saveSettings(() => {
                this.toolbarEnabled = previousValue;
            });
        },

        async onFeatureToggleChange(feature, value) {
            if (!this.canEditFeature(feature) || this.isSaving) {
                return;
            }

            const previousPreferences = { ...this.featurePreferences };
            this.featurePreferences = {
                ...this.featurePreferences,
                [feature]: value,
            };

            await this.saveSettings(() => {
                this.featurePreferences = previousPreferences;
            });
        },

        async saveSettings(rollback) {
            this.isSaving = true;

            const httpClient = Shopware.Application.getContainer('init').httpClient;
            const loginService = Shopware.Service('loginService');
            const headers = {
                Authorization: `Bearer ${loginService.getToken()}`,
            };

            try {
                const response = await httpClient.patch(
                    '/_action/wako-admin-toolbar/profile-toggle',
                    {
                        enabled: this.toolbarEnabled,
                        features: this.featurePreferences,
                    },
                    { headers },
                );

                if (response?.data?.features) {
                    this.featurePreferences = {
                        ...this.featurePreferences,
                        ...response.data.features,
                    };
                }

                this.createNotificationSuccess({
                    message: this.$tc('wako-admin-toolbar.profile.saveSuccess'),
                });
            } catch (error) {
                rollback();
                // eslint-disable-next-line no-console
                console.error('Wako Admin Toolbar settings save failed', error);
                this.createNotificationError({
                    message: this._getToggleErrorMessage(error),
                });
            } finally {
                this.isSaving = false;
            }
        },
    },
});
