<?php declare(strict_types=1);

namespace Crehler\EdroneCrm\Subscriber;

use Crehler\EdroneCrm\Service\EdroneService;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderSubscriber implements EventSubscriberInterface
{
    /**
     * @var EdroneService
     */
    private $edroneService;

    /**
     * OrderSubscriber constructor.
     * @param EdroneService $edroneService
     */
    public function __construct(EdroneService $edroneService)
    {
        $this->edroneService = $edroneService;
    }


    public static function getSubscribedEvents(): array
    {
        return [
            OrderEvents::ORDER_WRITTEN_EVENT => 'onOrderWritten',
        ];
    }

    public function onOrderWritten(EntityWrittenEvent $event): void
    {
        if ($event->getEntityName() !== OrderDefinition::ENTITY_NAME) {
            return;
        }

        foreach ($event->getWriteResults() as $writeResult) {

            $payload = $writeResult->getPayload();
            if (empty($payload)) {
                continue;
            }

            $this->edroneService->orderChanged($payload['id'], $payload['stateId'], $event->getContext());
        }
    }
}
