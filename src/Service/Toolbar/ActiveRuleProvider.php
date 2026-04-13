<?php declare(strict_types=1);

namespace WakoPluginAdminToolbar\Service\Toolbar;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;

final class ActiveRuleProvider
{
    public function __construct(
        private readonly EntityRepository $ruleRepository,
        private readonly Connection $connection,
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

        $assignedRuleIds = $this->loadCoreAssignedRuleIds($ruleIds);
        if ($assignedRuleIds === []) {
            return [];
        }

        $criteria = (new Criteria($assignedRuleIds))
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

    /**
     * @param array<string> $ruleIds
     *
     * @return array<string>
     */
    private function loadCoreAssignedRuleIds(array $ruleIds): array
    {
        $assignedRuleIds = $this->connection->fetchFirstColumn(
            <<<'SQL'
SELECT DISTINCT LOWER(HEX(`rule_id`)) AS `id`
FROM `product_price`
WHERE `rule_id` IN (:ids)

UNION

SELECT DISTINCT LOWER(HEX(`rule_id`)) AS `id`
FROM `shipping_method_price`
WHERE `rule_id` IN (:ids)

UNION

SELECT DISTINCT LOWER(HEX(`calculation_rule_id`)) AS `id`
FROM `shipping_method_price`
WHERE `calculation_rule_id` IN (:ids)

UNION

SELECT DISTINCT LOWER(HEX(`availability_rule_id`)) AS `id`
FROM `shipping_method`
WHERE `availability_rule_id` IN (:ids)

UNION

SELECT DISTINCT LOWER(HEX(`availability_rule_id`)) AS `id`
FROM `payment_method`
WHERE `availability_rule_id` IN (:ids)

UNION

SELECT DISTINCT LOWER(HEX(`rule_id`)) AS `id`
FROM `promotion_persona_rule`
WHERE `rule_id` IN (:ids)

UNION

SELECT DISTINCT LOWER(HEX(`rule_id`)) AS `id`
FROM `promotion_order_rule`
WHERE `rule_id` IN (:ids)

UNION

SELECT DISTINCT LOWER(HEX(`rule_id`)) AS `id`
FROM `promotion_cart_rule`
WHERE `rule_id` IN (:ids)

UNION

SELECT DISTINCT LOWER(HEX(`rule_id`)) AS `id`
FROM `promotion_discount_rule`
WHERE `rule_id` IN (:ids)

UNION

SELECT DISTINCT LOWER(HEX(`rule_id`)) AS `id`
FROM `promotion_setgroup_rule`
WHERE `rule_id` IN (:ids)

UNION

SELECT DISTINCT LOWER(HEX(`rule_id`)) AS `id`
FROM `flow_sequence`
WHERE `rule_id` IN (:ids)

UNION

SELECT DISTINCT LOWER(HEX(`availability_rule_id`)) AS `id`
FROM `tax_provider`
WHERE `availability_rule_id` IN (:ids)
SQL,
            ['ids' => Uuid::fromHexToBytesList($ruleIds)],
            ['ids' => ArrayParameterType::BINARY],
        );

        if ($assignedRuleIds === []) {
            return [];
        }

        $assignedRuleIds = array_map(static fn ($id): string => (string) $id, $assignedRuleIds);
        $assignedRuleLookup = array_flip($assignedRuleIds);

        return array_values(array_filter(
            $ruleIds,
            static fn (string $ruleId): bool => isset($assignedRuleLookup[$ruleId]),
        ));
    }
}
