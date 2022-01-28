<?php declare(strict_types=1);

namespace Crehler\EdroneCrm\Resources\snippet\pl_PL;

use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class SnippetFile_pl_PL implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'storefront.pl-PL';
    }

    public function getPath(): string
    {
        return __DIR__ . '/storefront.pl-PL.json';
    }

    public function getIso(): string
    {
        return 'pl-PL';
    }

    public function getAuthor(): string
    {
        return 'Crehler';
    }

    public function isBase(): bool
    {
        return false;
    }
}
