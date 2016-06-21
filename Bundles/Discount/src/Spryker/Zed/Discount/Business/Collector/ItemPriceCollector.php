<?php
/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Discount\Business\Collector;

use Generated\Shared\Transfer\ClauseTransfer;
use Generated\Shared\Transfer\QuoteTransfer;
use Spryker\Zed\Discount\Business\QueryString\ComparatorOperatorsInterface;
use Spryker\Zed\Discount\Business\QueryString\Converter\CurrencyConverterInterface;

class ItemPriceCollector extends BaseCollector implements CollectorInterface
{

    /**
     * @var \Spryker\Zed\Discount\Business\QueryString\ComparatorOperatorsInterface
     */
    protected $comparators;

    /**
     * @var \Spryker\Zed\Discount\Business\QueryString\Converter\CurrencyConverterInterface
     */
    protected $currencyConverter;

    /**
     * @param \Spryker\Zed\Discount\Business\QueryString\ComparatorOperatorsInterface $comparators
     * @param \Spryker\Zed\Discount\Business\QueryString\Converter\CurrencyConverterInterface $currencyConverter
     */
    public function __construct(
        ComparatorOperatorsInterface $comparators,
        CurrencyConverterInterface $currencyConverter
    ) {
        $this->comparators = $comparators;
        $this->currencyConverter = $currencyConverter;
    }

    /**
     * @param \Generated\Shared\Transfer\QuoteTransfer $quoteTransfer
     * @param \Generated\Shared\Transfer\ClauseTransfer $clauseTransfer
     *
     * @return \Generated\Shared\Transfer\DiscountableItemTransfer[]
     */
    public function collect(QuoteTransfer $quoteTransfer, ClauseTransfer $clauseTransfer)
    {
        $this->currencyConverter->convertDecimalToCent($clauseTransfer);

        $discountableItems = [];
        foreach ($quoteTransfer->getItems() as $itemTransfer) {
            if ($this->comparators->compare($clauseTransfer, $itemTransfer->getUnitGrossPrice()) === false) {
                continue;
            }

            $discountableItems[] = $this->createDiscountableItemTransfer(
                $itemTransfer->getUnitGrossPrice(),
                $itemTransfer->getQuantity(),
                $itemTransfer->getCalculatedDiscounts()
            );
        }

        return $discountableItems;
    }

}
