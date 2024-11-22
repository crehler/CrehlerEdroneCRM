<?php

declare(strict_types=1);

namespace Crehler\EdroneCrm\Service;

use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;

use Symfony\Component\HttpFoundation\RequestStack;
use function array_merge;

class EdroneCookieProvider implements CookieProviderInterface
{
    private const EDRONE_COOKIE = [
        'snippet_name' => 'crehlerEdroneCrm.cookie.edroneName',
        'snippet_description' => 'crehlerEdroneCrm.cookie.edroneDescription',
        'cookie' => 'edrone-crm-enabled',
        'expiration' => '30',
        'value' => '1',
        'default' => false
    ];

    public function __construct(
        private readonly CookieProviderInterface $originalService,
        private readonly RequestStack            $requestStack
    ) {}

    public function getCookieGroups(): array
    {
        return array_merge(
            $this->originalService->getCookieGroups(),
            [
                self::EDRONE_COOKIE
            ]
        );
    }

    /**
     * Check if user accept Edrone cookies consent
     *
     * @return bool
     */
    public function isCookieConsentAccepted(): bool
    {
        $acceptedCookiesArray = $this->requestStack->getMainRequest()->cookies->all();

        if (array_key_exists(self::EDRONE_COOKIE['cookie'], $acceptedCookiesArray)
            && $acceptedCookiesArray[self::EDRONE_COOKIE['cookie']] === '1') {
            return true;
        }

        return false;
    }
}
