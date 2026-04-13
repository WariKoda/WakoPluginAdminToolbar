<?php declare(strict_types=1);

namespace WakoPluginAdminToolbar\Installer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class CustomFieldInstaller
{
    public const SET_NAME   = 'wako_admin_toolbar';
    public const FIELD_NAME = 'wako_admin_toolbar_enabled';

    public function __construct(
        private readonly EntityRepository $customFieldSetRepository,
    ) {}

    public function install(Context $context): void
    {
        // Idempotent — skip if the set already exists
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::SET_NAME));

        if ($this->customFieldSetRepository->searchIds($criteria, $context)->getTotal() > 0) {
            return;
        }

        $this->customFieldSetRepository->create([
            [
                'name'   => self::SET_NAME,
                'global' => false,
                'config' => [
                    'label' => [
                        'en-GB' => 'Wako Admin Toolbar',
                        'de-DE' => 'Wako Admin Toolbar',
                    ],
                ],
                'relations' => [
                    ['entityName' => 'user'],
                ],
                'customFields' => [
                    [
                        'name'   => self::FIELD_NAME,
                        'type'   => CustomFieldTypes::BOOL,
                        'config' => [
                            'label' => [
                                'en-GB' => 'Admin toolbar enabled',
                                'de-DE' => 'Admin-Toolbar aktiviert',
                            ],
                            'componentName'   => 'sw-field',
                            'customFieldType' => 'checkbox',
                            'customFieldPosition' => 1,
                        ],
                    ],
                ],
            ],
        ], $context);
    }

    public function uninstall(Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::SET_NAME));

        $ids = $this->customFieldSetRepository->searchIds($criteria, $context)->getIds();

        if (empty($ids)) {
            return;
        }

        $this->customFieldSetRepository->delete(
            array_map(static fn (string $id) => ['id' => $id], $ids),
            $context,
        );
    }
}
