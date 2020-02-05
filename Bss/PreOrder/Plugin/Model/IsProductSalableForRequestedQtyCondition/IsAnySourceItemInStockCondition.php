<?php
/**
 * BSS Commerce Co.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://bsscommerce.com/Bss-Commerce-License.txt
 *
 * @category   BSS
 * @package    Bss_PreOrder
 * @author     Extension Team
 * @copyright  Copyright (c) 2018-2019 BSS Commerce Co. ( http://bsscommerce.com )
 * @license    http://bsscommerce.com/Bss-Commerce-License.txt
 */
namespace Bss\PreOrder\Plugin\Model\IsProductSalableForRequestedQtyCondition;

use Bss\PreOrder\Model\Attribute\Source\Order;
use Magento\InventorySales\Model\IsProductSalableForRequestedQtyCondition\IsAnySourceItemInStockCondition as IsA;

/**
 * Class IsSalableWithReservationsCondition
 *
 */
class IsAnySourceItemInStockCondition
{
    /**
     * @var \Bss\PreOrder\Helper\Data
     */
    protected $helper;

    /**
     * OrderNotice constructor.
     * @param \Bss\PreOrder\Helper\Data $helper
     */
    public function __construct(
        \Bss\PreOrder\Helper\Data $helper
    ) {
        $this->helper = $helper;
    }

    /**
     * Apply For Rule Conditions
     *
     * @param IsA $subject
     * @param callable $proceed
     * @param string $sku
     * @param int $stockId
     * @param float $requestedQty
     * @return mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute($subject, callable $proceed, string $sku, int $stockId, float $requestedQty)
    {
        if ($this->helper->isEnable()
            && class_exists(\Magento\InventorySalesApi\Api\Data\ProductSalableResultInterfaceFactory::class)) {
            $product = $this->helper->getProductBySku($sku);
            $preOrder = $product->getData('preorder');
            if ($preOrder == Order::ORDER_YES || $preOrder == Order::ORDER_OUT_OF_STOCK) {
                return \Magento\Framework\App\ObjectManager::getInstance()->create(
                    \Magento\InventorySalesApi\Api\Data\ProductSalableResultInterface::class,
                    ['errors' => []]
                );
            }
        }

        return $proceed($sku, $stockId, $requestedQty);
    }
}