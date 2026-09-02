<?php declare(strict_types=1);

namespace WakoPluginAdminToolbar\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use WakoPluginAdminToolbar\Service\Toolbar\ToolbarRenderContextFactory;

final class ToolbarRenderContextFactoryTest extends TestCase
{
    public function testCreatesValidatedContext(): void
    {
        $entityId = Uuid::randomHex();
        $parentId = Uuid::randomHex();
        $cmsPageId = Uuid::randomHex();
        $request = new Request([
            'pageType' => 'product',
            'entityId' => $entityId,
            'parentId' => $parentId,
            'cmsPageId' => $cmsPageId,
            'routeName' => 'frontend.detail.page',
            'locale' => 'de-DE',
        ]);

        self::assertSame([
            'pageType' => 'product',
            'entityId' => $entityId,
            'parentId' => $parentId,
            'cmsPageId' => $cmsPageId,
            'routeName' => 'frontend.detail.page',
            'locale' => 'de-DE',
        ], (new ToolbarRenderContextFactory())->create($request));
    }

    public function testRejectsInvalidContextValues(): void
    {
        $request = new Request([
            'pageType' => 'script',
            'entityId' => 'not-a-uuid',
            'parentId' => ['invalid'],
            'cmsPageId' => '<script>',
            'routeName' => '<script>alert(1)</script>',
            'locale' => '../de-DE',
        ]);

        self::assertSame([
            'pageType' => 'generic',
            'entityId' => null,
            'parentId' => null,
            'cmsPageId' => null,
            'routeName' => null,
            'locale' => 'en-GB',
        ], (new ToolbarRenderContextFactory())->create($request));
    }

    public function testUsesGenericDefaultsForMissingContext(): void
    {
        self::assertSame([
            'pageType' => 'generic',
            'entityId' => null,
            'parentId' => null,
            'cmsPageId' => null,
            'routeName' => null,
            'locale' => 'en-GB',
        ], (new ToolbarRenderContextFactory())->create(new Request()));
    }
}
