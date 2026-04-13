<?php declare(strict_types=1);

namespace WakoPluginAdminToolbar\Service\Toolbar;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Exception;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use WakoPluginAdminToolbar\Struct\ToolbarSession;

final class ToolbarSessionResolver
{
    public function __construct(
        private readonly EntityRepository $userRepository,
        private readonly Configuration $jwtConfiguration,
        private readonly RateLimiterFactory $rateLimiterFactory,
        private readonly ToolbarPermissionService $permissionService,
    ) {}

    public function resolve(Request $request): ?ToolbarSession
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

        return new ToolbarSession(
            (string) $user->getId(),
            (bool) ($customFields['wako_admin_toolbar_enabled'] ?? false),
            $user->isAdmin(),
            $this->collectPrivileges($user),
            $user,
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
