<?php declare(strict_types=1);

namespace Crehler\EdroneCrm\Struct;

use Shopware\Core\Framework\Struct\Struct;

class EdroneProductCategoryStruct extends Struct
{
    /**
     * @var string
     */
    protected $productCategoryIds;

    /**
     * @var string
     */
    protected $productCategoryNames;

    /**
     * @return string
     */
    public function getProductCategoryIds(): string
    {
        return $this->productCategoryIds;
    }

    /**
     * @param string $productCategoryIds
     * @return EdroneProductCategoryStruct
     */
    public function setProductCategoryIds(string $productCategoryIds): EdroneProductCategoryStruct
    {
        $this->productCategoryIds = $productCategoryIds;
        return $this;
    }

    /**
     * @return string
     */
    public function getProductCategoryNames(): string
    {
        return $this->productCategoryNames;
    }

    /**
     * @param string $productCategoryNames
     * @return EdroneProductCategoryStruct
     */
    public function setProductCategoryNames(string $productCategoryNames): EdroneProductCategoryStruct
    {
        $this->productCategoryNames = $productCategoryNames;
        return $this;
    }
}
