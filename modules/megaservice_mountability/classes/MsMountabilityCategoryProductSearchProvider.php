<?php
/**
 * Provider de recherche produits « compatibles avec la moto en garage ».
 *
 * Branché par l'override CategoryController quand un cookie `ms_moto` est présent
 * sur le sous-arbre Powerparts : il court-circuite ps_facetedsearch et renvoie,
 * via le pipeline standard (pagination + tri + compteur natifs), les produits de
 * la catégorie ∩ compatibles avec la moto (table ms_mountability).
 *
 * Patron : UkoopartsCategoryProductSearchProvider (API PS 8 identique).
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchContext;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchProviderInterface;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchResult;
use PrestaShop\PrestaShop\Core\Product\Search\SortOrderFactory;

require_once __DIR__ . '/MsMountability.php';

class MsMountabilityCategoryProductSearchProvider implements ProductSearchProviderInterface
{
    /** @var Context */
    private $context;
    /** @var SortOrderFactory */
    private $sortOrderFactory;
    /** @var int */
    private $idCategory;
    /** @var int */
    private $idMoto;

    public function __construct(Context $context, $translator, $idCategory, $idMoto)
    {
        $this->context          = $context;
        $this->sortOrderFactory = new SortOrderFactory($translator);
        $this->idCategory       = (int) $idCategory;
        $this->idMoto           = (int) $idMoto;
    }

    public function runQuery(ProductSearchContext $context, ProductSearchQuery $query): ProductSearchResult
    {
        $result = new ProductSearchResult();

        $ids   = $this->compatibleCategoryProductIds($query);
        $total = count($ids);

        $perPage = (int) $query->getResultsPerPage();
        if ($perPage <= 0) {
            $perPage = 12;
        }
        $page   = max(1, (int) $query->getPage());
        $offset = ($page - 1) * $perPage;

        $products = [];
        foreach (array_slice($ids, $offset, $perPage) as $id) {
            // id_product suffit : l'assembleur/presenter du contrôleur complète.
            $products[] = ['id_product' => (int) $id, 'id_product_attribute' => 0];
        }

        $result->setProducts($products);
        $result->setTotalProductsCount($total);
        $result->setAvailableSortOrders($this->sortOrderFactory->getDefaultSortOrders());
        $result->setCurrentSortOrder($query->getSortOrder());

        return $result;
    }

    /**
     * IDs produits du sous-arbre catégorie ∩ compatibles moto, triés selon la
     * requête. Liste complète triée puis paginée en PHP (volumétrie catégorie
     * raisonnable ; POC).
     *
     * @return int[]
     */
    private function compatibleCategoryProductIds(ProductSearchQuery $query)
    {
        $compatible = MsMountability::getCompatibleProducts($this->idMoto);
        if (empty($compatible)) {
            return [];
        }
        $in     = implode(',', array_map('intval', $compatible));
        $idShop = (int) $this->context->shop->id;
        $idLang = (int) $this->context->language->id;

        $rows = Db::getInstance()->executeS(
            'SELECT cp.`id_product` AS id_product,
                    MIN(ps.`price`)    AS s_price,
                    MIN(pl.`name`)     AS s_name,
                    MIN(ps.`date_add`) AS s_date,
                    MIN(cp.`position`) AS s_pos
             FROM `' . _DB_PREFIX_ . 'category_product` cp
             JOIN `' . _DB_PREFIX_ . 'category` c    ON c.`id_category` = cp.`id_category`
             JOIN `' . _DB_PREFIX_ . 'category` root ON root.`id_category` = ' . $this->idCategory . '
             JOIN `' . _DB_PREFIX_ . 'product_shop` ps
                  ON ps.`id_product` = cp.`id_product` AND ps.`id_shop` = ' . $idShop . '
             LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                  ON pl.`id_product` = cp.`id_product` AND pl.`id_lang` = ' . $idLang . ' AND pl.`id_shop` = ' . $idShop . '
             WHERE c.`nleft` >= root.`nleft` AND c.`nright` <= root.`nright`
               AND cp.`id_product` IN (' . $in . ')
               AND ps.`active` = 1 AND ps.`visibility` IN ("both", "catalog")
             GROUP BY cp.`id_product`
             ' . $this->orderBySql($query)
        ) ?: [];

        return array_map(static function ($r) { return (int) $r['id_product']; }, $rows);
    }

    private function orderBySql(ProductSearchQuery $query)
    {
        $sort  = $query->getSortOrder();
        $field = $sort ? $sort->getField() : 'position';
        $way   = ($sort && strtolower((string) $sort->getDirection()) === 'desc') ? 'DESC' : 'ASC';

        switch ($field) {
            case 'price':
                return 'ORDER BY s_price ' . $way;
            case 'name':
                return 'ORDER BY s_name ' . $way;
            case 'date_add':
                return 'ORDER BY s_date ' . $way;
            case 'position':
            default:
                return 'ORDER BY s_pos ' . $way . ', s_date DESC';
        }
    }
}
