<?php declare(strict_types=1);

namespace WakoPluginAdminToolbar;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use WakoPluginAdminToolbar\Installer\CustomFieldInstaller;

class WakoPluginAdminToolbar extends Plugin
{
    public function install(InstallContext $installContext): void
    {
        $this->getInstaller()->install($installContext->getContext());
    }

    public function update(UpdateContext $updateContext): void
    {
        $this->getInstaller()->install($updateContext->getContext());

        if (version_compare($updateContext->getCurrentPluginVersion(), '2.0.0', '<')) {
            $this->resetPrivacyDefaults();
        }
    }

    public function enrichPrivileges(): array
    {
        return [
            'wako_admin_toolbar.viewer' => [
                'wako_admin_toolbar:use',
            ],
        ];
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $this->getInstaller()->uninstall($uninstallContext->getContext());
    }

    private function resetPrivacyDefaults(): void
    {
        if ($this->container === null) {
            throw new \LogicException('The plugin container is not available.');
        }

        /** @var SystemConfigService $systemConfigService */
        $systemConfigService = $this->container->get(SystemConfigService::class);
        $systemConfigService->set('WakoPluginAdminToolbar.config.customerContextShowEmail', false);
        $systemConfigService->set('WakoPluginAdminToolbar.config.customerContextShowRules', false);
    }

    private function getInstaller(): CustomFieldInstaller
    {
        if ($this->container === null) {
            throw new \LogicException('The plugin container is not available.');
        }

        /** @var EntityRepository<CustomFieldSetCollection> $repository */
        $repository = $this->container->get('custom_field_set.repository');

        return new CustomFieldInstaller($repository);
    }
}
