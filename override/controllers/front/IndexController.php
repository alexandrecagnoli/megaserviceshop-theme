<?php

class IndexController extends IndexControllerCore
{
    /**
     * Flux de produits de la page d'accueil, par catégorie.
     *
     * Les trois sections existantes (Nouveautés, Promos, Produits) viennent de
     * modules PrestaShop, qui ne savent remonter que des listes globales. Ces
     * trois-ci sont des entrées de catalogue : on les alimente ici plutôt que
     * d'installer trois instances de plus d'un module générique.
     *
     * Clé → [id_category, nombre de produits].
     */
    private static $HOME_SECTIONS = [
        'lifestyle'  => [14, 8], // Lifestyle (vêtements)
        'rider'      => [15, 8], // Équipements pilotes
        'new_motos'  => [10, 8], // Motos neuves
    ];

    public function initContent()
    {
        parent::initContent();

        $this->context->smarty->assign('ms_home_sections', $this->homeSections());
    }

    /**
     * @return array<string, array{title:string, url:string, products:array}>
     */
    private function homeSections()
    {
        // Le présentateur vit dans megaservice_relations : si le module est
        // désinstallé, les sections s'effacent au lieu de casser l'accueil.
        if (!$this->presenterAvailable()) {
            return [];
        }

        $out = [];
        foreach (self::$HOME_SECTIONS as $key => $section) {
            list($idCategory, $limit) = $section;

            $ids = $this->categoryProductIds($idCategory, $limit);
            if (!$ids) {
                continue;
            }

            $category = new Category((int) $idCategory, (int) $this->context->language->id);
            if (!$category->id) {
                continue;
            }

            $out[$key] = [
                'title'    => $category->name,
                'url'      => $this->context->link->getCategoryLink($category),
                'products' => MsProductRelationService::presentProducts($ids, $this->context),
            ];
        }

        return $out;
    }

    private function presenterAvailable()
    {
        if (class_exists('MsProductRelationService')) {
            return true;
        }

        $file = _PS_MODULE_DIR_ . 'megaservice_relations/classes/ProductRelationService.php';
        if (is_file($file)) {
            require_once $file;
        }

        return class_exists('MsProductRelationService');
    }

    /**
     * Les derniers produits d'une catégorie ET DE SES ENFANTS, photo obligatoire.
     *
     * Le sous-arbre, parce que « Lifestyle » ou « Équipements pilotes » ne
     * portent rien en propre : tout est dans leurs sous-catégories.
     *
     * La photo est exigée en SQL et non laissée au template : home-products-section
     * écarte déjà les produits sans visuel (règle de l'accueil), mais il le fait
     * APRÈS coup — on aurait demandé 8 produits pour n'en afficher que 3.
     *
     * @return int[]
     */
    private function categoryProductIds($idCategory, $limit)
    {
        $idShop = (int) $this->context->shop->id;

        $rows = Db::getInstance()->executeS(
            'SELECT p.`id_product`
               FROM `' . _DB_PREFIX_ . 'product` p
               JOIN `' . _DB_PREFIX_ . 'product_shop` ps
                 ON ps.`id_product` = p.`id_product` AND ps.`id_shop` = ' . $idShop . '
               JOIN `' . _DB_PREFIX_ . 'category_product` cp
                 ON cp.`id_product` = p.`id_product`
               JOIN `' . _DB_PREFIX_ . 'category` c
                 ON c.`id_category` = cp.`id_category` AND c.`active` = 1
               JOIN `' . _DB_PREFIX_ . 'category` root
                 ON root.`id_category` = ' . (int) $idCategory . '
              WHERE ps.`active` = 1
                AND ps.`visibility` IN ("both", "catalog")
                AND c.`nleft` >= root.`nleft`
                AND c.`nright` <= root.`nright`
                AND EXISTS (SELECT 1 FROM `' . _DB_PREFIX_ . 'image` i
                             WHERE i.`id_product` = p.`id_product`)
              GROUP BY p.`id_product`
              ORDER BY p.`date_add` DESC
              LIMIT ' . (int) $limit
        ) ?: [];

        return array_map('intval', array_column($rows, 'id_product'));
    }
}
