export function isLogoStorefrontLinkEnabled(configValues) {
    return (
        configValues?.[
            "WakoPluginAdminToolbar.config.logoStorefrontLinkEnabled"
        ] !== false
    );
}

export function getConfiguredSalesChannelId(configValues) {
    const salesChannelId =
        configValues?.[
            "WakoPluginAdminToolbar.config.logoStorefrontSalesChannelId"
        ];

    if (typeof salesChannelId !== "string" || salesChannelId === "") {
        return null;
    }

    return salesChannelId;
}

export function resolveStorefrontUrl(salesChannel, domainLinkService) {
    if (!salesChannel || !domainLinkService) {
        return null;
    }

    const url = domainLinkService.getDomainLink(salesChannel);

    if (typeof url !== "string" || url === "") {
        return null;
    }

    return url;
}
