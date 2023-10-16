<?php

declare(strict_types=1);

namespace Crehler\EdroneCrm\Service;

use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;

use function array_merge;

class EdroneCookieProvider implements CookieProviderInterface
{
    private const EDRONE_COOKIE = [
        'snippet_name' => 'crehlerEdroneCRM.cookie.edroneName',
        'snippet_description' => 'crehlerEdroneCRM.cookie.edroneDescription',
        'cookie' => 'edrone-crm-enabled',
        'expiration' => '30',
        'value' => '1',
        'default' => true
    ];

    public function __construct(private readonly CookieProviderInterface $originalService)
    {
    }

    public function getCookieGroups(): array
    {
        return array_merge(
            $this->originalService->getCookieGroups(),
            [
                self::EDRONE_COOKIE
            ]
        );
    }
}
