<?php declare(strict_types=1);

namespace WakoPluginAdminToolbar\Controller;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Exception;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use Shopware\Administration\Framework\Routing\AdministrationRouteScope;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Single endpoint that replaces the three JS API calls (toolbar-session +
 * _info/me + user/{id}) with one server-side request.
 *
 * The bearerAuth cookie is readable server-side here because this route lives
 * under /admin — the same path the browser scoped the cookie to.
 *
 * The JWT signature is verified using the same HMAC-SHA256 key (APP_SECRET)
 * that Shopware uses for its own OAuth tokens.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [AdministrationRouteScope::ID]])]
class AdminToolbarAuthController
{
    private const PRIVILEGE_TOOLBAR_USE = 'wako_admin_toolbar:use';
    private const PRIVILEGE_CLEAR_CACHE = 'system:clear:cache';
    private const PRIVILEGE_PRODUCT_READ = 'product:read';
    private const PRIVILEGE_PRODUCT_UPDATE = 'product:update';
    private const PRIVILEGE_CATEGORY_UPDATE = 'category:update';
    private const PRIVILEGE_CMS_PAGE_UPDATE = 'cms_page:update';
    private const PRIVILEGE_LANDING_PAGE_UPDATE = 'landing_page:update';
    private const PRIVILEGE_CUSTOMER_READ = 'customer:read';
    private const PRIVILEGE_RULE_READ = 'rule:read';

    public function __construct(
        private readonly EntityRepository $userRepository,
        private readonly Configuration $jwtConfiguration,
        private readonly RateLimiterFactory $rateLimiterFactory,
        private readonly SalesChannelContextServiceInterface $salesChannelContextService,
        private readonly EntityRepository $ruleRepository,
        private readonly Connection $connection,
        private readonly EntityRepository $productRepository,
        private readonly CacheClearer $cacheClearer,
    ) {}

