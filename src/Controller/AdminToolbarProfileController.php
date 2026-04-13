<?php declare(strict_types=1);

namespace WakoPluginAdminToolbar\Controller;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class AdminToolbarProfileController
{
    private const FIELD_NAME = 'wako_admin_toolbar_enabled';

    public function __construct(
        private readonly EntityRepository $userRepository,
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

        $user = $context->scope(Context::SYSTEM_SCOPE, fn (Context $context): ?UserEntity => $this->userRepository
            ->search(new Criteria([$userId]), $context)
            ->getEntities()
            ->first());

        if (!$user instanceof UserEntity) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $customFields = $user->getCustomFields() ?? [];
        $customFields[self::FIELD_NAME] = $enabled;

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
        ]);
    }
}
