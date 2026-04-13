<?php declare(strict_types=1);

namespace WakoPluginAdminToolbar\Subscriber;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Storefront\Page\GenericPageLoadedEvent;
use Shopware\Storefront\Page\LandingPage\LandingPageLoadedEvent;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedEvent;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ToolbarPageDataSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            GenericPageLoadedEvent::class    => 'onGenericPageLoaded',
            ProductPageLoadedEvent::class    => 'onProductPageLoaded',
            NavigationPageLoadedEvent::class => 'onNavigationPageLoaded',
            LandingPageLoadedEvent::class    => 'onLandingPageLoaded',
        ];
    }

    public function onGenericPageLoaded(GenericPageLoadedEvent $event): void
    {
        // Baseline for all pages — specific events will overwrite this
        $event->getPage()->addExtension('wakoAdminToolbar', new ArrayStruct([
            'pageType' => 'generic',
            'entityId' => null,
            'parentId' => null,
            'cmsPageId' => null,
        ]));
    }

    public function onProductPageLoaded(ProductPageLoadedEvent $event): void
    {
        $product = $event->getPage()->getProduct();

        // parentId is null for simple products; for variants it points to the
        // parent which is the correct admin edit target.
        // cmsPageId is set when the product (or its parent) has a custom layout.
        $event->getPage()->addExtension('wakoAdminToolbar', new ArrayStruct([
            'pageType' => 'product',
            'entityId' => $product->getId(),
            'parentId' => $product->getParentId(),
            'cmsPageId' => $event->getPage()->getCmsPage()?->getId(),
        ]));
    }

    public function onNavigationPageLoaded(NavigationPageLoadedEvent $event): void
    {
        $cmsPage = $event->getPage()->getCmsPage();

        $event->getPage()->addExtension('wakoAdminToolbar', new ArrayStruct([
            'pageType' => 'navigation',
            'entityId' => $event->getPage()->getNavigationId(),
            'parentId' => null,
            'cmsPageId' => $cmsPage?->getId(),
        ]));
    }

    public function onLandingPageLoaded(LandingPageLoadedEvent $event): void
    {
        $landingPage = $event->getPage()->getLandingPage();

        $event->getPage()->addExtension('wakoAdminToolbar', new ArrayStruct([
            'pageType' => 'landingPage',
            'entityId' => $landingPage?->getId(),
            'parentId' => null,
            'cmsPageId' => $landingPage?->getCmsPageId(),
        ]));
    }
}
