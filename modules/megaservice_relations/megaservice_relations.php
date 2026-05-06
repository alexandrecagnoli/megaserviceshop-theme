<?php
/**
 * Megaservice — Relations produits Powerparts
 * Gère 4 types de relations entre produits :
 *   - mandatory  : pièces obligatoires (à avoir aussi)
 *   - excluded   : pièces exclues (incompatibles)
 *   - recommended: pièces recommandées (suggestion)
 *   - spare      : pièces de rechange (avec quantité recommandée)
 *
 * Une seule table polymorphe `ps_megaservice_product_relation`.
 * Relations unidirectionnelles (A→B n'implique pas B→A).
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/ProductRelationService.php';

class Megaservice_relations extends Module
{
    public function __construct()
    {
        $this->name             = 'megaservice_relations';
        $this->tab              = 'administration';
        $this->version          = '1.0.0';
        $this->author           = 'Megaservice';
        $this->need_instance    = 0;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap        = true;

        parent::__construct();

        $this->displayName = 'Megaservice — Relations produits Powerparts';
        $this->description = 'Gère les liaisons obligatoires / exclues / recommandées / pièces de rechange pour les produits Powerparts.';
    }

    /**
     * Catégories racines Powerparts — la fiche du module ne s'affiche que pour
     * les produits dans cette sous-arborescence. Cohérent avec ProductController override.
     */
    private static $POWERPARTS_ROOT_IDS = [41];

    public function install()
    {
        return parent::install()
            && $this->createTable()
            // Un seul hook : displayBackOfficeHeader. Le panneau est injecté
            // via JS dans la page produit (Plan B après échec des hooks "Step"
            // qui ne dispatchent pas dans la page produit moderne PS 8).
            && $this->registerHook('displayBackOfficeHeader');
    }

    public function uninstall()
    {
        // On NE supprime PAS la table à l'uninstall pour ne pas perdre les données
        // si l'admin se trompe. La SQL drop reste manuelle si besoin.
        return parent::uninstall();
    }

    public function dropTable()
    {
        return Db::getInstance()->execute(
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'megaservice_product_relation`'
        );
    }

    private function createTable()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'megaservice_product_relation` (
            `id_relation`        INT(11) NOT NULL AUTO_INCREMENT,
            `id_product_source`  INT(11) NOT NULL,
            `id_product_target`  INT(11) NOT NULL,
            `relation_type`      ENUM(\'mandatory\',\'excluded\',\'recommended\',\'spare\') NOT NULL,
            `position`           INT(11) NOT NULL DEFAULT 0,
            `recommended_qty`    INT(11) NOT NULL DEFAULT 1,
            PRIMARY KEY (`id_relation`),
            INDEX `idx_source_type` (`id_product_source`, `relation_type`),
            UNIQUE KEY `u_rel` (`id_product_source`, `id_product_target`, `relation_type`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

        return Db::getInstance()->execute($sql);
    }

    /**
     * Hook header BO — charge le CSS/JS qui injecte le panneau dans la page produit.
     * Le JS détecte la page (URL `/products-v2/<id>/edit`), fait un AJAX getState
     * pour récupérer is_powerparts + relations, et injecte le panneau dans le DOM.
     *
     * Plan B après échec des hooks "Step" qui ne dispatchent pas dans la page
     * produit moderne PS 8 (LegacyHookSubscriber issue connu).
     */
    public function hookDisplayBackOfficeHeader()
    {
        $this->context->controller->addCSS($this->_path . 'views/css/admin-product-relations.css');
        $this->context->controller->addJS($this->_path . 'views/js/admin-product-relations.js');

        // Expose l'URL AJAX du module au JS (getModuleLink gère friendly URLs)
        $ajaxUrl = $this->context->link->getModuleLink('megaservice_relations', 'admin', [], true);
        return '<script>window.MS_RELATIONS_AJAX_URL = ' . json_encode($ajaxUrl) . ';</script>';
    }

    /**
     * Public — appelé par le contrôleur AJAX pour récupérer l'état initial
     * (is_powerparts + 4 listes de relations) pour un produit donné.
     */
    public function buildPanelState($idProduct)
    {
        $idProduct = (int) $idProduct;
        if (!$idProduct || !$this->isProductInPowerpartsSubtree($idProduct)) {
            return ['is_powerparts' => false];
        }

        $relations = [];
        foreach (MsProductRelationService::allTypes() as $type) {
            $rows = MsProductRelationService::getRelations($idProduct, $type);
            $items = [];
            foreach ($rows as $r) {
                $idTarget = (int) $r['id_product_target'];
                $items[] = [
                    'id_product'      => $idTarget,
                    'name'            => $this->getProductName($idTarget),
                    'reference'       => $this->getProductReference($idTarget),
                    'cover_url'       => $this->getProductCoverUrl($idTarget),
                    'recommended_qty' => (int) $r['recommended_qty'],
                ];
            }
            $relations[$type] = $items;
        }

        return [
            'is_powerparts' => true,
            'relations'     => $relations,
        ];
    }

    /**
     * Vérifie si le produit appartient à la sous-arborescence Powerparts
     * (mêmes critères que le ProductController override).
     */
    private function isProductInPowerpartsSubtree($idProduct)
    {
        if (empty(self::$POWERPARTS_ROOT_IDS)) {
            return false;
        }
        $productCategories = Product::getProductCategories((int) $idProduct);
        if (empty($productCategories)) {
            return false;
        }
        foreach (self::$POWERPARTS_ROOT_IDS as $rootId) {
            $root = new Category((int) $rootId);
            if (!$root->id) continue;
            foreach ($productCategories as $catId) {
                $cat = new Category((int) $catId);
                if (!$cat->id) continue;
                if ($cat->nleft >= $root->nleft && $cat->nright <= $root->nright) {
                    return true;
                }
            }
        }
        return false;
    }

    private function getProductName($idProduct)
    {
        $idLang = (int) $this->context->language->id;
        $sql = 'SELECT `name` FROM `' . _DB_PREFIX_ . 'product_lang`
                WHERE `id_product` = ' . (int) $idProduct . ' AND `id_lang` = ' . $idLang;
        return (string) Db::getInstance()->getValue($sql);
    }

    private function getProductReference($idProduct)
    {
        $sql = 'SELECT `reference` FROM `' . _DB_PREFIX_ . 'product`
                WHERE `id_product` = ' . (int) $idProduct;
        return (string) Db::getInstance()->getValue($sql);
    }

    private function getProductCoverUrl($idProduct)
    {
        // ⚠️ Pas de LIMIT 1 explicite : Db::getValue() en ajoute un automatiquement
        // (cf. classes/db/Db.php) → 'LIMIT 1 LIMIT 1' = syntax error MariaDB.
        $sql = 'SELECT i.`id_image` FROM `' . _DB_PREFIX_ . 'image` i
                WHERE i.`id_product` = ' . (int) $idProduct . ' AND i.`cover` = 1';
        $idImage = (int) Db::getInstance()->getValue($sql);
        if (!$idImage) {
            return '';
        }
        $product = new Product($idProduct, false, $this->context->language->id);
        return $this->context->link->getImageLink($product->link_rewrite, $idImage, 'small_default');
    }
}
