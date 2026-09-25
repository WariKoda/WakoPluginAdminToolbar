<?php declare(strict_types=1);

namespace WakoPluginAdminToolbar\Service\Toolbar;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ToolbarThemeCompileService
{
    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        private readonly EntityRepository $salesChannelRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly ThemeService $themeService,
    ) {}

    public function compile(string $salesChannelId, Context $context): void
    {
        if ($this->systemConfigService->get('WakoPluginAdminToolbar.config.adminThemeCompileButtonEnabled') === false) {
            throw new AccessDeniedHttpException();
        }

        if (!Uuid::isValid($salesChannelId)) {
            throw new BadRequestHttpException('Invalid sales channel ID.');
        }

        $criteria = (new Criteria([$salesChannelId]))->addAssociation('themes');
        $salesChannel = $this->salesChannelRepository->search($criteria, $context)->getEntities()->first();

        if (!$salesChannel instanceof SalesChannelEntity
            || $salesChannel->getTypeId() !== Defaults::SALES_CHANNEL_TYPE_STOREFRONT
        ) {
            throw new NotFoundHttpException('Storefront sales channel not found.');
        }

        $themes = $salesChannel->getExtension('themes');
        $theme = $themes instanceof ThemeCollection ? $themes->first() : null;
        if ($theme === null) {
            throw new BadRequestHttpException('No theme assigned to this sales channel.');
        }

        $this->themeService->compileTheme($salesChannelId, $theme->getId(), $context);
    }
}
