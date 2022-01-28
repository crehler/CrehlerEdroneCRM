<?php declare(strict_types=1);

namespace Crehler\EdroneCrm\Subscriber;

use Crehler\EdroneCrm\Service\EdroneService;
use Crehler\EdroneCrm\Struct\EdroneProductCategoryStruct;
use Shopware\Core\Content\Cms\Events\CmsPageLoadedEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterRegisterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;

class StorefrontSubscriber implements EventSubscriberInterface
{
    /**
     * @var EdroneService
     */
    private $edroneService;

    /**
     * StorefrontSubscriber constructor.
     * @param $edroneService
     */
    public function __construct(EdroneService $edroneService)
    {
        $this->edroneService = $edroneService;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => 'onPage',
            CmsPageLoadedEvent::class => 'onCmsPage',
            NewsletterRegisterEvent::class => 'onNewsletterRegister',
            NewsletterConfirmEvent::class => 'onNewsletterConfirm',
        ];
    }

    public function onCmsPage(CmsPageLoadedEvent $event): void
    {
//        $result = $event->getResult();
    }

    public function onPage(ProductPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $product = $page->getProduct();
        $edroneProductCategory = $this->edroneService->createProductCategoryStruct($page->getHeader()->getNavigation()->getTree(), $product);

        if($edroneProductCategory instanceof EdroneProductCategoryStruct) {
            $product->addExtension('edroneProductCategory', $edroneProductCategory);
        }
    }

    public function onNewsletterRegister(NewsletterRegisterEvent $event): void
    {
        $newsletterRecipient = $event->getNewsletterRecipient();
        $this->edroneService->subscribe($newsletterRecipient->getFirstName(), $newsletterRecipient->getEmail());
    }

    public function onNewsletterConfirm(NewsletterConfirmEvent $event): void
    {
        $newsletterRecipient = $event->getNewsletterRecipient();
        $this->edroneService->subscribe($newsletterRecipient->getFirstName(), $newsletterRecipient->getEmail());
    }
}
