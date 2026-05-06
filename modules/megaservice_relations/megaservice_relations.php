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
            // Hook inline (page produit moderne PS 8, en bas du step "Description")
            && $this->registerHook('displayAdminProductsMainStepLeftColumnBottom')
            // Hook legacy pour compat éventuelle (apparaît dans l'onglet "Modules")
            && $this->registerHook('displayAdminProductsExtra')
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
     * Hook moderne PS 8 — affiche le panneau INLINE en bas du step Description
     * (toujours visible, pas besoin de cliquer "Configurer"). Uniquement pour
     * les produits dans la sous-arborescence Powerparts.
     */
    public function hookDisplayAdminProductsMainStepLeftColumnBottom($params)
    {
        error_log('[MS_RELATIONS] hook MainStepLeftColumnBottom called for id_product=' . ($params['id_product'] ?? 'null'));
        $idProduct = (int) ($params['id_product'] ?? 0);
        $debug = '<div style="background:#ff0;padding:8px;border:2px solid red;font-size:14px;">HOOK FIRED — id_product=' . $idProduct . ' — Powerparts=' . ($idProduct && $this->isProductInPowerpartsSubtree($idProduct) ? 'YES' : 'NO') . '</div>';
        return $debug . $this->renderRelationsPanel($params);
    }

    /**
     * Hook legacy — fallback pour les versions PS où le hook moderne n'est pas dispo.
     * Apparaît dans l'onglet Modules avec un bouton "Configurer".
     */
    public function hookDisplayAdminProductsExtra($params)
    {
        return $this->renderRelationsPanel($params);
    }

    /**
     * Render commun aux deux hooks. N'affiche le panneau que si le produit
     * appartient à la sous-arborescence Accessoires Powerparts.
     */
    private function renderRelationsPanel($params)
    {
        $idProduct = (int) $params['id_product'];
        if (!$idProduct || !$this->isProductInPowerpartsSubtree($idProduct)) {
            return '';
        }

        // Pré-charge les relations existantes pour les passer au JS en JSON
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

        $this->context->smarty->assign([
            'ms_id_product'     => $idProduct,
            'ms_ajax_url'       => $this->context->link->getModuleLink(
                'megaservice_relations',
                'admin',
                [],
                true
            ),
            'ms_relations_json' => json_encode($relations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return $this->display(__FILE__, 'admin-products-extra.tpl');
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

    /**
     * Hook header BO — charge le CSS/JS de l'onglet relations sur la page produit.
     */
    public function hookDisplayBackOfficeHeader()
    {
        $controller = Tools::getValue('controller');
        // PS 8 : page produit = AdminProducts (legacy) OU AdminProductsV2 (modern)
        if (!in_array($controller, ['AdminProducts', 'AdminProductsV2'])) {
            return;
        }
        $this->context->controller->addCSS($this->_path . 'views/css/admin-product-relations.css');
        $this->context->controller->addJS($this->_path . 'views/js/admin-product-relations.js');
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
        $sql = 'SELECT i.`id_image` FROM `' . _DB_PREFIX_ . 'image` i
                WHERE i.`id_product` = ' . (int) $idProduct . ' AND i.`cover` = 1
                LIMIT 1';
        $idImage = (int) Db::getInstance()->getValue($sql);
        if (!$idImage) {
            return '';
        }
        $product = new Product($idProduct, false, $this->context->language->id);
        return $this->context->link->getImageLink($product->link_rewrite, $idImage, 'small_default');
    }
}
