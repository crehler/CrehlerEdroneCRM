<?php

declare(strict_types=1);

namespace Crehler\EdroneCrm\Struct;

use Shopware\Core\Framework\Struct\Struct;

class EdroneProductCategoryStruct extends Struct
{
    protected string $productCategoryIds;

    protected string $productCategoryNames;

    public function getProductCategoryIds(): string
    {
        return $this->productCategoryIds;
    }

    public function setProductCategoryIds(string $productCategoryIds): EdroneProductCategoryStruct
    {
        $this->productCategoryIds = $productCategoryIds;

        return $this;
    }

    public function getProductCategoryNames(): string
    {
        return $this->productCategoryNames;
    }

    public function setProductCategoryNames(string $productCategoryNames): EdroneProductCategoryStruct
    {
        $this->productCategoryNames = $productCategoryNames;

        return $this;
    }
}