    #[Route(
        path: '/admin/toolbar-auth',
        name: 'wako.admin.toolbar.auth',
        defaults: ['auth_required' => false],
        methods: ['GET'],
    )]
    public function auth(Request $request): JsonResponse
    {
        $toolbarSession = $this->resolveAuthorizedToolbarSession($request);
        if ($toolbarSession === null) {
            return $this->noContent();
        }

        $response = new JsonResponse([
            'enabled' => true,
            'permissions' => $this->buildCapabilities($toolbarSession),
        ]);

        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    #[Route(
        path: '/admin/toolbar-clear-cache',
        name: 'wako.admin.toolbar.clear_cache',
        defaults: ['auth_required' => false],
        methods: ['DELETE'],
    )]
    public function clearCache(Request $request): Response
    {
        $toolbarSession = $this->resolveAuthorizedToolbarSession($request);
        if ($toolbarSession === null || !$this->hasPrivilege($toolbarSession, self::PRIVILEGE_CLEAR_CACHE)) {
            return new Response('', Response::HTTP_FORBIDDEN);
        }

        $this->cacheClearer->clear();

        $response = new Response('', Response::HTTP_NO_CONTENT);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    #[Route(
        path: '/admin/toolbar-variants/{parentId}',
        name: 'wako.admin.toolbar.variants',
        defaults: ['auth_required' => false],
        methods: ['GET'],
    )]
    public function variants(Request $request, string $parentId): JsonResponse
    {
        $toolbarSession = $this->resolveAuthorizedToolbarSession($request);
        if ($toolbarSession === null || !$this->hasPrivilege($toolbarSession, self::PRIVILEGE_PRODUCT_READ) || !Uuid::isValid($parentId)) {
            return new JsonResponse(['variants' => []], Response::HTTP_FORBIDDEN);
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('parentId', $parentId))
            ->addAssociation('options.group')
            ->addSorting(new FieldSorting('productNumber', FieldSorting::ASCENDING))
            ->setLimit(30)
            ->setTitle('wako-admin-toolbar::variants');

        $variants = [];
        $products = $this->productRepository->search($criteria, Context::createDefaultContext())->getEntities();

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
                'id' => $product->getId(),
                'label' => $labelParts !== []
                    ? implode(' / ', $labelParts)
                    : $product->getProductNumber(),
            ];
        }

        $response = new JsonResponse(['variants' => $variants]);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    #[Route(
        path: '/admin/toolbar-customer-context',
        name: 'wako.admin.toolbar.customer_context',
        defaults: ['auth_required' => false],
        methods: ['GET'],
    )]
    public function customerContext(Request $request): JsonResponse
    {
        $toolbarSession = $this->resolveAuthorizedToolbarSession($request);
        if ($toolbarSession === null || !$this->hasPrivilege($toolbarSession, self::PRIVILEGE_CUSTOMER_READ)) {
            return new JsonResponse(null, Response::HTTP_FORBIDDEN);
        }

        if (!$request->hasSession()) {
            return $this->noContent();
        }

        $session = $request->getSession();

        $salesChannelId = (string) $session->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, '');
        if ($salesChannelId === '') {
            return $this->noContent();
        }

        $contextToken = (string) $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN, '');
        if ($contextToken === '') {
            return $this->noContent();
        }

        try {
            $salesChannelContext = $this->salesChannelContextService->get(
                new SalesChannelContextServiceParameters($salesChannelId, $contextToken)
            );
        } catch (\Throwable) {
            return $this->noContent();
        }

        $customer = $salesChannelContext->getCustomer();
        if ($customer === null || $customer->getGuest()) {
            return $this->noContent();
        }

        $displayName = trim($customer->getFirstName() . ' ' . $customer->getLastName());

        $response = new JsonResponse([
            'customer' => [
                'id'             => $customer->getId(),
                'displayName'    => $displayName,
                'firstName'      => $customer->getFirstName(),
                'lastName'       => $customer->getLastName(),
                'customerNumber' => $customer->getCustomerNumber(),
                'email'          => $customer->getEmail(),
            ],
            'activeRules' => $this->hasPrivilege($toolbarSession, self::PRIVILEGE_RULE_READ)
                ? $this->loadActiveRules(
                    $salesChannelContext->getRuleIds(),
                    $salesChannelContext->getContext(),
                )
                : [],
        ]);

        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private function noContent(): JsonResponse
    {
        $response = new JsonResponse(null, Response::HTTP_NO_CONTENT);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    /**
     * Parses and cryptographically validates the JWT using the same HMAC-SHA256
     * configuration Shopware uses for its OAuth tokens (APP_SECRET).
     *
     * Returns the user UUID from the `sub` claim, or null if validation fails.
     */
    private function validateAndExtractUserId(string $jwt): ?string
    {
        try {
            /** @var UnencryptedToken $token */
            $token = $this->jwtConfiguration->parser()->parse($jwt);

            $constraints = $this->jwtConfiguration->validationConstraints();
            $this->jwtConfiguration->validator()->assert($token, ...$constraints);
        } catch (Exception | RequiredConstraintsViolated) {
            return null;
        }

        $sub = $token->claims()->get('sub');

        if (!is_string($sub) || !Uuid::isValid($sub)) {
            return null;
        }

        return $sub;
    }

    /**
     * @return array{enabled: bool, isAdmin: bool, privileges: array<string, bool>, user: UserEntity}|null
     */
    private function resolveToolbarSession(Request $request): ?array
    {
        $limiter = $this->rateLimiterFactory->create((string) $request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            return null;
        }

        $raw = $request->cookies->get('bearerAuth');
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        try {
            /** @var array{access?: string, expiry?: int|float} $auth */
            $auth = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        $expiry = (int) ($auth['expiry'] ?? 0);
        if ($expiry > 0 && (int) round(microtime(true) * 1000) > $expiry) {
            return null;
        }

        $jwt = $auth['access'] ?? null;
        if (!is_string($jwt) || $jwt === '') {
            return null;
        }

        $userId = $this->validateAndExtractUserId($jwt);
        if ($userId === null) {
            return null;
        }

        $criteria = (new Criteria([$userId]))
            ->addAssociation('aclRoles');

        $user = $this->userRepository
            ->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();

        if (!$user instanceof UserEntity) {
            return null;
        }

        $customFields = $user->getCustomFields() ?? [];

        return [
            'enabled' => (bool) ($customFields['wako_admin_toolbar_enabled'] ?? false),
            'isAdmin' => $user->isAdmin(),
            'privileges' => $this->collectPrivileges($user),
            'user' => $user,
        ];
    }

    /**
     * @return array{enabled: bool, isAdmin: bool, privileges: array<string, bool>, user: UserEntity}|null
     */
    private function resolveAuthorizedToolbarSession(Request $request): ?array
    {
        $toolbarSession = $this->resolveToolbarSession($request);
        if ($toolbarSession === null || $toolbarSession['enabled'] !== true) {
            return null;
        }

        if (!$this->hasPrivilege($toolbarSession, self::PRIVILEGE_TOOLBAR_USE)) {
            return null;
        }

        return $toolbarSession;
    }

    /**
     * @return array<string, bool>
     */
    private function collectPrivileges(UserEntity $user): array
    {
        $privileges = [];

        foreach ($user->getAclRoles() ?? [] as $aclRole) {
            foreach ($aclRole->getPrivileges() as $privilege) {
                $privileges[(string) $privilege] = true;
            }
        }

        return $privileges;
    }

    /**
     * @param array{enabled: bool, isAdmin: bool, privileges: array<string, bool>, user: UserEntity} $toolbarSession
     */
    private function hasPrivilege(array $toolbarSession, string $privilege): bool
    {
        return $toolbarSession['isAdmin'] === true || isset($toolbarSession['privileges'][$privilege]);
    }

    /**
     * @param array{enabled: bool, isAdmin: bool, privileges: array<string, bool>, user: UserEntity} $toolbarSession
     * @param array<string> $privileges
     */
    private function hasAllPrivileges(array $toolbarSession, array $privileges): bool
    {
        foreach ($privileges as $privilege) {
            if (!$this->hasPrivilege($toolbarSession, $privilege)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{enabled: bool, isAdmin: bool, privileges: array<string, bool>, user: UserEntity} $toolbarSession
     *
     * @return array<string, bool>
     */
    private function buildCapabilities(array $toolbarSession): array
    {
        return [
            'canClearCache' => $this->hasPrivilege($toolbarSession, self::PRIVILEGE_CLEAR_CACHE),
            'canLoadVariants' => $this->hasPrivilege($toolbarSession, self::PRIVILEGE_PRODUCT_READ),
            'canViewCustomerContext' => $this->hasPrivilege($toolbarSession, self::PRIVILEGE_CUSTOMER_READ),
            'canViewRules' => $this->hasPrivilege($toolbarSession, self::PRIVILEGE_RULE_READ),
            'canEditProduct' => $this->hasPrivilege($toolbarSession, self::PRIVILEGE_PRODUCT_UPDATE),
            'canEditCategory' => $this->hasPrivilege($toolbarSession, self::PRIVILEGE_CATEGORY_UPDATE),
            'canEditCmsPage' => $this->hasPrivilege($toolbarSession, self::PRIVILEGE_CMS_PAGE_UPDATE),
            'canEditLandingPage' => $this->hasAllPrivileges($toolbarSession, [
                self::PRIVILEGE_CMS_PAGE_UPDATE,
                self::PRIVILEGE_LANDING_PAGE_UPDATE,
            ]),
        ];
    }

    /**
     * @param array<string> $ruleIds
     *
     * @return array<int, array{id: string, name: string, priority: int}>
     */
    private function loadActiveRules(array $ruleIds, Context $context): array
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
                'id' => $rule->getId(),
                'name' => $rule->getName(),
                'priority' => $rule->getPriority(),
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
