<?php
/**
 * Service métier pour les relations Powerparts.
 * Lit / écrit dans ps_megaservice_product_relation et présente les produits
 * au format attendu par le miniature template (cf. ProductListingPresenter PS).
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class MsProductRelationService
{
    const TYPE_MANDATORY   = 'mandatory';
    const TYPE_EXCLUDED    = 'excluded';
    const TYPE_RECOMMENDED = 'recommended';
    const TYPE_SPARE       = 'spare';

    public static function allTypes()
    {
        return [
            self::TYPE_MANDATORY,
            self::TYPE_EXCLUDED,
            self::TYPE_RECOMMENDED,
            self::TYPE_SPARE,
        ];
    }

    /**
     * Retourne les relations brutes (id_product_target, position, recommended_qty)
     * pour un produit source et un type donné.
     */
    public static function getRelations($idProduct, $type)
    {
        $idProduct = (int) $idProduct;
        if (!$idProduct || !in_array($type, self::allTypes(), true)) {
            return [];
        }

        $sql = 'SELECT `id_product_target`, `position`, `recommended_qty`
                FROM `' . _DB_PREFIX_ . 'megaservice_product_relation`
                WHERE `id_product_source` = ' . $idProduct . '
                AND `relation_type` = "' . pSQL($type) . '"
                ORDER BY `position` ASC, `id_relation` ASC';

        $rows = Db::getInstance()->executeS($sql);
        return is_array($rows) ? $rows : [];
    }

    /**
     * Retourne les IDs des produits liés (utilisé pour les tabs obligatoires/exclues/recommandées).
     */
    public static function getRelatedIds($idProduct, $type)
    {
        $rows = self::getRelations($idProduct, $type);
        return array_map('intval', array_column($rows, 'id_product_target'));
    }

    /**
     * Présente une liste d'IDs au format $accessories (clés : id_product, name,
     * cover, price, has_discount, regular_price, availability, add_to_cart_url, etc.)
     * via le ProductListingPresenter de PS.
     */
    public static function presentProducts(array $productIds, $context = null)
    {
        if (empty($productIds)) {
            return [];
        }
        if ($context === null) {
            $context = Context::getContext();
        }

        $assembler = new \ProductAssembler($context);
        $factory   = new \ProductPresenterFactory($context);
        $settings  = $factory->getPresentationSettings();
        $presenter = new \PrestaShop\PrestaShop\Adapter\Presenter\Product\ProductListingPresenter(
            new \PrestaShop\PrestaShop\Adapter\Image\ImageRetriever($context->link),
            $context->link,
            new \PrestaShop\PrestaShop\Adapter\Product\PriceFormatter(),
            new \PrestaShop\PrestaShop\Adapter\Product\ProductColorsRetriever(),
            $context->getTranslator()
        );

        $out = [];
        foreach ($productIds as $id) {
            try {
                $raw = $assembler->assembleProduct(['id_product' => (int) $id]);
                $out[] = $presenter->present($settings, $raw, $context->language);
            } catch (\Exception $e) {
                // produit inexistant ou cassé : skip silencieusement
            }
        }
        return $out;
    }

    /**
     * Helper : récupère + présente les produits liés pour un type donné.
     * Utilisé par le ProductController override pour les tabs.
     */
    public static function getPresentedRelations($idProduct, $type, $context = null)
    {
        $ids = self::getRelatedIds($idProduct, $type);
        return self::presentProducts($ids, $context);
    }

    /**
     * Cas particulier "spare parts" : retourne les rows présentés enrichis
     * avec recommended_qty (utilisée dans le list view des pièces de rechange).
     */
    public static function getSpareRows($idProduct, $context = null)
    {
        $rows = self::getRelations($idProduct, self::TYPE_SPARE);
        if (empty($rows)) {
            return [];
        }

        $qtyByProduct = [];
        foreach ($rows as $r) {
            $qtyByProduct[(int) $r['id_product_target']] = max(1, (int) $r['recommended_qty']);
        }

        $presented = self::presentProducts(array_keys($qtyByProduct), $context);

        $out = [];
        foreach ($presented as $p) {
            $idP = isset($p['id_product']) ? (int) $p['id_product'] : 0;
            if (!$idP) {
                continue;
            }
            $out[] = [
                'id_product'      => $idP,
                'name'            => isset($p['name']) ? $p['name'] : '',
                'reference'       => !empty($p['reference']) ? $p['reference'] : '',
                'price'           => isset($p['price']) ? $p['price'] : '',
                'availability'    => isset($p['availability']) ? $p['availability'] : 'available',
                'recommended_qty' => isset($qtyByProduct[$idP]) ? $qtyByProduct[$idP] : 1,
                'add_to_cart_url' => isset($p['add_to_cart_url']) ? $p['add_to_cart_url'] : '#',
            ];
        }
        return $out;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Setters (utilisés par BO + import CSV — Phase 3 et 4)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Remplace toutes les relations d'un type pour un produit source.
     * $items : array de ['id_product_target' => int, 'recommended_qty' => int (optional)]
     */
    public static function replaceRelations($idProduct, $type, array $items)
    {
        $idProduct = (int) $idProduct;
        if (!$idProduct || !in_array($type, self::allTypes(), true)) {
            return false;
        }

        $db = Db::getInstance();
        $db->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'megaservice_product_relation`
             WHERE `id_product_source` = ' . $idProduct . '
             AND `relation_type` = "' . pSQL($type) . '"'
        );

        $position = 0;
        foreach ($items as $item) {
            $idTarget = (int) (is_array($item) ? ($item['id_product_target'] ?? 0) : $item);
            if (!$idTarget || $idTarget === $idProduct) {
                continue;
            }
            $qty = max(1, (int) (is_array($item) ? ($item['recommended_qty'] ?? 1) : 1));

            $db->insert('megaservice_product_relation', [
                'id_product_source' => $idProduct,
                'id_product_target' => $idTarget,
                'relation_type'     => pSQL($type),
                'position'          => ++$position,
                'recommended_qty'   => $qty,
            ]);
        }
        return true;
    }

    /**
     * Ajoute une relation unique (utilisée par CSV import qui peut envoyer
     * plusieurs lignes pour un même couple — ON DUPLICATE KEY met à jour).
     */
    public static function addRelation($idSource, $type, $idTarget, $position = null, $recommendedQty = 1)
    {
        $idSource = (int) $idSource;
        $idTarget = (int) $idTarget;
        if (!$idSource || !$idTarget || $idSource === $idTarget || !in_array($type, self::allTypes(), true)) {
            return false;
        }
        $position = ($position === null) ? self::getNextPosition($idSource, $type) : (int) $position;
        $qty = max(1, (int) $recommendedQty);

        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'megaservice_product_relation`
                (`id_product_source`, `id_product_target`, `relation_type`, `position`, `recommended_qty`)
                VALUES (' . $idSource . ', ' . $idTarget . ', "' . pSQL($type) . '", ' . $position . ', ' . $qty . ')
                ON DUPLICATE KEY UPDATE `position` = VALUES(`position`), `recommended_qty` = VALUES(`recommended_qty`)';
        return Db::getInstance()->execute($sql);
    }

    private static function getNextPosition($idSource, $type)
    {
        $sql = 'SELECT MAX(`position`) FROM `' . _DB_PREFIX_ . 'megaservice_product_relation`
                WHERE `id_product_source` = ' . (int) $idSource . '
                AND `relation_type` = "' . pSQL($type) . '"';
        return ((int) Db::getInstance()->getValue($sql)) + 1;
    }
}
