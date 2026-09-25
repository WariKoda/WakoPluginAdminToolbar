<?php declare(strict_types=1);

namespace WakoPluginAdminToolbar\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use WakoPluginAdminToolbar\Service\Toolbar\ToolbarThemeCompileService;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class AdminToolbarThemeController
{
    public function __construct(private readonly ToolbarThemeCompileService $themeCompileService) {}

    #[Route(
        path: '/api/_action/wako-admin-toolbar/theme-compile/{salesChannelId}',
        name: 'api.action.wako-admin-toolbar.theme-compile',
        defaults: ['_acl' => ['theme:update', 'theme:read', 'sales_channel:read']],
        methods: ['POST'],
    )]
    public function compile(string $salesChannelId, Context $context): JsonResponse
    {
        $this->themeCompileService->compile($salesChannelId, $context);

        $response = new JsonResponse(['requested' => true], 202);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
