<?php

declare(strict_types=1);

namespace Crehler\EdroneCrm\Subscriber;

use Crehler\EdroneCrm\Service\EdroneCookieProvider;
use Crehler\EdroneCrm\Service\EdroneService;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderSubscriber implements EventSubscriberInterface
{
    const string EDRONE_COOKIE_NAME = 'crehlerEdroneCrm.cookie.edroneName';
    public function __construct(
        private readonly EdroneService $edroneService,
        private readonly EdroneCookieProvider $edroneCookieProvider
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderEvents::ORDER_WRITTEN_EVENT => 'onOrderWritten',
        ];
    }

    public function onOrderWritten(EntityWrittenEvent $event): void
    {
        if (! $this->edroneCookieConsentProvider()) {
            return;
        }

        if (OrderDefinition::ENTITY_NAME !== $event->getEntityName()) {
            return;
        }

        foreach ($event->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();

            if (!isset($payload['id'], $payload['stateId'])) {
                continue;
            }

            $this->edroneService->orderChanged($payload['id'], $payload['stateId'], $event->getContext());
        }
    }

    /**
     * Check if user accept Edrone cookies consent
     *
     * @return bool
     */
    private function isCookieConsentAccepted(): bool
    {
        foreach ($this->edroneCookieProvider->getCookieGroups() as $cookie) {
            if ($cookie['snippet_name'] === self::EDRONE_COOKIE_NAME) {
                return true;
            }
        }

        return false;
    }
}
