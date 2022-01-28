<?php

namespace Crehler\EdroneCrm\Service;

use Crehler\EdroneCrm\Struct\EdroneProductCategoryStruct;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class EdroneService
{
    public const EDRONE_URL = 'https://api.edrone.me/trace';

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $stateMachineRepository;

    /**
     * @var array
     */
    private $config;

    /**
     * @var ?array
     */
    private $breadcrumb;

    /**
     * EdroneService constructor.
     * @param EntityRepositoryInterface $orderRepository
     * @param EntityRepositoryInterface $stateMachineRepository
     * @param SystemConfigService $systemConfigService
     * @param string $pluginName
     */
    public function __construct(
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $stateMachineRepository,
        SystemConfigService $systemConfigService,
        string $pluginName)
    {
        $this->orderRepository = $orderRepository;
        $this->stateMachineRepository = $stateMachineRepository;
        $this->config = empty($systemConfigService->get($pluginName))? [] : ($systemConfigService->get($pluginName))['config'];
    }

    public function orderChanged(string $orderId, string $newOrderStatusId, Context $context)
    {
        $newOrderStatus = $this->getStatus($newOrderStatusId, $context);

        if($newOrderStatus->getTechnicalName() === OrderStates::STATE_CANCELLED) {
            $this->orderCancel($orderId, $context);
        }
    }

    public function subscribe(string $firstName, string $email)
    {
        $subscribeData = $this->createSubscribeData($firstName, $email);
        $this->sendPost(self::EDRONE_URL, $subscribeData);
    }

    public function createProductCategoryStruct(array $navigationTree, SalesChannelProductEntity $product)
    {
        $this->searchBreadcrumbs($navigationTree, $product);

        if(empty($this->breadcrumb)) return [];

        return (new EdroneProductCategoryStruct())
            ->setProductCategoryIds(implode('~', array_keys($this->breadcrumb)))
            ->setProductCategoryNames(implode('~', array_values($this->breadcrumb)));
    }

    private function searchBreadcrumbs(array $navigationTree, SalesChannelProductEntity $product)
    {
        /** @var TreeItem $treeItem */
        foreach ($navigationTree as $treeItem) {
            $productTree = $product->getCategoryTree();
            if($treeItem->getCategory()->getId() == end($productTree)) {
                $this->breadcrumb = $treeItem->getCategory()->getPlainBreadcrumb();
            } elseif (!empty($treeItem->getChildren())) {
                $this->searchBreadcrumbs($treeItem->getChildren(), $product);
            }
        }
    }

    private function orderCancel(string $orderId, Context $context): void
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('customer');

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $context)->get($orderId);

        if(!$order instanceof OrderEntity || !$this->getAppId()) return;

        $edroneOrderData = $this->createOrderData($order);

        $this->sendPost(self::EDRONE_URL, $edroneOrderData);
    }

    private function createOrderData(OrderEntity $order): array
    {
        return [
            'version' => '1.0.0',
            'platform' => 'shopware6',
            'app_id' => $this->getAppId(),
            'email' => $order->getOrderCustomer()->getEmail(),
            'order_id' => $order->getOrderNumber(),
            'action_type' => 'order_cancel',
            'sender_type' => 'server'
        ];
    }

    private function createSubscribeData(string $firstName, string $email): array
    {
        return [
            'app_id' => $this->getAppId(),
            'version' => '1.0.0',
            'platform' => 'shopware6',
            'first_name' => $firstName,
            'email' => $email,
            'action_type' => 'subscribe',
            'sender_type' => 'server'
        ];
    }

    private function sendPost(string $url, array $params): void
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, count($params));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_exec($ch);
        curl_close($ch);
    }

    private function getStatus(string $newOrderStatusId, Context $context): ?StateMachineStateEntity
    {
        return $this->stateMachineRepository->search(new Criteria([$newOrderStatusId]), $context)
            ->get($newOrderStatusId);
    }

    private function getAppId(): string
    {
        return empty($this->config['appId'])? '' : $this->config['appId'];
    }
}
