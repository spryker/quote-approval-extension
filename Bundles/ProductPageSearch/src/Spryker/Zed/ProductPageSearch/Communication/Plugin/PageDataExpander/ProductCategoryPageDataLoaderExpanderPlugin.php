<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\ProductPageSearch\Communication\Plugin\PageDataExpander;

use Generated\Shared\Transfer\LocaleTransfer;
use Generated\Shared\Transfer\ProductPageSearchTransfer;
use Orm\Zed\Category\Persistence\Map\SpyCategoryAttributeTableMap;
use Orm\Zed\Category\Persistence\Map\SpyCategoryClosureTableTableMap;
use Orm\Zed\Category\Persistence\Map\SpyCategoryNodeTableMap;
use Orm\Zed\Category\Persistence\Map\SpyCategoryTableMap;
use Orm\Zed\Category\Persistence\SpyCategoryNodeQuery;
use Spryker\Shared\ProductPageSearch\ProductPageSearchConfig;
use Spryker\Zed\Kernel\Communication\AbstractPlugin;
use Spryker\Zed\ProductCategory\Persistence\ProductCategoryQueryContainer;
use Spryker\Zed\ProductPageSearch\Dependency\Plugin\ProductPageDataExpanderInterface;
use Spryker\Zed\PropelOrm\Business\Model\Formatter\PropelArraySetFormatter;
use Spryker\Zed\PropelOrm\Business\Runtime\ActiveQuery\Criteria;

/**
 * @method \Spryker\Zed\ProductPageSearch\Persistence\ProductPageSearchQueryContainerInterface getQueryContainer()
 * @method \Spryker\Zed\ProductPageSearch\Communication\ProductPageSearchCommunicationFactory getFactory()
 * @method \Spryker\Zed\ProductPageSearch\Business\ProductPageSearchFacadeInterface getFacade()
 */
class ProductCategoryPageDataLoaderExpanderPlugin extends AbstractPlugin implements ProductPageDataExpanderInterface
{
    const RESULT_FIELD_PRODUCT_ORDER = 'product_order';

    /**
     * @var array
     */
    protected static $categoryTree;

    /**
     * @var string
     */
    protected static $categoryName;

    /**
     * @param array $productData
     * @param \Generated\Shared\Transfer\ProductPageSearchTransfer $productAbstractPageSearchTransfer
     *
     * @return void
     */
    public function expandProductPageData(array $productData, ProductPageSearchTransfer $productAbstractPageSearchTransfer)
    {
        $productCategoryEntities = $productData[ProductPageSearchConfig::PRODUCT_ABSTRACT_PAGE_LOAD_DATA]->getCategories();
        $allParentCategoryIds = [];
        $localeTransfer = (new LocaleTransfer())
            ->setIdLocale($productData['Locale']['id_locale']);
        foreach ($productAbstractPageSearchTransfer->getCategoryNodeIds() as $idCategory) {
            $allParentCategoryIds = array_merge(
                $allParentCategoryIds,
                $this->getAllParents($idCategory, $localeTransfer)
            );
        }

        $allParentCategoryIds = array_values(array_unique($allParentCategoryIds));
        $productAbstractPageSearchTransfer->setAllParentCategoryIds($allParentCategoryIds);

        $this->setCategoryNames(
            $allParentCategoryIds,
            $productAbstractPageSearchTransfer->getCategoryNodeIds(),
            $localeTransfer,
            $productAbstractPageSearchTransfer
        );

        $this->setSorting(
            $allParentCategoryIds,
            $localeTransfer,
            $productAbstractPageSearchTransfer,
            $productCategoryEntities
        );
    }

    /**
     * @param int $idCategoryNode
     * @param \Generated\Shared\Transfer\LocaleTransfer $localeTransfer
     *
     * @return array
     */
    protected function getAllParents($idCategoryNode, LocaleTransfer $localeTransfer)
    {
        if (static::$categoryTree === null) {
            $this->loadTree($localeTransfer);
        }

        return static::$categoryTree[$idCategoryNode];
    }

    /**
     * @param \Generated\Shared\Transfer\LocaleTransfer $localeTransfer
     *
     * @return void
     */
    protected function loadTree(LocaleTransfer $localeTransfer)
    {
        static::$categoryTree = [];

        $categoryNodes = $this->getFactory()->getCategoryQueryContainer()
            ->queryCategoryNode($localeTransfer->getIdLocale())
            ->find();

        $categoryEntities = $this->loadCategories()->find();
        $formattedCategoriesByLocaleAndNodeIds = $this->formatCategoriesWithLocaleAndNodIds($categoryEntities);

        foreach ($categoryNodes as $categoryNodeEntity) {
            $pathData = [];

            if (isset($formattedCategoriesByLocaleAndNodeIds[$localeTransfer->getIdLocale()][$categoryNodeEntity->getIdCategoryNode()])) {
                $pathData = $formattedCategoriesByLocaleAndNodeIds[$localeTransfer->getIdLocale()][$categoryNodeEntity->getIdCategoryNode()];
            }

            static::$categoryTree[$categoryNodeEntity->getIdCategoryNode()] = [];

            foreach ($pathData as $path) {
                $idCategory = (int)$path['id_category_node'];
                if (!in_array($idCategory, static::$categoryTree[$categoryNodeEntity->getIdCategoryNode()])) {
                    static::$categoryTree[$categoryNodeEntity->getIdCategoryNode()][] = $idCategory;
                }
            }
        }
    }

