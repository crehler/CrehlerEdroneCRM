<?php

declare(strict_types=1);

namespace Crehler\EdroneCrm\Service;

use Crehler\EdroneCrm\CrehlerEdroneCrm;
use Crehler\EdroneCrm\Enums\ActionType;
use Crehler\EdroneCrm\Struct\EdroneProductCategoryStruct;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\Seo\SeoUrl\SeoUrlRepositoryTest;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

use stdClass;
use function array_keys;
use function count;
use function curl_close;
use function curl_exec;
use function curl_init;
use function curl_setopt;
use function implode;

use const CURLOPT_URL;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_HEADER;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;

class EdroneService
{
    private const EDRONE_URL = 'https://api.edrone.me/trace';

    private ?array $breadcrumb;

    public function __construct(
        private readonly ConfigServiceInterface             $configService,
        private readonly EntityRepository                   $orderRepository,
        private readonly EntityRepository                   $stateMachineRepository,
        private readonly EntityRepository                   $salesChannelDomainRepository,
        private readonly SeoUrlPlaceholderHandler           $seoUrlPlaceholderHandler,
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
    )
    {
    }

    public function getSalesChannelContextBySalesChannelId(string $salesChannelId): ?SalesChannelContext
    {
        return $this->salesChannelContextFactory->create('', $salesChannelId);
    }

    public function orderChanged(string $orderId, string $newOrderStatusId, Context $context): void
    {
        $newOrderStatus = $this->getStatus($newOrderStatusId, $context);

        if (null !== $newOrderStatus && OrderStates::STATE_CANCELLED === $newOrderStatus->getTechnicalName()) {
            $this->orderCancel($orderId, $context);
        }

        if ($newOrderStatus->getTechnicalName() === OrderStates::STATE_OPEN) {
            $this->orderSubmit(orderId: $orderId, context: $context);
        }
    }

    public function subscribe(?string $firstName, string $email): void
    {
        $this->sendPost($this->createSubscribeData($firstName, $email));
    }

    public function createProductCategoryStruct(
        array                     $navigationTree,
        SalesChannelProductEntity $product
    ): ?EdroneProductCategoryStruct
    {
        $this->searchBreadcrumbs($navigationTree, $product);
        if (empty($this->breadcrumb)) {
            return null;
        }

        return (new EdroneProductCategoryStruct())
            ->setProductCategoryIds(implode('~', array_keys($this->breadcrumb)))
            ->setProductCategoryNames(implode('~', array_values($this->breadcrumb)));
    }

    private function getStatus(string $newOrderStatusId, Context $context): ?StateMachineStateEntity
    {
        return $this->stateMachineRepository->search(new Criteria([$newOrderStatusId]), $context)
            ->get($newOrderStatusId);
    }

    private function orderSubmit(string $orderId, Context $context): void
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('orderCustomer.customer.address');
        $criteria->addAssociation('lineItems.product.media');
        $criteria->addAssociation('billingAddress.country');
        $criteria->addAssociation('currency');

        $order = $this->orderRepository->search($criteria, $context)->get($orderId);

        if (!$order instanceof OrderEntity) {
            return;
        }

