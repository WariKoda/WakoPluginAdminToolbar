<?php declare(strict_types=1);

namespace WakoPluginAdminToolbar;

use WakoPluginAdminToolbar\Installer\CustomFieldInstaller;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

class WakoPluginAdminToolbar extends Plugin
{
    public function install(InstallContext $installContext): void
    {
        $this->getInstaller()->install($installContext->getContext());
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

    private function getInstaller(): CustomFieldInstaller
    {
        /** @var \Shopware\Core\Framework\DataAbstractionLayer\EntityRepository $repo */
        $repo = $this->container->get('custom_field_set.repository');

        return new CustomFieldInstaller($repo);
    }
}
