<?php declare(strict_types=1);

namespace WakoPluginAdminToolbar\Controller;

use Shopware\Administration\Framework\Routing\AdministrationRouteScope;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use WakoPluginAdminToolbar\Service\Toolbar\ToolbarCapabilitiesBuilder;
use WakoPluginAdminToolbar\Service\Toolbar\ToolbarCustomerContextProvider;
use WakoPluginAdminToolbar\Service\Toolbar\ToolbarPermissionService;
use WakoPluginAdminToolbar\Service\Toolbar\ToolbarSessionResolver;
use WakoPluginAdminToolbar\Service\Toolbar\ToolbarVariantService;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [AdministrationRouteScope::ID]])]
class AdminToolbarAuthController
{
    public function __construct(
        private readonly ToolbarSessionResolver $toolbarSessionResolver,
        private readonly ToolbarPermissionService $permissionService,
        private readonly ToolbarCapabilitiesBuilder $capabilitiesBuilder,
        private readonly ToolbarVariantService $toolbarVariantService,
        private readonly ToolbarCustomerContextProvider $customerContextProvider,
        private readonly CacheClearer $cacheClearer,
    ) {}

    #[Route(
        path: '/admin/toolbar-auth',
        name: 'wako.admin.toolbar.auth',
        defaults: ['auth_required' => false],
        methods: ['GET'],
    )]
    public function auth(Request $request): Response
    {
        $toolbarSession = $this->toolbarSessionResolver->resolveAuthorized($request);
        if ($toolbarSession === null) {
            return $this->response(Response::HTTP_NO_CONTENT);
        }

        $salesChannelId = $request->hasSession()
            ? (string) $request->getSession()->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, '')
            : '';

        return $this->jsonResponse([
            'enabled' => true,
            'permissions' => $this->capabilitiesBuilder->build($toolbarSession, $salesChannelId ?: null),
        ]);
    }

    #[Route(
        path: '/admin/toolbar-clear-cache',
        name: 'wako.admin.toolbar.clear_cache',
        defaults: ['auth_required' => false],
        methods: ['DELETE'],
    )]
    public function clearCache(Request $request): Response
    {
        if (!$this->isSameOrigin($request)) {
            return $this->response(Response::HTTP_FORBIDDEN);
        }

        $toolbarSession = $this->toolbarSessionResolver->resolveAuthorized($request);
        if ($toolbarSession === null || !$this->permissionService->canClearCache($toolbarSession)) {
            return $this->response(Response::HTTP_FORBIDDEN);
        }

        $this->cacheClearer->clear();

        return $this->response(Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/admin/toolbar-variants/{parentId}',
        name: 'wako.admin.toolbar.variants',
        defaults: ['auth_required' => false],
        methods: ['GET'],
    )]
    public function variants(Request $request, string $parentId): JsonResponse
    {
        $toolbarSession = $this->toolbarSessionResolver->resolveAuthorized($request);
        if ($toolbarSession === null
            || !$this->permissionService->canLoadVariants($toolbarSession)
            || !Uuid::isValid($parentId)
        ) {
            return $this->jsonResponse(['variants' => []], Response::HTTP_FORBIDDEN);
        }

        return $this->jsonResponse([
            'variants' => $this->toolbarVariantService->loadVariants($parentId, Context::createDefaultContext()),
        ]);
    }

    #[Route(
        path: '/admin/toolbar-customer-context',
        name: 'wako.admin.toolbar.customer_context',
        defaults: ['auth_required' => false],
        methods: ['GET'],
    )]
    public function customerContext(Request $request): Response
    {
        $toolbarSession = $this->toolbarSessionResolver->resolveAuthorized($request);
        if ($toolbarSession === null || !$this->permissionService->canViewCustomerContext($toolbarSession)) {
            return $this->response(Response::HTTP_FORBIDDEN);
        }

        $customerContext = $this->customerContextProvider->load(
            $request,
            $toolbarSession,
            $this->permissionService->canViewRules($toolbarSession),
        );

        if ($customerContext === null) {
            return $this->response(Response::HTTP_NO_CONTENT);
        }

        return $this->jsonResponse($customerContext);
    }

    /**
     * Defense-in-depth for state-changing storefront-accessible endpoints.
     *
     * Accepts either Origin or Referer when scheme/host/port match exactly.
     * Requests without both headers are rejected.
     */
    private function isSameOrigin(Request $request): bool
    {
        $source = $request->headers->get('Origin') ?: $request->headers->get('Referer');
        if (!is_string($source) || $source === '') {
            return false;
        }

        $sourceParts = parse_url($source);
        if ($sourceParts === false) {
            return false;
        }

        $sourceScheme = $sourceParts['scheme'] ?? null;
        $sourceHost = $sourceParts['host'] ?? null;
        if (!is_string($sourceScheme) || !is_string($sourceHost) || $sourceScheme === '' || $sourceHost === '') {
            return false;
        }

        $requestPort = $request->getPort();
        $sourcePort = isset($sourceParts['port']) ? (int) $sourceParts['port'] : null;

        if ($sourcePort === null) {
            $sourcePort = $sourceScheme === 'https' ? 443 : ($sourceScheme === 'http' ? 80 : 0);
        }

        return $sourceScheme === $request->getScheme()
            && strtolower($sourceHost) === strtolower($request->getHost())
            && $sourcePort === $requestPort;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function jsonResponse(array $data, int $status = 200): JsonResponse
    {
        $response = new JsonResponse($data, $status);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private function response(int $status): Response
    {
        $response = new Response('', $status);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
