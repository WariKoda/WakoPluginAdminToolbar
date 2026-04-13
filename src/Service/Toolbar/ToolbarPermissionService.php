<?php declare(strict_types=1);

namespace WakoPluginAdminToolbar\Service\Toolbar;

use WakoPluginAdminToolbar\Struct\ToolbarSession;

final class ToolbarPermissionService
{
    public const PRIVILEGE_TOOLBAR_USE = 'wako_admin_toolbar:use';
    public const PRIVILEGE_CLEAR_CACHE = 'system:clear:cache';
    public const PRIVILEGE_PRODUCT_READ = 'product:read';
    public const PRIVILEGE_PRODUCT_UPDATE = 'product:update';
    public const PRIVILEGE_CATEGORY_UPDATE = 'category:update';
    public const PRIVILEGE_CMS_PAGE_UPDATE = 'cms_page:update';
    public const PRIVILEGE_LANDING_PAGE_UPDATE = 'landing_page:update';
    public const PRIVILEGE_CUSTOMER_READ = 'customer:read';
    public const PRIVILEGE_RULE_READ = 'rule:read';

    public function canUseToolbar(ToolbarSession $session): bool
    {
        return $session->isEnabled() && $session->hasPrivilege(self::PRIVILEGE_TOOLBAR_USE);
    }

    public function canClearCache(ToolbarSession $session): bool
    {
        return $session->hasPrivilege(self::PRIVILEGE_CLEAR_CACHE);
    }

    public function canLoadVariants(ToolbarSession $session): bool
    {
        return $session->hasPrivilege(self::PRIVILEGE_PRODUCT_READ);
    }

    public function canViewCustomerContext(ToolbarSession $session): bool
    {
        return $session->hasPrivilege(self::PRIVILEGE_CUSTOMER_READ);
    }

    public function canViewRules(ToolbarSession $session): bool
    {
        return $session->hasPrivilege(self::PRIVILEGE_RULE_READ);
    }

    public function canEditProduct(ToolbarSession $session): bool
    {
        return $session->hasPrivilege(self::PRIVILEGE_PRODUCT_UPDATE);
    }

    public function canEditCategory(ToolbarSession $session): bool
    {
        return $session->hasPrivilege(self::PRIVILEGE_CATEGORY_UPDATE);
    }

    public function canEditCmsPage(ToolbarSession $session): bool
    {
        return $session->hasPrivilege(self::PRIVILEGE_CMS_PAGE_UPDATE);
    }

    public function canEditLandingPage(ToolbarSession $session): bool
    {
        return $session->hasAllPrivileges([
            self::PRIVILEGE_CMS_PAGE_UPDATE,
            self::PRIVILEGE_LANDING_PAGE_UPDATE,
        ]);
    }
}
