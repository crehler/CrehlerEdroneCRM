<?php

declare(strict_types=1);

namespace Crehler\EdroneCrm\Service;

interface ConfigServiceInterface
{
    public function getAppId(): ?string;
}
