<?php declare(strict_types=1);

namespace WakoPluginAdminToolbar\Service\Toolbar;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

final class ToolbarVariantService
{
    public function __construct(
        private readonly EntityRepository $productRepository,
    ) {}

    /**
     * @return array<int, array{id: string, label: string}>
     */
    public function loadVariants(string $parentId, Context $context): array
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('parentId', $parentId))
            ->addAssociation('options.group')
            ->addSorting(new FieldSorting('productNumber', FieldSorting::ASCENDING))
            ->setLimit(30)
            ->setTitle('wako-admin-toolbar::variants');

        $variants = [];
        $products = $this->productRepository->search($criteria, $context)->getEntities();

        foreach ($products as $product) {
            if (!$product instanceof ProductEntity) {
                continue;
            }

            $options = [];
            foreach ($product->getOptions() ?? [] as $option) {
                if (!$option instanceof PropertyGroupOptionEntity) {
                    continue;
                }

                $group = $option->getGroup();
                $options[] = [
                    'groupName' => (string) ($group?->getTranslation('name') ?? $group?->getName() ?? ''),
                    'name' => (string) ($option->getTranslation('name') ?? $option->getName() ?? ''),
                ];
            }

            usort($options, static fn (array $a, array $b): int => $a['groupName'] <=> $b['groupName']);

            $labelParts = array_values(array_filter(array_map(
                static fn (array $option): string => $option['name'],
                $options,
            )));

            $variants[] = [
                'id' => (string) $product->getId(),
                'label' => $labelParts !== []
                    ? implode(' / ', $labelParts)
                    : (string) $product->getProductNumber(),
            ];
        }

        return $variants;
    }
}
