import template from './wako-admin-toolbar-plugin-config-hint.html.twig';
import './wako-admin-toolbar-plugin-config-hint.scss';

const { Component } = Shopware;

Component.register('wako-admin-toolbar-plugin-config-hint', {
    template,

    props: {
        value: {
            type: String,
            required: false,
            default: '',
        },
        snippetPrefix: {
            type: String,
            required: false,
            default: '',
        },
        routeName: {
            type: String,
            required: false,
            default: 'wako.admin.toolbar.settings.index',
        },
    },

    methods: {
        openSettingsPage() {
            this.$router.push({ name: this.routeName });
        },
    },
});
