<?php declare(strict_types=1);

namespace WakoPluginAdminToolbar\Service\Toolbar;

use League\OAuth2\Server\Exception\OAuthServerException;
use Shopware\Core\Framework\Api\OAuth\SymfonyBearerTokenValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use WakoPluginAdminToolbar\Struct\ToolbarSession;

final class ToolbarSessionResolver
{
    private const FEATURE_FIELDS = [
        'productLinks' => 'wako_admin_toolbar_feature_product_links',
        'categoryLinks' => 'wako_admin_toolbar_feature_category_links',
        'cmsLinks' => 'wako_admin_toolbar_feature_cms_links',
        'customerContext' => 'wako_admin_toolbar_feature_customer_context',
    ];

    /**
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(
        private readonly EntityRepository $userRepository,
        private readonly SymfonyBearerTokenValidator $bearerTokenValidator,
        private readonly RateLimiterFactory $rateLimiterFactory,
        private readonly ToolbarPermissionService $permissionService,
    ) {}

    public function resolve(Request $request): ?ToolbarSession
    {
        $raw = $request->cookies->get('bearerAuth');
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        try {
            /** @var array{access?: string} $auth */
            $auth = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        $jwt = $auth['access'] ?? null;
        if (!is_string($jwt) || $jwt === '') {
            return null;
        }

        $limiter = $this->rateLimiterFactory->create((string) $request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
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

        return new ToolbarSession(
            (string) $user->getId(),
            (bool) ($customFields['wako_admin_toolbar_enabled'] ?? false),
            $user->isAdmin(),
            $this->collectPrivileges($user),
            $user,
            $this->collectFeaturePreferences($customFields),
        );
    }

    public function resolveAuthorized(Request $request): ?ToolbarSession
    {
        $toolbarSession = $this->resolve($request);
        if ($toolbarSession === null || !$this->permissionService->canUseToolbar($toolbarSession)) {
            return null;
        }

        return $toolbarSession;
    }

    private function validateAndExtractUserId(string $jwt): ?string
    {
        $validationRequest = new Request();
        $validationRequest->headers->set('Authorization', 'Bearer ' . $jwt);

        try {
            $this->bearerTokenValidator->validateAuthorization($validationRequest);
        } catch (OAuthServerException) {
            return null;
        }

        $userId = $validationRequest->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_USER_ID);

        if (!is_string($userId) || !Uuid::isValid($userId)) {
            return null;
        }

        return $userId;
    }

    /**
     * @param array<string, mixed> $customFields
     *
     * @return array<string, bool>
     */
    private function collectFeaturePreferences(array $customFields): array
    {
        $preferences = [];

        foreach (self::FEATURE_FIELDS as $feature => $fieldName) {
            $preferences[$feature] = (bool) ($customFields[$fieldName] ?? true);
        }

        return $preferences;
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
}
