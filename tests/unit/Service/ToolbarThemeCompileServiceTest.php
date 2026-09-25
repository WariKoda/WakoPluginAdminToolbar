<?php declare(strict_types=1);

namespace WakoPluginAdminToolbar\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeEntity;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use WakoPluginAdminToolbar\Controller\AdminToolbarThemeController;
use WakoPluginAdminToolbar\Service\Toolbar\ToolbarThemeCompileService;

final class ToolbarThemeCompileServiceTest extends TestCase
{
    public function testRouteRequiresCorePrivileges(): void
    {
        $method = new \ReflectionMethod(AdminToolbarThemeController::class, 'compile');
        $route = $method->getAttributes(Route::class)[0]->getArguments();
        static::assertSame(['theme:update', 'theme:read', 'sales_channel:read'], $route['defaults']['_acl']);
        static::assertSame(['POST'], $route['methods']);
    }

    public function testDisabledConfigRejectsBeforeRepositoryOrCompile(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(static::never())->method('search');
        $config = $this->createMock(SystemConfigService::class);
        $config->method('get')->willReturn(false);
        $themeService = $this->createMock(ThemeService::class);
        $themeService->expects(static::never())->method('compileTheme');
        $this->expectException(AccessDeniedHttpException::class);
        (new ToolbarThemeCompileService($repository, $config, $themeService))->compile(Uuid::randomHex(), Context::createDefaultContext());
    }

    public function testInvalidIdRejectsBeforeRepository(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(static::never())->method('search');
        $this->expectException(BadRequestHttpException::class);
        (new ToolbarThemeCompileService($repository, $this->createMock(SystemConfigService::class), $this->createMock(ThemeService::class)))
            ->compile('invalid', Context::createDefaultContext());
    }

    public function testCompilesAssignedThemeWithoutReassignment(): void
    {
        $channel = $this->channel();
        $theme = new ThemeEntity();
        $theme->setId(Uuid::randomHex());
        $channel->addExtension('themes', new ThemeCollection([$theme]));
        $context = Context::createDefaultContext();
        $themeService = $this->createMock(ThemeService::class);
        $themeService->expects(static::once())->method('compileTheme')->with($channel->getId(), $theme->getId(), $context);
        $themeService->expects(static::never())->method('assignTheme');
        $this->service($channel, $themeService, $context)->compile($channel->getId(), $context);
    }

    public function testMissingThemeIsRejected(): void
    {
        $channel = $this->channel();
        $context = Context::createDefaultContext();
        $themeService = $this->createMock(ThemeService::class);
        $themeService->expects(static::never())->method('compileTheme');
        $this->expectException(BadRequestHttpException::class);
        $this->service($channel, $themeService, $context)->compile($channel->getId(), $context);
    }

    public function testNonStorefrontIsRejected(): void
    {
        $channel = $this->channel();
        $channel->setTypeId(Uuid::randomHex());
        $context = Context::createDefaultContext();
        $themeService = $this->createMock(ThemeService::class);
        $themeService->expects(static::never())->method('compileTheme');
        $this->expectException(NotFoundHttpException::class);
        $this->service($channel, $themeService, $context)->compile($channel->getId(), $context);
    }

    private function channel(): SalesChannelEntity
    {
        $channel = new SalesChannelEntity();
        $channel->setId(Uuid::randomHex());
        $channel->setTypeId(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        return $channel;
    }

    private function service(SalesChannelEntity $channel, ThemeService $themeService, Context $context): ToolbarThemeCompileService
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(static::once())->method('search')->with(
            static::callback(static fn (Criteria $criteria): bool => $criteria->getIds() === [$channel->getId()] && $criteria->hasAssociation('themes')),
            $context,
        )->willReturn(new EntitySearchResult('sales_channel', 1, new SalesChannelCollection([$channel]), null, new Criteria(), $context));

        return new ToolbarThemeCompileService($repository, $this->createMock(SystemConfigService::class), $themeService);
    }
}
