import template from "./sw-admin-menu.html.twig";
import "./sw-admin-menu.scss";
import {
    getConfiguredSalesChannelId,
    isLogoStorefrontLinkEnabled,
    resolveStorefrontUrl,
} from "./storefront-link.helper";

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.override("sw-admin-menu", {
    template,

    inject: {
        repositoryFactory: {
            from: "repositoryFactory",
            default: null,
        },
        domainLinkService: {
            from: "domainLinkService",
            default: null,
        },
        systemConfigApiService: {
            from: "systemConfigApiService",
            default: null,
        },
    },

    data() {
        return {
            storefrontUrl: null,
        };
    },

    computed: {
        salesChannelRepository() {
            if (!this.repositoryFactory) {
                return null;
            }

            return this.repositoryFactory.create("sales_channel");
        },
    },

    created() {
        this.loadStorefrontLink();
    },

    methods: {
        async loadStorefrontLink() {
            try {
                const systemConfigApiService =
                    this.systemConfigApiService ||
                    Shopware.Service("systemConfigApiService");
                const domainLinkService =
                    this.domainLinkService ||
                    Shopware.Service("domainLinkService");

                if (
                    !systemConfigApiService ||
                    !domainLinkService ||
                    !this.salesChannelRepository
                ) {
                    return;
                }

                const configValues = await systemConfigApiService.getValues(
                    "WakoPluginAdminToolbar.config",
                );

                if (!isLogoStorefrontLinkEnabled(configValues)) {
                    this.storefrontUrl = null;
                    return;
                }

                const configuredId = getConfiguredSalesChannelId(configValues);

                if (configuredId) {
                    const configuredChannel =
                        await this.loadSalesChannelById(configuredId);
                    const configuredUrl = resolveStorefrontUrl(
                        configuredChannel,
                        domainLinkService,
                    );

                    if (configuredUrl) {
                        this.storefrontUrl = configuredUrl;
                        return;
                    }
                }

                const fallbackChannel =
                    await this.loadFallbackStorefrontSalesChannel();
                this.storefrontUrl = resolveStorefrontUrl(
                    fallbackChannel,
                    domainLinkService,
                );
            } catch {
                this.storefrontUrl = null;
            }
        },

        createSalesChannelCriteria() {
            const criteria = new Criteria(1, 1);
            criteria.addAssociation("type");
            criteria.addAssociation("domains");

            return criteria;
        },

        async loadSalesChannelById(salesChannelId) {
            try {
                return await this.salesChannelRepository.get(
                    salesChannelId,
                    Shopware.Context.api,
                    this.createSalesChannelCriteria(),
                );
            } catch {
                return null;
            }
        },

        async loadFallbackStorefrontSalesChannel() {
            const criteria = this.createSalesChannelCriteria();
            criteria.addFilter(
                Criteria.equals(
                    "typeId",
                    Shopware.Defaults.storefrontSalesChannelTypeId,
                ),
            );
            criteria.addFilter(Criteria.equals("active", true));
            criteria.addFilter(
                Criteria.not("AND", [Criteria.equals("domains.id", null)]),
            );
            criteria.addSorting(Criteria.sort("name", "ASC"));

            const salesChannels = await this.salesChannelRepository.search(
                criteria,
                Shopware.Context.api,
            );

            return salesChannels.first() || null;
        },
    },
});