    /**
     * @param array $allParentCategories
     * @param array $directParentCategories
     * @param \Generated\Shared\Transfer\LocaleTransfer $localeTransfer
     * @param \Generated\Shared\Transfer\ProductPageSearchTransfer $productAbstractPageSearchTransfer
     *
     * @return void
     */
    protected function setCategoryNames(
        array $allParentCategories,
        array $directParentCategories,
        LocaleTransfer $localeTransfer,
        ProductPageSearchTransfer $productAbstractPageSearchTransfer
    ) {
        $boostedCategoryNames = [];
        foreach ($directParentCategories as $idCategory) {
            $boostedName = $this->getName($idCategory, $localeTransfer);
            if ($boostedName !== null) {
                $boostedCategoryNames[$idCategory] = $boostedName;
            }
        }
        $productAbstractPageSearchTransfer->setBoostedCategoryNames($boostedCategoryNames);

        $categoryNames = [];
        foreach ($allParentCategories as $idCategory) {
            if (in_array($idCategory, $directParentCategories)) {
                continue;
            }

            $categoryName = $this->getName($idCategory, $localeTransfer);
            if ($categoryName !== null) {
                $categoryNames[$idCategory] = $categoryName;
            }
        }
        $productAbstractPageSearchTransfer->setCategoryNames($categoryNames);
    }

    /**
     * @param int $idCategory
     * @param \Generated\Shared\Transfer\LocaleTransfer $localeTransfer
     *
     * @return string
     */
    protected function getName($idCategory, LocaleTransfer $localeTransfer)
    {
        if (static::$categoryName === null) {
            $this->loadNames($localeTransfer);
        }

        return isset(static::$categoryName[$idCategory]) ? static::$categoryName[$idCategory] : null;
    }

    /**
     * @param \Generated\Shared\Transfer\LocaleTransfer $localeTransfer
     *
     * @return void
     */
    protected function loadNames(LocaleTransfer $localeTransfer)
    {
        static::$categoryName = [];

        $categoryAttributes = $this
            ->getQueryContainer()
            ->queryCategoryAttributesByLocale($localeTransfer)
            ->useCategoryQuery()
            ->filterByIsSearchable(true)
            ->endUse()
            ->find();

        foreach ($categoryAttributes as $categoryAttributeEntity) {
            static::$categoryName[$categoryAttributeEntity->getFkCategory()] = $categoryAttributeEntity->getName();
        }
    }

    /**
     * @param array $directParentCategories
     * @param \Generated\Shared\Transfer\LocaleTransfer $localeTransfer
     * @param \Generated\Shared\Transfer\ProductPageSearchTransfer $productAbstractPageSearchTransfer
     * @param array $productCategoryEntities
     *
     * @return void
     */
    protected function setSorting(
        array $directParentCategories,
        LocaleTransfer $localeTransfer,
        ProductPageSearchTransfer $productAbstractPageSearchTransfer,
        array $productCategoryEntities
    ) {
        $maxProductOrder = (pow(2, 31) - 1);

        $filteredProductCategoriesByDirectParents = [];
        if ($productCategoryEntities) {
            foreach ($productCategoryEntities as $productCategory) {
                if (in_array($productCategory->getVirtualColumn('id_category_node'), $directParentCategories)) {
                    $filteredProductCategoriesByDirectParents[] = $productCategory;
                }
            }
        }

        $sortedCategories = [];
        foreach ($filteredProductCategoriesByDirectParents as $productCategoryEntity) {
            $idCategoryNode = $productCategoryEntity->getVirtualColumn(
                ProductCategoryQueryContainer::VIRTUAL_COLUMN_ID_CATEGORY_NODE
            );

            $productOrder = (int)$productCategoryEntity->getProductOrder() ?: $maxProductOrder;
            $sortedCategories[$idCategoryNode]['product_order'] = $productOrder;
            $allNodeParents = $this->getAllParents($idCategoryNode, $localeTransfer);
            $sortedCategories[$idCategoryNode]['all_node_parents'] = $allNodeParents;
        }
        $productAbstractPageSearchTransfer->setSortedCategories($sortedCategories);
    }

    /**
     * @return \Orm\Zed\Category\Persistence\SpyCategoryNodeQuery
     */
    protected function loadCategories()
    {
        $nodeQuery = SpyCategoryNodeQuery::create();

        $nodeQuery
            ->useClosureTableQuery()
                ->orderByFkCategoryNodeDescendant(Criteria::DESC)
                ->orderByDepth(Criteria::DESC)
                ->filterByDepth(null, Criteria::NOT_EQUAL)
            ->endUse()
            ->useCategoryQuery()
                ->useAttributeQuery()
                ->endUse()
            ->endUse();

        $nodeQuery
            ->withColumn(SpyCategoryNodeTableMap::COL_FK_CATEGORY, 'fk_category')
            ->withColumn(SpyCategoryNodeTableMap::COL_ID_CATEGORY_NODE, 'id_category_node')
            ->withColumn(SpyCategoryClosureTableTableMap::COL_FK_CATEGORY_NODE_DESCENDANT, 'fk_category_node_descendant')
            ->withColumn(SpyCategoryAttributeTableMap::COL_NAME, 'name')
            ->withColumn(SpyCategoryTableMap::COL_CATEGORY_KEY, 'category_key')
            ->withColumn(SpyCategoryAttributeTableMap::COL_FK_LOCALE, 'fk_locale');

        $nodeQuery->setFormatter(new PropelArraySetFormatter());

        return $nodeQuery;
    }

    /**
     * @param array $categoryEntities
     *
     * @return array
     */
    protected function formatCategoriesWithLocaleAndNodIds(array $categoryEntities)
    {
        $categories = [];
        foreach ($categoryEntities as $categoryEntity) {
            $categories[$categoryEntity['fk_locale']][$categoryEntity['fk_category_node_descendant']][] = $categoryEntity;
        }

        return $categories;
    }
}
