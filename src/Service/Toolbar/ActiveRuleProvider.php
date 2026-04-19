<?php declare(strict_types=1);

namespace WakoPluginAdminToolbar\Service\Toolbar;

use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

final class ActiveRuleProvider
{
    public function __construct(
        private readonly EntityRepository $ruleRepository,
    ) {}

    /**
     * @param array<string> $ruleIds
     *
     * @return array<int, array{id: string, name: string, priority: int}>
     */
    public function loadAssignedRules(array $ruleIds, Context $context): array
    {
        if ($ruleIds === []) {
            return [];
        }

        $criteria = (new Criteria($ruleIds))
            ->addSorting(new FieldSorting('priority', FieldSorting::DESCENDING))
            ->addSorting(new FieldSorting('name', FieldSorting::ASCENDING))
            ->setTitle('wako-admin-toolbar::customer-context-rules');

        $rules = $this->ruleRepository->search($criteria, $context)->getEntities();
        $activeRules = [];

        foreach ($rules as $rule) {
            if (!$rule instanceof RuleEntity) {
                continue;
            }

            $activeRules[] = [
                'id' => (string) $rule->getId(),
                'name' => (string) $rule->getName(),
                'priority' => (int) $rule->getPriority(),
            ];
        }

        return $activeRules;
    }
}
