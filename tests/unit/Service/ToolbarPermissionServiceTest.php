<?php declare(strict_types=1);

namespace WakoPluginAdminToolbar\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\UserEntity;
use WakoPluginAdminToolbar\Service\Toolbar\ToolbarPermissionService;
use WakoPluginAdminToolbar\Struct\ToolbarSession;

final class ToolbarPermissionServiceTest extends TestCase
{
    private ToolbarPermissionService $permissionService;

    protected function setUp(): void
    {
        $this->permissionService = new ToolbarPermissionService();
    }

    public function testToolbarRequiresOptInAndBasePrivilege(): void
    {
        static::assertFalse($this->permissionService->canUseToolbar($this->session(false, [
            ToolbarPermissionService::PRIVILEGE_TOOLBAR_USE,
        ])));
        static::assertFalse($this->permissionService->canUseToolbar($this->session(true)));
        static::assertTrue($this->permissionService->canUseToolbar($this->session(true, [
            ToolbarPermissionService::PRIVILEGE_TOOLBAR_USE,
        ])));
    }

    public function testAdminStillRequiresToolbarOptIn(): void
    {
        static::assertFalse($this->permissionService->canUseToolbar($this->session(false, [], true)));
        static::assertTrue($this->permissionService->canUseToolbar($this->session(true, [], true)));
    }

    public function testLandingPageRequiresBothPrivileges(): void
    {
        static::assertFalse($this->permissionService->canEditLandingPage($this->session(true, [
            ToolbarPermissionService::PRIVILEGE_CMS_PAGE_UPDATE,
        ])));
        static::assertFalse($this->permissionService->canEditLandingPage($this->session(true, [
            ToolbarPermissionService::PRIVILEGE_LANDING_PAGE_UPDATE,
        ])));
        static::assertTrue($this->permissionService->canEditLandingPage($this->session(true, [
            ToolbarPermissionService::PRIVILEGE_CMS_PAGE_UPDATE,
            ToolbarPermissionService::PRIVILEGE_LANDING_PAGE_UPDATE,
        ])));
    }

    public function testFeaturePreferenceIsAnAdditionalPermissionGate(): void
    {
        $session = $this->session(
            true,
            [ToolbarPermissionService::PRIVILEGE_PRODUCT_UPDATE],
            false,
            ['productLinks' => false],
        );

        static::assertFalse($this->permissionService->canEditProduct($session));
    }

    /**
     * @param array<string> $privileges
     * @param array<string, bool> $featurePreferences
     */
    private function session(
        bool $enabled,
        array $privileges = [],
        bool $isAdmin = false,
        array $featurePreferences = [],
    ): ToolbarSession {
        $userId = Uuid::randomHex();
        $user = new UserEntity();
        $user->setId($userId);

        return new ToolbarSession(
            $userId,
            $enabled,
            $isAdmin,
            array_fill_keys($privileges, true),
            $user,
            $featurePreferences,
        );
    }
}
