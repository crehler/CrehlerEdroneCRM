<?php

declare(strict_types=1);

namespace Crehler\EdroneCrm\Service;

use Crehler\EdroneCrm\CrehlerEdroneCrm;
use Crehler\EdroneCrm\Enums\ActionType;
use Crehler\EdroneCrm\Struct\EdroneProductCategoryStruct;
use GuzzleHttp\Client;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandler;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

use function array_keys;
use function implode;

class EdroneService
{
    private const EDRONE_URL = 'https://api.edrone.me/trace';


    public function __construct(
        private readonly ConfigServiceInterface             $configService,
        private readonly EntityRepository                   $orderRepository,
        private readonly EntityRepository                   $stateMachineRepository,
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

        if ($newOrderStatus !== null
            && OrderStates::STATE_CANCELLED === $newOrderStatus->getTechnicalName()
            && $context->getSource() instanceof AdminApiSource
        ) {
            $this->orderCancel($orderId, $context);
        }

        if ($newOrderStatus !== null
            && $newOrderStatus->getTechnicalName() === OrderStates::STATE_OPEN
            && $context->getSource() instanceof SalesChannelApiSource
        ) {
            $this->orderSubmit(orderId: $orderId, context: $context);
        }
    }

    public function subscribe(?string $firstName, string $email): void
    {
        $this->sendData($this->createSubscribeData($firstName, $email));
    }

    public function createProductCategoryStruct(
        array                     $navigationTree,
        SalesChannelProductEntity $product
    ): ?EdroneProductCategoryStruct
    {
        $breadcrumb = $this->searchBreadcrumbs($navigationTree, $product);
        if (empty($breadcrumb)) {
            return null;
        }

        return (new EdroneProductCategoryStruct())
            ->setProductCategoryIds(implode('~', array_keys($breadcrumb)))
            ->setProductCategoryNames(implode('~', array_values($breadcrumb)));
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

        $this->sendData($this->createOrderData(order: $order, actionType: ActionType::ORDER));
    }

    private function orderCancel(string $orderId, Context $context): void
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

        $this->sendData($this->createOrderData(order: $order, actionType: ActionType::ORDER_CANCEL));
    }

    private function searchBreadcrumbs(array $navigationTree, SalesChannelProductEntity $product): array
    {
        /** @var TreeItem $treeItem */
        foreach ($navigationTree as $treeItem) {
            $productTree = $product->getCategoryTree();

            if ($treeItem->getCategory()->getId() === end($productTree)) {
                $breadcrumb = $treeItem->getCategory()->getPlainBreadcrumb();
            } elseif (!empty($treeItem->getChildren())) {
                $this->searchBreadcrumbs($treeItem->getChildren(), $product);
            }
        }

        return $breadcrumb ?? [];
    }

    private function setUrlForProduct(string $productId, SalesChannelContext $salesChannelContext): string
    {
        $seoPlaceholder = $this->seoUrlPlaceholderHandler->generate(
            name: 'frontend.detail.page',
            parameters: ['productId' => $productId]
        );

        return $this->seoUrlPlaceholderHandler->replace(
            content: $seoPlaceholder,
            host: $salesChannelContext->getSalesChannel()->getDomains()->first()->getUrl(),
            context: $salesChannelContext
        );
    }

    private function createOrderData(OrderEntity $order, ActionType $actionType): array
    {
        /** @var SalesChannelContext $salesChannelContext */
        $salesChannelContext = $this->getSalesChannelContextBySalesChannelId($order->getSalesChannelId());

        /** @var OrderCustomerEntity $orderCustomer */
        $orderCustomer = $order->getOrderCustomer();

        /** @var OrderLineItemCollection $lineItems */
        $lineItems = $order->getLineItems();

        /** @var OrderLineItemEntity $lineItem */
        foreach ($lineItems as $lineItem) {
            $productTitles[] = $lineItem->getLabel();
            $productSkus[] = $lineItem->getProduct()->getProductNumber();
            $productIds[] = $lineItem->getId();
            $productImages[] = $lineItem->getProduct()?->getMedia()?->first()?->getMedia()?->getUrl();

            $productUrls[] = $this->setUrlForProduct(
                productId: $lineItem->getProductId(),
                salesChannelContext: $salesChannelContext,
            );

            $productCounts[] = $lineItem->getQuantity();

            foreach ($lineItem->getPayload()['categoryIds'] as $category) {
                $productCategoryIds[] = $category;
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
            'action_type' => $actionType->value,
        ];

        $additionalCredentials = [
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
            'product_ids' => join('|', $productIds),
            'product_skus' => join('|', $productSkus),
            'product_titles' => join('|', $productTitles),
            'product_images' => join('|', $productImages),
            'product_urls' => join('|', $productUrls),
            'product_counts' => join('|', $productCounts),
            'product_category_ids' => join('|', $productCategoryIds),
        ];

        return match ($actionType) {
            ActionType::ORDER_CANCEL => $basicCredentials,
            ActionType::ORDER => array_merge($basicCredentials, $additionalCredentials),
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

    private function sendData(array $params): void
    {
        if ($this->configService->getAppId() === null) {
            return;
        }
        $client = new Client();
//dd($params);
        $client->post(self::EDRONE_URL, [
            'body' => http_build_query($params),
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]
        ]);

    }
}
