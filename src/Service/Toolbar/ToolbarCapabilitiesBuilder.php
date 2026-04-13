<?php declare(strict_types=1);

namespace WakoPluginAdminToolbar\Service\Toolbar;

use WakoPluginAdminToolbar\Struct\ToolbarSession;

final class ToolbarCapabilitiesBuilder
{
    public function __construct(
        private readonly ToolbarPermissionService $permissionService,
    ) {}

    /**
     * @return array<string, bool>
     */
    public function build(ToolbarSession $session): array
    {
        return [
            'canClearCache' => $this->permissionService->canClearCache($session),
            'canLoadVariants' => $this->permissionService->canLoadVariants($session),
            'canViewCustomerContext' => $this->permissionService->canViewCustomerContext($session),
            'canViewRules' => $this->permissionService->canViewRules($session),
            'canEditProduct' => $this->permissionService->canEditProduct($session),
            'canEditCategory' => $this->permissionService->canEditCategory($session),
            'canEditCmsPage' => $this->permissionService->canEditCmsPage($session),
            'canEditLandingPage' => $this->permissionService->canEditLandingPage($session),
        ];
    }
}
