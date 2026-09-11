import {
    getConfiguredSalesChannelId,
    isLogoStorefrontLinkEnabled,
    resolveStorefrontUrl,
} from "./storefront-link.helper";

describe("storefront-link.helper", () => {
    describe("isLogoStorefrontLinkEnabled", () => {
        it("defaults to enabled when the config key is missing", () => {
            expect(isLogoStorefrontLinkEnabled({})).toBe(true);
        });

        it("is disabled only when the config value is false", () => {
            expect(
                isLogoStorefrontLinkEnabled({
                    "WakoPluginAdminToolbar.config.logoStorefrontLinkEnabled": false,
                }),
            ).toBe(false);
        });
    });

    describe("getConfiguredSalesChannelId", () => {
        it("returns a configured sales channel id", () => {
            expect(
                getConfiguredSalesChannelId({
                    "WakoPluginAdminToolbar.config.logoStorefrontSalesChannelId":
                        "sales-channel-id",
                }),
            ).toBe("sales-channel-id");
        });

        it("returns null for an empty selection", () => {
            expect(
                getConfiguredSalesChannelId({
                    "WakoPluginAdminToolbar.config.logoStorefrontSalesChannelId":
                        "",
                }),
            ).toBeNull();
        });
    });

    describe("resolveStorefrontUrl", () => {
        it("returns the domain from domainLinkService", () => {
            const domainLinkService = {
                getDomainLink: jest.fn(() => "https://shop.example"),
            };

            expect(
                resolveStorefrontUrl({ id: "sc-1" }, domainLinkService),
            ).toBe("https://shop.example");
            expect(domainLinkService.getDomainLink).toHaveBeenCalledWith({
                id: "sc-1",
            });
        });

        it("returns null when no sales channel or domain is available", () => {
            expect(
                resolveStorefrontUrl(null, {
                    getDomainLink: () => "https://shop.example",
                }),
            ).toBeNull();
            expect(
                resolveStorefrontUrl(
                    { id: "sc-1" },
                    { getDomainLink: () => null },
                ),
            ).toBeNull();
        });
    });
});
