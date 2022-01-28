<?php declare(strict_types=1);

namespace Crehler\EdroneCrm\Cookie;

use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;


class EdroneCookieProvider implements CookieProviderInterface
{

    private $originalService;

    public function __construct(CookieProviderInterface $service)
    {
        $this->originalService = $service;
    }

    private const edroneCookie = [
        'snippet_name' => 'cookie.edroneName',
        'snippet_description' => 'cookie.edroneDescription',
        'cookie' => 'edrone-crm-enabled',
        'expiration' => '30',
        'value' => '1',
        'default' => true
    ];

    public function getCookieGroups(): array
    {
        return array_merge(
            $this->originalService->getCookieGroups(),
            [
                self::edroneCookie
            ]
        );
    }
}
