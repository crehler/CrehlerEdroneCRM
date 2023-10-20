<?php

declare(strict_types=1);

namespace Crehler\EdroneCrm\Subscriber;

use Crehler\EdroneCrm\Service\EdroneService;
use Crehler\EdroneCrm\Struct\EdroneProductCategoryStruct;
use Shopware\Core\Content\Cms\Events\CmsPageLoadedEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterRegisterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;

readonly class StorefrontSubscriber implements EventSubscriberInterface
{
    public function __construct(private EdroneService $edroneService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => 'onProductPageLoaded',
            CmsPageLoadedEvent::class => 'onCmsPageLoaded',
            NewsletterRegisterEvent::class => 'onNewsletterRegister',
            NewsletterConfirmEvent::class => 'onNewsletterConfirm',
        ];
    }

    public function onProductPageLoaded(ProductPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $product = $page->getProduct();
        $edroneProductCategory = $this->edroneService->createProductCategoryStruct(
            $page->getHeader()->getNavigation()->getTree(),
            $product
        );

        if ($edroneProductCategory instanceof EdroneProductCategoryStruct) {
            $product->addExtension('edroneProductCategory', $edroneProductCategory);
        }
    }

    public function onCmsPageLoaded(CmsPageLoadedEvent $event): void
    {
//        $result = $event->getResult();
    }

    public function onNewsletterRegister(NewsletterRegisterEvent $event): void
    {
        $this->edroneService->subscribe(
            $event->getNewsletterRecipient()->getFirstName(),
            $event->getNewsletterRecipient()->getEmail()
        );
    }

    public function onNewsletterConfirm(NewsletterConfirmEvent $event): void
    {
        $this->edroneService->subscribe(
            $event->getNewsletterRecipient()->getFirstName(),
            $event->getNewsletterRecipient()->getEmail()
        );
    }
}