        $this->sendProduct($this->createOrderData($order, $context, ActionType::ORDER));
    }

    private function orderCancel(string $orderId, Context $context): void
    {
        $order = $this->orderRepository->search(
            (new Criteria([$orderId]))->addAssociation('customer'),
            $context
        )->get($orderId);

        if (!$order instanceof OrderEntity) {
            return;
        }

        $this->sendPost($this->createOrderData($order, $context, ActionType::ORDER_CANCEL));
    }

    private function searchBreadcrumbs(array $navigationTree, SalesChannelProductEntity $product): void
    {
        /** @var TreeItem $treeItem */
        foreach ($navigationTree as $treeItem) {
            $productTree = $product->getCategoryTree();

            if ($treeItem->getCategory()->getId() === end($productTree)) {
                $this->breadcrumb = $treeItem->getCategory()->getPlainBreadcrumb();
            } elseif (!empty($treeItem->getChildren())) {
                $this->searchBreadcrumbs($treeItem->getChildren(), $product);
            }
        }
    }

    private function setUrlForProduct(string $productId, Context $context, SalesChannelContext $salesChannelContext): string
    {
        $seoPlaceholder = $this->seoUrlPlaceholderHandler->generate(
            name: 'frontend.detail.page',
            parameters: ['productId' => $productId]
        );

        return $this->seoUrlPlaceholderHandler->replace(
            content: $seoPlaceholder,
            host: $this->getSalesChanelDomain($context)->getUrl(),
            context: $salesChannelContext
        );
    }

    private function getSalesChanelDomain(Context $context): SalesChannelDomainEntity
    {
        $salesChanelId = $context->getSource()->getSalesChannelId();

        $criteria = new Criteria();
        $criteria->addAssociation('currency');
        $criteria->addAssociation('language');
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChanelId));

        return $this->salesChannelDomainRepository->search($criteria, $context)->first();
    }

    private function createOrderData(OrderEntity $order, Context $context, ActionType $actionType): array
    {
        /** @var SalesChannelContext $salesChannelContext */
        $salesChannelContext = $this->getSalesChannelContextBySalesChannelId($context->getSource()->getSalesChannelId());

        /** @var OrderCustomerEntity $orderCustomer */
        $orderCustomer = $order->getOrderCustomer();

        /** @var OrderLineItemCollection $lineItems */
        $lineItems = $order->getLineItems();

        /** @var OrderLineItemEntity $lineItem */
        foreach ($lineItems as $lineItem) {
            $product_titles[] = $lineItem->getLabel();
            $product_skus[] = $lineItem->getProduct()->getProductNumber();
            $product_ids[] = $lineItem->getId();
            $product_images[] = $lineItem->getProduct()?->getMedia()?->first()?->getMedia()?->getUrl();
            $product_urls[] = $this->setUrlForProduct($lineItem->getProductId(), $context, $salesChannelContext);;
            $product_counts[] = $lineItem->getQuantity();

            foreach ($lineItem->getPayload()['categoryIds'] as $category) {
                $product_category_ids[] = $category;
            }
        }

        $basicCredentials = [
            'app_id' => $this->configService->getAppId(),
            'version' => CrehlerEdroneCrm::VERSION,
            'platform' => CrehlerEdroneCrm::PLATFORM,
            'platform_version' => CrehlerEdroneCrm::PLATFORM_VERSION,
            'sender_type' => 'server',
            'email' => $order->getOrderCustomer()->getEmail(),
            'order_id' => $order->getOrderNumber(),
            'action_type' => ActionType::ORDER->value,
        ];

        $orderPurchaseCredentials = [
            'first_name' => $orderCustomer->getFirstName(),
            'last_name' => $orderCustomer->getLastName(),
            'phone' => $order->getBillingAddress()->getPhoneNumber(),
            'city' => $order->getBillingAddress()->getCity(),
            'country' => $order->getBillingAddress()->getCountry()->getIso(),
            'subscriber_status' => $order->getOrderCustomer()->getCustomer()->getGuest(),
            'order_payment_value' => $order->getAmountTotal(),
            'base_payment_value' => $order->getAmountTotal(),
            'base_currency' => $order->getCurrency()->getShortName(),
            'order_currency' => $order->getCurrency()->getShortName(),
            'product_ids' => join('|', $product_ids),
            'product_skus' => join('|', $product_skus),
            'product_titles' => join('|', $product_titles),
            'product_images' => join('|', $product_images),
            'product_urls' => join('|', $product_urls),
            'product_counts' => join('|', $product_counts),
            'product_category_ids' => join('|', $product_category_ids),
        ];

        return match ($actionType) {
            ActionType::ORDER => array_merge($basicCredentials, $orderPurchaseCredentials),
            ActionType::ORDER_CANCEL => $basicCredentials,
            default => null,
        };
    }

    private function createSubscribeData(?string $firstName, string $email): array
    {
        return [
            'app_id' => $this->configService->getAppId(),
            'version' => CrehlerEdroneCrm::VERSION,
            'platform' => CrehlerEdroneCrm::PLATFORM,
            'platform_version' => CrehlerEdroneCrm::PLATFORM_VERSION,
            'action_type' => 'subscribe',
            'sender_type' => 'server',
            'first_name' => $firstName ?? '',
            'email' => $email,
        ];
    }

    private function sendProduct(array $params): void
    {
        if ($this->configService->getAppId() === null) {
            return;
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, self::EDRONE_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_exec($ch);
        curl_close($ch);
    }

    private function sendPost(array $params): void
    {
        if ($this->configService->getAppId() === null) {
            return;
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, self::EDRONE_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, count($params));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_exec($ch);
        curl_close($ch);
    }
}
