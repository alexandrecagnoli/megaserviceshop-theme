<?php

use PrestaShop\PrestaShop\Core\Product\Search\SortOrder;

class CategoryController extends CategoryControllerCore
{
    /**
     * Mapping ID catégorie => template
     *
     * Templates disponibles :
     *   'default' — 3 colonnes + sidebar filtres
     *   'full'    — 4 colonnes pleine largeur, sous-catégories en cards
     *
     * Pour ajouter une catégorie : ajouter son ID (visible dans l'URL PS, ex: /15-equipements → 15)
     */
    private static $CATEGORY_TEMPLATES = [
        14 => 'full', // Lifestyle (vêtements)
        15 => 'full', // Équipements pilotes
    ];

    /**
     * Catégories racines qui affichent le contexte "moto sélectionnée"
     * (bandeau moto + badge "Compatible" + filtrage par montabilité).
     * S'applique à la racine ET à toutes ses sous-catégories (test nested set).
     */
    private static $MOTO_CONTEXT_ROOT_IDS = [
        41, // Accessoires Powerparts
    ];

    /** @var int|null id_moto du "garage" (cookie), mémoïsé pour la requête. */
    private $motoFilterId;

    public function init()
    {
        parent::init();

        // Retrait du filtre garage : ?ms_clear_moto=1 → efface le cookie + URL propre.
        if ((int) Tools::getValue('ms_clear_moto') === 1) {
            $this->context->cookie->ms_moto = 0;
            $this->context->cookie->write();
            Tools::redirect($this->context->link->getCategoryLink((int) $this->category->id));
        }
    }

    public function initContent()
    {
        parent::initContent();

        $category_id = (int) $this->category->id;
        $template = isset(self::$CATEGORY_TEMPLATES[$category_id])
            ? self::$CATEGORY_TEMPLATES[$category_id]
            : 'default';

        $motoFilter = $this->motoFilterBanner();

        $this->context->smarty->assign([
            'ms_category_template'  => $template,
            'ms_is_full_width'      => $template === 'full',
            // Badge "Compatible" + contexte : uniquement quand un filtre moto est ACTIF
            // (tous les produits affichés sont alors réellement compatibles).
            'ms_show_moto_context'  => (bool) $motoFilter,
            'ms_moto_filter'        => $motoFilter,
        ]);
    }

    /**
     * Sur ton core, la résolution du provider passe par le hook `productSearchProvider`
     * que facetedsearch gagne toujours — impossible de le court-circuiter par une
     * simple méthode. On reproduit donc le flux natif de `getProductSearchVariables`
     * (patron ukooparts) en forçant NOTRE provider, UNIQUEMENT quand le filtre garage
     * est actif ; sinon on délègue au natif (facetedsearch intact).
     */
    protected function getProductSearchVariables()
    {
        if (!($this->getMotoFilterId() && $this->isInMotoContextSubtree())) {
            return parent::getProductSearchVariables();
        }

        $file = _PS_MODULE_DIR_ . 'megaservice_mountability/classes/MsMountabilityCategoryProductSearchProvider.php';
        if (!is_file($file)) {
            return parent::getProductSearchVariables();
        }
        require_once $file;
        if (!class_exists('MsMountabilityCategoryProductSearchProvider')) {
            return parent::getProductSearchVariables();
        }

        $context = $this->getProductSearchContext();
        $query   = $this->getProductSearchQuery();

        $provider = new MsMountabilityCategoryProductSearchProvider(
            $this->context,
            $this->getTranslator(),
            (int) $this->category->id,
            $this->getMotoFilterId()
        );

        $resultsPerPage = (int) Tools::getValue('resultsPerPage');
        if ($resultsPerPage <= 0) {
            $resultsPerPage = (int) Configuration::get('PS_PRODUCTS_PER_PAGE');
        }
        $query
            ->setResultsPerPage($resultsPerPage)
            ->setPage(max((int) Tools::getValue('page'), 1));

        if ($encodedSortOrder = Tools::getValue('order')) {
            $query->setSortOrder(SortOrder::newFromString($encodedSortOrder));
        }
        $query->setEncodedFacets(Tools::getValue('q'));

        Hook::exec('actionProductSearchProviderRunQueryBefore', compact('query'));
        $result = $provider->runQuery($context, $query);
        Hook::exec('actionProductSearchProviderRunQueryAfter', compact('query', 'result'));

        if (!$result->getCurrentSortOrder()) {
            $result->setCurrentSortOrder($query->getSortOrder());
        }

        $products   = $this->prepareMultipleProductsForTemplate($result->getProducts());
        $pagination = $this->getTemplateVarPagination($query, $result);

        $sort_orders   = $this->getTemplateVarSortOrders(
            $result->getAvailableSortOrders(),
            $query->getSortOrder()->toString()
        );
        $sort_selected = false;
        foreach ($sort_orders as $order) {
            if (isset($order['current']) && $order['current'] === true) {
                $sort_selected = $order['label'];
                break;
            }
        }

        $searchVariables = [
            'result'                  => $result,
            'label'                   => $this->getListingLabel(),
            'products'                => $products,
            'sort_orders'             => $sort_orders,
            'sort_selected'           => $sort_selected,
            'pagination'              => $pagination,
            'rendered_facets'         => null, // facettes mises de côté quand le filtre moto est actif
            'rendered_active_filters' => null,
            'js_enabled'              => $this->ajax,
            'current_url'             => $this->updateQueryString(['q' => $result->getEncodedFacets()]),
        ];

        Hook::exec('filterProductSearch', ['searchVariables' => &$searchVariables]);
        Hook::exec('actionProductSearchAfter', $searchVariables);

        return $searchVariables;
    }

    /** id_moto du garage (cookie), 0 si absent. */
    private function getMotoFilterId()
    {
        if ($this->motoFilterId === null) {
            $this->motoFilterId = (int) $this->context->cookie->ms_moto;
        }

        return $this->motoFilterId;
    }

    /**
     * Données du bandeau "Catalogue filtré sur X" (ou null si pas de filtre actif
     * sur cette catégorie). Le lien "changer" rouvre la modale (js-model-trigger),
     * le lien "retirer" efface le cookie.
     *
     * @return array{label:string,clear_url:string}|null
     */
    private function motoFilterBanner()
    {
        $idMoto = $this->getMotoFilterId();
        if (!$idMoto || !$this->isInMotoContextSubtree()) {
            return null;
        }

        $row = Db::getInstance()->getRow(
            'SELECT `annee`, `core_name`, `nom_fr`
             FROM `' . _DB_PREFIX_ . 'ms_moto`
             WHERE `id_moto` = ' . (int) $idMoto . ' AND `active` = 1'
        );
        if (!$row) {
            return null;
        }

        $name  = $row['core_name'] !== '' ? $row['core_name'] : $row['nom_fr'];
        $base  = $this->context->link->getCategoryLink((int) $this->category->id);
        $clear = $base . (strpos($base, '?') !== false ? '&' : '?') . 'ms_clear_moto=1';

        return [
            'label'     => trim($row['annee'] . ' ' . $name),
            'clear_url' => $clear,
        ];
    }

    private function isInMotoContextSubtree()
    {
        if (empty(self::$MOTO_CONTEXT_ROOT_IDS) || !$this->category->id) {
            return false;
        }

        foreach (self::$MOTO_CONTEXT_ROOT_IDS as $root_id) {
            $root = new Category((int) $root_id);
            if (!$root->id) {
                continue;
            }
            if ($this->category->nleft >= $root->nleft && $this->category->nright <= $root->nright) {
                return true;
            }
        }

        return false;
    }
}
