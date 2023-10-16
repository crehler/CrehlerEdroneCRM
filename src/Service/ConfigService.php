<?php

declare(strict_types=1);

namespace Crehler\EdroneCrm\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigService implements ConfigServiceInterface
{
    private array $config;

    public function __construct(string $pluginName, SystemConfigService $systemConfigService)
    {
        $this->config = $systemConfigService->get($pluginName)['config'] ?? [];
    }

    public function getAppId(): ?string
    {
        return $this->config['appId'] ?? null;
    }
}
