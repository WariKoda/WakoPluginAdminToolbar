<?php declare(strict_types=1);

namespace WakoPluginAdminToolbar\Controller;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class AdminToolbarProfileController
{
    private const FIELD_NAME = 'wako_admin_toolbar_enabled';

    private const FEATURE_FIELDS = [
        'productLinks' => 'wako_admin_toolbar_feature_product_links',
        'categoryLinks' => 'wako_admin_toolbar_feature_category_links',
        'cmsLinks' => 'wako_admin_toolbar_feature_cms_links',
        'customerContext' => 'wako_admin_toolbar_feature_customer_context',
    ];

    public function __construct(
        private readonly EntityRepository $userRepository,
        private readonly SystemConfigService $systemConfigService,
    ) {}

    #[Route(
        path: '/api/_action/wako-admin-toolbar/profile-toggle',
        name: 'api.action.wako-admin-toolbar.profile-toggle',
        defaults: ['_acl' => ['user_change_me', 'wako_admin_toolbar:use']],
        methods: ['PATCH'],
    )]
    public function updateOwnToggle(Request $request, Context $context): JsonResponse
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource || !$source->getUserId()) {
            return new JsonResponse(null, Response::HTTP_FORBIDDEN);
        }

        $userId = $source->getUserId();
        $enabled = $request->request->getBoolean('enabled');
        $features = $request->request->all('features');
        $features = \is_array($features) ? $features : [];

        $user = $context->scope(Context::SYSTEM_SCOPE, fn (Context $context): ?UserEntity => $this->userRepository
            ->search(new Criteria([$userId]), $context)
            ->getEntities()
            ->first());

        if (!$user instanceof UserEntity) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $customFields = $user->getCustomFields() ?? [];
        $customFields[self::FIELD_NAME] = $enabled;

        foreach (self::FEATURE_FIELDS as $feature => $fieldName) {
            if (!\array_key_exists($feature, $features)) {
                continue;
            }

            $customFields[$fieldName] = $this->isFeatureAllowed($feature, $context) && (bool) $features[$feature];
        }

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($userId, $customFields): void {
            $this->userRepository->update([
                [
                    'id' => $userId,
                    'customFields' => $customFields,
                ],
            ], $context);
        });

        return new JsonResponse([
            'enabled' => $enabled,
            'features' => $this->extractFeaturePreferences($customFields),
        ]);
    }

    /**
     * @param array<string, mixed> $customFields
     *
     * @return array<string, bool>
     */
    private function extractFeaturePreferences(array $customFields): array
    {
        $preferences = [];

        foreach (self::FEATURE_FIELDS as $feature => $fieldName) {
            $preferences[$feature] = (bool) ($customFields[$fieldName] ?? true);
        }

        return $preferences;
    }

    private function isFeatureAllowed(string $feature, Context $context): bool
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            return false;
        }

        return match ($feature) {
            'productLinks' => $this->isGlobalFeatureEnabled('featureProductLinksEnabled')
                && ($source->isAdmin() || $source->isAllowed('product:update')),
            'categoryLinks' => $this->isGlobalFeatureEnabled('featureCategoryLinksEnabled')
                && ($source->isAdmin() || $source->isAllowed('category:update')),
            'cmsLinks' => $this->isGlobalFeatureEnabled('featureCmsLinksEnabled')
                && ($source->isAdmin() || $source->isAllowed('cms_page:update')),
            'customerContext' => ($source->isAdmin() || $source->isAllowed('customer:read'))
                && $this->hasAnyCustomerContextData(),
            default => false,
        };
    }

    private function isGlobalFeatureEnabled(string $feature): bool
    {
        $key = \sprintf('WakoPluginAdminToolbar.config.%s', $feature);
        $value = $this->systemConfigService->get($key);

        return $value === null || $value === true;
    }

    private function hasAnyCustomerContextData(): bool
    {
        $keys = [
            'WakoPluginAdminToolbar.config.customerContextShowEmail',
            'WakoPluginAdminToolbar.config.customerContextShowCustomerNumber',
            'WakoPluginAdminToolbar.config.customerContextShowRules',
        ];

        foreach ($keys as $key) {
            if ($this->systemConfigService->getBool($key)) {
                return true;
            }
        }

        return false;
    }
}
