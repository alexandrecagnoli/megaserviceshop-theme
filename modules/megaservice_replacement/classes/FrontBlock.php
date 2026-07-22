<?php
/**
 * Megaservice — Construction du bloc de remplacement affiché sur la fiche
 * produit front.
 *
 * Applique la matrice des cas de la SPEC §5.2 :
 *   A — remplaçant 1:1 actif et publié
 *   B — ensemble 1:N, tous les composants disponibles
 *   C — ensemble 1:N, disponibilité partielle
 *   D — remplaçant(s) présent(s) au catalogue mais indisponible(s)
 *   E — remplaçant absent du catalogue PrestaShop (écart de données)
 *
 * Les prix / stocks / noms sont lus EN TEMPS RÉEL sur les fiches produit
 * cibles : le module ne stocke aucune donnée commerciale, elle resterait
 * périmée entre deux synchronisations.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class MsReplacementFrontBlock
{
    const CASE_SINGLE_OK   = 'A';
    const CASE_SET_OK      = 'B';
    const CASE_SET_PARTIAL = 'C';
    const CASE_UNAVAILABLE = 'D';
    const CASE_MISSING     = 'E';

    /**
     * @return array<string,mixed>|null null si la référence n'est pas remplacée
     */
    public static function build($reference, Context $context)
    {
        $info = MsReplacementRepository::forReference($reference);
        if ($info === null || empty($info['replaced_by'])) {
            return null;
        }

        $targets   = [];
        $available = 0;
        $inCatalog = 0;
        $total     = 0;

        foreach ($info['replaced_by'] as $t) {
            ++$total;
            $product = $t['target_id']
                ? self::presentProduct((int) $t['target_id'], $context)
                : null;

            if ($product !== null) {
                ++$inCatalog;
            }
            $isAvailable = ($product !== null) && !empty($product['available']);
            if ($isAvailable) {
                ++$available;
            }

            $targets[] = [
                'ref'          => $t['ref_final'] !== null && $t['ref_final'] !== ''
                    ? $t['ref_final'] : $t['ref_replacement'],
                'quantity'     => (int) $t['quantity'],
                'product'      => $product,
                'available'    => $isAvailable,
                'chain_status' => $t['chain_status'],
            ];
        }

        $isSet = $total > 1;

        if ($inCatalog === 0) {
            $case = self::CASE_MISSING;              // E
        } elseif ($available === 0) {
            $case = self::CASE_UNAVAILABLE;          // D
        } elseif ($available === $total) {
            $case = $isSet ? self::CASE_SET_OK : self::CASE_SINGLE_OK;  // B / A
        } else {
            $case = self::CASE_SET_PARTIAL;          // C
        }

        return [
            'case'            => $case,
            'is_set'          => $isSet,
            'reference'       => $reference,
            'targets'         => $targets,
            'total_count'     => $total,
            'available_count' => $available,
            // Un ensemble peut être très gros (jusqu'à 101 composants constatés) :
            // le template replie la liste au-delà de ce seuil.
            'is_large_set'    => $total > 8,
        ];
    }

    /**
     * Données commerciales d'un produit cible, lues à la volée.
     *
     * @return array<string,mixed>|null
     */
    private static function presentProduct($idProduct, Context $context)
    {
        $product = new Product($idProduct, false, (int) $context->language->id);
        if (!Validate::isLoadedObject($product) || !$product->active) {
            return null;
        }

        $idAttribute = (int) Product::getDefaultAttribute($idProduct);
        $quantity    = (int) StockAvailable::getQuantityAvailableByProduct($idProduct, $idAttribute);

        $cover    = Product::getCover($idProduct);
        $imageUrl = '';
        if (!empty($cover['id_image'])) {
            $imageUrl = $context->link->getImageLink($product->link_rewrite, (int) $cover['id_image'], 'home_default');
        }

        return [
            'id_product'           => (int) $idProduct,
            'id_product_attribute' => $idAttribute,
            'name'                 => $product->name,
            'reference'            => $product->reference,
            'url'                  => $context->link->getProductLink($product),
            'image'                => $imageUrl,
            'price'                => Tools::displayPrice(Product::getPriceStatic($idProduct, true)),
            'quantity'             => $quantity,
            // Même règle que la PDP microfiche : le stock ne suffit pas, il faut
            // aussi que le produit soit réellement commandable.
            'available'            => $quantity > 0 && (bool) $product->available_for_order,
        ];
    }
}
