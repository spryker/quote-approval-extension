<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\MinimumOrderValue\Helper;

use Codeception\Module;
use Orm\Zed\MinimumOrderValue\Persistence\Map\SpyMinimumOrderValueTableMap;
use Orm\Zed\MinimumOrderValue\Persistence\Map\SpyMinimumOrderValueTypeTableMap;
use Orm\Zed\MinimumOrderValue\Persistence\SpyMinimumOrderValueQuery;
use Orm\Zed\MinimumOrderValue\Persistence\SpyMinimumOrderValueTypeQuery;

class MinimumOrderValueHelper extends Module
{
    protected const ERROR_MESSAGE_FOUND = 'Found at least one entry in the database table but database table `%s` was expected to be empty.';

    protected const ERROR_MESSAGE_EXPECTED = 'Expected at least %d entries in the database table `%s` and found %d entries.';

    /**
     * @return void
     */
    public function truncateMinimumOrderValues(): void
    {
        $this->getMinimumOrderValueQuery()
            ->deleteAll();
    }

    /**
     * @return void
     */
    public function assertMinimumOrderValueTableIsEmtpy(): void
    {
        $this->assertFalse($this->getMinimumOrderValueQuery()->exists(), sprintf(static::ERROR_MESSAGE_FOUND, SpyMinimumOrderValueTableMap::TABLE_NAME));
    }

    /**
     * @param int $recordsNum
     *
     * @return void
     */
    public function assertMinimumOrderValueTypeTableHasRecords(int $recordsNum): void
    {
        $entriesFound = $this->getMinimumOrderValueTypeQuery()->count();
        $this->assertEquals($entriesFound, $recordsNum, sprintf(static::ERROR_MESSAGE_EXPECTED, $recordsNum, SpyMinimumOrderValueTypeTableMap::TABLE_NAME, $entriesFound));
    }

    /**
     * @return \Orm\Zed\MinimumOrderValue\Persistence\SpyMinimumOrderValueQuery
     */
    protected function getMinimumOrderValueQuery(): SpyMinimumOrderValueQuery
    {
        return SpyMinimumOrderValueQuery::create();
    }

    /**
     * @return \Orm\Zed\MinimumOrderValue\Persistence\SpyMinimumOrderValueTypeQuery
     */
    protected function getMinimumOrderValueTypeQuery(): SpyMinimumOrderValueTypeQuery
    {
        return SpyMinimumOrderValueTypeQuery::create();
    }
}
