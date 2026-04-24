<?php declare(strict_types=1);

namespace WakoPluginAdminToolbar\Service\Toolbar;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use WakoPluginAdminToolbar\Struct\ToolbarSession;

final class ToolbarCapabilitiesBuilder
{
    public function __construct(
        private readonly ToolbarPermissionService $permissionService,
        private readonly SystemConfigService $systemConfigService,
    ) {}

    /**
     * @return array<string, bool>
     */
    public function build(ToolbarSession $session, ?string $salesChannelId = null): array
    {
        $productLinksEnabled = $this->isFeatureEnabled('featureProductLinksEnabled', $salesChannelId);
        $categoryLinksEnabled = $this->isFeatureEnabled('featureCategoryLinksEnabled', $salesChannelId);
        $cmsLinksEnabled = $this->isFeatureEnabled('featureCmsLinksEnabled', $salesChannelId);

        return [
            'canClearCache'          => $this->permissionService->canClearCache($session),
            'canLoadVariants'        => $productLinksEnabled
                                        && $this->permissionService->canLoadVariants($session),
            'canViewCustomerContext' => $this->permissionService->canViewCustomerContext($session)
                                        && $this->hasAnyCustomerContextData($salesChannelId),
            'canViewRules'           => $this->permissionService->canViewRules($session)
                                        && $this->systemConfigService->getBool(
                                            'WakoPluginAdminToolbar.config.customerContextShowRules',
                                            $salesChannelId,
                                        ),
            'canEditProduct'         => $productLinksEnabled
                                        && $this->permissionService->canEditProduct($session),
            'canEditCategory'        => $categoryLinksEnabled
                                        && $this->permissionService->canEditCategory($session),
            'canEditCmsPage'         => $cmsLinksEnabled
                                        && $this->permissionService->canEditCmsPage($session),
            'canEditLandingPage'     => $cmsLinksEnabled
                                        && $this->permissionService->canEditLandingPage($session),
        ];
    }

    private function isFeatureEnabled(string $feature, ?string $salesChannelId): bool
    {
        $key = \sprintf('WakoPluginAdminToolbar.config.%s', $feature);
        $value = $this->systemConfigService->get($key, $salesChannelId);

        return $value === null || $value === true;
    }

    private function hasAnyCustomerContextData(?string $salesChannelId): bool
    {
        $keys = [
            'WakoPluginAdminToolbar.config.customerContextShowEmail',
            'WakoPluginAdminToolbar.config.customerContextShowCustomerNumber',
            'WakoPluginAdminToolbar.config.customerContextShowRules',
        ];

        foreach ($keys as $key) {
            if ($this->systemConfigService->getBool($key, $salesChannelId)) {
                return true;
            }
        }

        return false;
    }
}
