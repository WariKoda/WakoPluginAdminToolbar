<?php declare(strict_types=1);

namespace WakoPluginAdminToolbar\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\User\UserEntity;
use WakoPluginAdminToolbar\Service\Toolbar\ToolbarCapabilitiesBuilder;
use WakoPluginAdminToolbar\Service\Toolbar\ToolbarPermissionService;
use WakoPluginAdminToolbar\Struct\ToolbarSession;

final class ToolbarCapabilitiesBuilderTest extends TestCase
{
    public function testCustomerContextRequiresAtLeastOneGloballyEnabledDataField(): void
    {
        $builder = $this->builder([]);
        $capabilities = $builder->build($this->session([
            ToolbarPermissionService::PRIVILEGE_CUSTOMER_READ,
        ]));

        static::assertFalse($capabilities['canViewCustomerContext']);
        static::assertFalse($builder->hasAnyCustomerContextData());
    }

    public function testCustomerContextIsAvailableWhenADataFieldIsEnabled(): void
    {
        $builder = $this->builder([
            'WakoPluginAdminToolbar.config.customerContextShowCustomerNumber' => true,
        ]);
        $capabilities = $builder->build($this->session([
            ToolbarPermissionService::PRIVILEGE_CUSTOMER_READ,
        ]));

        static::assertTrue($capabilities['canViewCustomerContext']);
        static::assertTrue($builder->hasAnyCustomerContextData());
    }

    public function testGlobalProductSwitchDisablesProductCapabilities(): void
    {
        $builder = $this->builder([
            'WakoPluginAdminToolbar.config.featureProductLinksEnabled' => false,
        ]);
        $capabilities = $builder->build($this->session([
            ToolbarPermissionService::PRIVILEGE_PRODUCT_READ,
            ToolbarPermissionService::PRIVILEGE_PRODUCT_UPDATE,
        ]));

        static::assertFalse($capabilities['canLoadVariants']);
        static::assertFalse($capabilities['canEditProduct']);
    }

    /**
     * @param array<string, bool> $config
     */
    private function builder(array $config): ToolbarCapabilitiesBuilder
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('get')->willReturnCallback(
            static fn (string $key): ?bool => $config[$key] ?? null,
        );
        $systemConfigService->method('getBool')->willReturnCallback(
            static fn (string $key): bool => $config[$key] ?? false,
        );

        return new ToolbarCapabilitiesBuilder(new ToolbarPermissionService(), $systemConfigService);
    }

    /**
     * @param array<string> $privileges
     */
    private function session(array $privileges): ToolbarSession
    {
        $userId = Uuid::randomHex();
        $user = new UserEntity();
        $user->setId($userId);

        return new ToolbarSession(
            $userId,
            true,
            false,
            array_fill_keys($privileges, true),
            $user,
            [
                'productLinks' => true,
                'customerContext' => true,
            ],
        );
    }
}
