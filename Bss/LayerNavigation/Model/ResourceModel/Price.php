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
 * @category  BSS
 * @package   Bss_LayerNavigation
 * @author    Extension Team
 * @copyright Copyright (c) 2018-2019 BSS Commerce Co. ( http://bsscommerce.com )
 * @license   http://bsscommerce.com/Bss-Commerce-License.txt
 */
namespace Bss\LayerNavigation\Model\ResourceModel;

use Magento\CatalogSearch\Model\Layer\Filter\Price as AbstractFilter;
use Bss\LayerNavigation\Helper\Data as LayerHelper;

/**
 * Class Price
 * @package Bss\LayerNavigation\Model\ResourceModel
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Price extends AbstractFilter
{
    /**
     * @var LayerHelper
     */
    protected $moduleHelper;

    /**
     * @var null
     */
    protected $filterVal = null;

    /**
     * @var \Magento\Tax\Helper\Data
     */
    protected $taxHelper;

    /**
     * @var \Magento\Framework\Pricing\PriceCurrencyInterface
     */
    private $priceCurrency;

    /**
     * @var \Magento\Catalog\Model\Layer\Filter\DataProvider\PriceFactory
     */
    protected $dataProviderFactory;

    /**
     * @var
     */
    protected $productCollection;

    /**
     * Price constructor.
     * @param \Magento\Catalog\Model\Layer\Filter\ItemFactory $filterItemFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Catalog\Model\Layer $layer
     * @param \Magento\Catalog\Model\Layer\Filter\Item\DataBuilder $itemDataBuilder
     * @param \Magento\Catalog\Model\ResourceModel\Layer\Filter\Price $resource
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Framework\Search\Dynamic\Algorithm $priceAlgorithm
     * @param \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency
     * @param \Magento\Catalog\Model\Layer\Filter\Dynamic\AlgorithmFactory $algorithmFactory
     * @param \Magento\Catalog\Model\Layer\Filter\DataProvider\PriceFactory $dataProviderFactory
     * @param \Magento\Tax\Helper\Data $taxHelper
     * @param LayerHelper $moduleHelper
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Catalog\Model\Layer\Filter\ItemFactory $filterItemFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\Layer $layer,
        \Magento\Catalog\Model\Layer\Filter\Item\DataBuilder $itemDataBuilder,
        \Magento\Catalog\Model\ResourceModel\Layer\Filter\Price $resource,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Search\Dynamic\Algorithm $priceAlgorithm,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        \Magento\Catalog\Model\Layer\Filter\Dynamic\AlgorithmFactory $algorithmFactory,
        \Magento\Catalog\Model\Layer\Filter\DataProvider\PriceFactory $dataProviderFactory,
        \Magento\Tax\Helper\Data $taxHelper,
        LayerHelper $moduleHelper,
        array $data = []
    ) {
        parent::__construct(
            $filterItemFactory,
            $storeManager,
            $layer,
            $itemDataBuilder,
            $resource,
            $customerSession,
            $priceAlgorithm,
            $priceCurrency,
            $algorithmFactory,
            $dataProviderFactory,
            $data
        );
        $this->priceCurrency = $priceCurrency;
        $this->dataProviderFactory = $dataProviderFactory;
        $this->moduleHelper = $moduleHelper;
        $this->taxHelper  = $taxHelper;
    }

    /**
     * @param \Magento\Framework\App\RequestInterface $request
     * @return $this
     */
    public function apply(\Magento\Framework\App\RequestInterface $request)
    {
        if (!$this->moduleHelper->isEnabled()) {
            return parent::apply($request);
        }

        $filter = $request->getParam($this->getRequestVar());
        if (!$filter || is_array($filter)) {
            return $this;
        }
        $filterParams = explode('_', $filter);
        $filter = $this->dataProviderFactory->create(['layer' => $this->getLayer()])->validateFilter($filterParams[0]);
        if (!$filter) {
            return $this;
        }

        $this->dataProviderFactory->create(['layer' => $this->getLayer()])->setInterval($filter);
        $priorFilters = $this->dataProviderFactory
            ->create(['layer' => $this->getLayer()])
            ->getPriorFilters($filterParams);
        if ($priorFilters) {
            $this->dataProviderFactory->create(['layer' => $this->getLayer()])->setPriorIntervals($priorFilters);
        }

        $productCollection = $this->getLayer()->getProductCollection();
        $productCollectionClone = $productCollection->getCollectionClone();
        if ($this->filterVal) {
            $productCollectionClone = $productCollectionClone->removeAttributeSearch(['price.from', 'price.to']);
        }
        $this->productCollection = $productCollectionClone;

        list($from, $to) = $this->filterVal = $filter;

        $this->getLayer()->getProductCollection()->addFieldToFilter(
            'price',
            ['from' => $from/$this->getCurrencyRate(), 'to' => $to/$this->getCurrencyRate()]
        );

        $this->getLayer()->getState()->addFilter(
            $this->_createItem($this->_renderRangeLabel(empty($from) ? 0 : $from, $to), $filter)
        );
        return $this;
    }

    /**
     * @param float|string $fromPrice
     * @param float|string $toPrice
     * @return float|\Magento\Framework\Phrase
     */
    protected function _renderRangeLabel($fromPrice, $toPrice)
    {
        $formattedFromPrice = $this->priceCurrency->format($fromPrice);
        if ($toPrice === '') {
            return __('%1 and above', $formattedFromPrice);
        } elseif ($fromPrice == $toPrice &&
            $this->dataProviderFactory->create(['layer' => $this->getLayer()])->getOnePriceIntervalValue()) {
            return $formattedFromPrice;
        } else {
            return __('%1 - %2', $formattedFromPrice, $this->priceCurrency->format($toPrice));
        }
    }

    /**
     * @return array
     */
    public function getSliderConfig()
    {
        if (!isset($this->productCollection)) {
            $productCollectionBefore = $this->getLayer()->getProductCollection();
            $productCollection = $productCollectionBefore->getCollectionClone();
        } else {
            $productCollection = $this->productCollection;
        }
        $min = $productCollection->getMinPrice();
        $max = $productCollection->getMaxPrice();

        list($from, $to) = $this->filterVal ?: [$min, $max];
        $from = ($from < $min) ? $min : (($from > $max) ? $max : $from);
        $to = ($to > $max) ? $max : (($to < $from) ? $from : $to);

        $item = $this->getItems()[0];

        return [
            "selectedFrom" => $from,
            "selectedTo" => $to,
            "minValue" => $min,
            "maxValue" => $max,
            "priceFormat" => $this->taxHelper->getPriceFormat(),
            "ajaxUrl" => $item->getUrl()
        ];
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _getItemsData()
    {
        if (!$this->moduleHelper->isEnabled()) {
            return parent::_getItemsData();
        }

        $attribute = $this->getAttributeModel();
        $this->_requestVar = $attribute->getAttributeCode();

        $productCollection = $this->getLayer()->getProductCollection();

        if ($this->filterVal) {
            $productCollection = $productCollection->getCollectionClone()
                ->removeAttributeSearch(['price.from', 'price.to']);
        }

        $facets = $productCollection->getFacetedData($attribute->getAttributeCode());

        $data = [];
        if (count($facets) > 1) { // two range minimum
            foreach ($facets as $key => $aggregation) {
                $count = $aggregation['count'];
                if (strpos($key, '_') === false) {
                    continue;
                }
                $data[] = $this->prepareData($key, $count);
            }
        }

        return $data;
    }

    /**
     * @param string $key
     * @param int $count
     * @return array
     */
    private function prepareData($key, $count)
    {
        list($from, $to) = explode('_', $key);
        if ($from == '*') {
            $from = $this->getFrom($to);
        }
        if ($to == '*') {
            $to = $this->getTo($to);
        }
        
        $label = $this->_renderRangeLabel(
            empty($from) ? 0 : $from * $this->getCurrencyRate(),
            empty($to) ? $to : $to * $this->getCurrencyRate()
        );

        $value = (int)$from * $this->getCurrencyRate() . '-' . (int)$to * $this->getCurrencyRate();
        
        $data = [
            'label' => $label,
            'value' => $value,
            'count' => $count,
            'from' => $from,
            'to' => $to,
        ];

        return $data;
    }
}
