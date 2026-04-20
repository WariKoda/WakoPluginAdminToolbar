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
        return [
            'canClearCache'          => $this->permissionService->canClearCache($session),
            'canLoadVariants'        => $this->permissionService->canLoadVariants($session),
            'canViewCustomerContext' => $this->permissionService->canViewCustomerContext($session)
                                        && $this->hasAnyCustomerContextData($salesChannelId),
            'canViewRules'           => $this->permissionService->canViewRules($session),
            'canEditProduct'         => $this->permissionService->canEditProduct($session),
            'canEditCategory'        => $this->permissionService->canEditCategory($session),
            'canEditCmsPage'         => $this->permissionService->canEditCmsPage($session),
            'canEditLandingPage'     => $this->permissionService->canEditLandingPage($session),
        ];
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
