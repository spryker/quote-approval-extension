<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\QuoteRequest\Dependency\Client;

use Generated\Shared\Transfer\QuoteTransfer;

class QuoteRequestToPersistentCartClientBridge implements QuoteRequestToPersistentCartClientInterface
{
    /**
     * @var \Spryker\Client\PersistentCart\PersistentCartClientInterface
     */
    protected $persistentCartClient;

    /**
     * @param \Spryker\Client\PersistentCart\PersistentCartClientInterface $persistentCartClient
     */
    public function __construct($persistentCartClient)
    {
        $this->persistentCartClient = $persistentCartClient;
    }

    /**
     * @param \Generated\Shared\Transfer\QuoteTransfer $quoteTransfer
     *
     * @return \Generated\Shared\Transfer\QuoteTransfer
     */
    public function replaceCustomerCart(QuoteTransfer $quoteTransfer): QuoteTransfer
    {
        return $this->persistentCartClient->replaceCustomerCart($quoteTransfer);
    }
}
