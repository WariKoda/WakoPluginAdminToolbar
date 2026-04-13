import template from './wako-admin-toolbar-settings-index.html.twig';

const { Component, Mixin } = Shopware;
const FIELD_NAME = 'wako_admin_toolbar_enabled';

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
                const currentUser = await this.userService.getUser();
                this.userId = currentUser?.data?.id ?? null;

                if (!this.userId) {
                    throw new Error('Current user id could not be resolved.');
                }

                const user = await this.userRepository.get(this.userId);
                this.toolbarEnabled = user?.customFields?.[FIELD_NAME] ?? false;
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

        async onToolbarToggleChange(value) {
            if (!this.canEditToggle || this.isSaving) {
                return;
            }

            const previousValue = this.toolbarEnabled;
            this.toolbarEnabled = value;
            this.isSaving = true;

            const httpClient = Shopware.Application.getContainer('init').httpClient;
            const loginService = Shopware.Service('loginService');
            const headers = {
                Authorization: `Bearer ${loginService.getToken()}`,
            };

            try {
                await httpClient.patch(
                    '/_action/wako-admin-toolbar/profile-toggle',
                    { enabled: value },
                    { headers },
                );

                this.createNotificationSuccess({
                    message: this.$tc('wako-admin-toolbar.profile.saveSuccess'),
                });
            } catch (error) {
                this.toolbarEnabled = previousValue;
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
