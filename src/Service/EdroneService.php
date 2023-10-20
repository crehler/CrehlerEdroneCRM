<?php

declare(strict_types=1);

namespace Crehler\EdroneCrm\Service;

use Crehler\EdroneCrm\Struct\EdroneProductCategoryStruct;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

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
        private readonly ConfigServiceInterface $configService,
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $stateMachineRepository
    ) {
    }

    public function orderChanged(string $orderId, string $newOrderStatusId, Context $context): void
    {
        $newOrderStatus = $this->getStatus($newOrderStatusId, $context);

        if (null !== $newOrderStatus && OrderStates::STATE_CANCELLED === $newOrderStatus->getTechnicalName()) {
            $this->orderCancel($orderId, $context);
        }
    }

    public function subscribe(string $firstName, string $email): void
    {
        if (null !== $this->configService->getAppId()) {
            $this->sendPost($this->createSubscribeData($firstName, $email));
        }
    }

    public function createProductCategoryStruct(
        array $navigationTree,
        SalesChannelProductEntity $product
    ): ?EdroneProductCategoryStruct {
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

    private function orderCancel(string $orderId, Context $context): void
    {
        $order = $this->orderRepository->search(
            (new Criteria([$orderId]))->addAssociation('customer'),
            $context
        )->get($orderId);

        if (!$order instanceof OrderEntity || null === $this->configService->getAppId()) {
            return;
        }

        $this->sendPost($this->createOrderData($order));
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

    private function createOrderData(OrderEntity $order): array
    {
        return [
            'version' => '1.0.0',
            'platform' => 'shopware6',
            'app_id' => $this->configService->getAppId(),
            'email' => $order->getOrderCustomer()->getEmail(),
            'order_id' => $order->getOrderNumber(),
            'action_type' => 'order_cancel',
            'sender_type' => 'server'
        ];
    }

    private function createSubscribeData(string $firstName, string $email): array
    {
        return [
            'app_id' => $this->configService->getAppId(),
            'version' => '1.0.0',
            'platform' => 'shopware6',
            'first_name' => $firstName,
            'email' => $email,
            'action_type' => 'subscribe',
            'sender_type' => 'server'
        ];
    }

    private function sendPost(array $params): void
    {
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
