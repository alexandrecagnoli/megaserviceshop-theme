<?php
/**
 * PR7 — PDP microfiche : vue éclatée d'une partie moto + hotspots cliquables
 * + liste des pièces (réf OEM, qté recommandée, prix/panier si produit lié).
 *
 * URL V1 : /module/megaservice_microfiches/microfiche?id_microfiche=123
 *
 * Conforme à la maquette PDP_microfiche : image éclatée à gauche avec pastilles
 * numérotées positionnées, liste des pièces à droite. Les pièces dont le hotspot
 * est lié à un produit (PR8) affichent prix + dispo + ajout panier ; les autres
 * (id_product NULL) affichent un état "Bientôt disponible" (réf seule).
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'megaservice_microfiches/classes/MsMicrofiche.php';
require_once _PS_MODULE_DIR_ . 'megaservice_microfiches/classes/MsMicroficheCategorie.php';
require_once _PS_MODULE_DIR_ . 'megaservice_microfiches/classes/MsMoto.php';

class Megaservice_microfichesMicroficheModuleFrontController extends ModuleFrontController
{
    /** @var MsMicrofiche|null */
    protected $microfiche;
    /** @var MsMoto|null */
    protected $moto;
    /** @var MsMicroficheCategorie|null */
    protected $categorie;

    public function init()
    {
        $idMicrofiche = (int) Tools::getValue('id_microfiche');

        if ($idMicrofiche > 0) {
            $microfiche = new MsMicrofiche($idMicrofiche);
            if (Validate::isLoadedObject($microfiche) && (bool) $microfiche->active) {
                $this->microfiche = $microfiche;
                $this->moto       = new MsMoto((int) $microfiche->id_moto);
                $this->categorie  = new MsMicroficheCategorie((int) $microfiche->id_categorie);
            }
        }

        if ($this->microfiche === null || !Validate::isLoadedObject($this->moto)) {
            Tools::redirect('index.php?controller=404');
        }

        parent::init();
    }

    public function initContent()
    {
        parent::initContent();

        $hotspots = $this->fetchHotspots((int) $this->microfiche->id);

        $this->context->smarty->assign([
            'ms_microfiche' => $this->microficheToTemplate(),
            'ms_moto'       => $this->motoToTemplate(),
            'ms_hotspots'   => $hotspots,
            'ms_related'    => $this->fetchRelatedMicrofiches(),
            'ms_cart_url'   => $this->context->shop->getBaseURL(true) . 'index.php',
            'ms_cart_token' => Tools::getToken(false),
        ]);

        $this->setTemplate('module:megaservice_microfiches/views/templates/front/microfiche.tpl');
    }

    /**
     * Hotspots de la microfiche, ordonnés par séquence, enrichis du produit lié
     * (prix TTC + dispo) et de leur position overlay en pourcentages.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function fetchHotspots(int $idMicrofiche): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT `id_hotspot`, `id_product`, `article_ref`, `article_label`,
                    `sequence_number`, `position_x`, `position_y`, `qty_recommended`
             FROM `' . _DB_PREFIX_ . 'ms_microfiche_hotspot`
             WHERE `id_microfiche` = ' . $idMicrofiche . '
             ORDER BY `sequence_number` ASC, `id_hotspot` ASC'
        ) ?: [];

        $imgW = max(1, (int) $this->microfiche->image_width);
        $imgH = max(1, (int) $this->microfiche->image_height);

        foreach ($rows as &$h) {
            $h['id_hotspot']      = (int) $h['id_hotspot'];
            $h['sequence_number'] = (int) $h['sequence_number'];
            $h['qty_recommended'] = max(1, (int) $h['qty_recommended']);
            // Position overlay : x depuis la gauche, y depuis le BAS (convention
            // BO, cf. AdminMsMicrofichesController). Bornées [0,100].
            $h['pos_left']   = min(100, max(0, (int) $h['position_x'] / $imgW * 100));
            $h['pos_bottom'] = min(100, max(0, (int) $h['position_y'] / $imgH * 100));

            $idProduct = (int) $h['id_product'];
            $h['has_product'] = false;
            if ($idProduct > 0) {
                $this->enrichWithProduct($h, $idProduct);
            }
        }
        unset($h);

        return $rows;
    }

    /**
     * Ajoute prix TTC + dispo + lien produit à un hotspot lié. Garde-fou si le
     * produit a été supprimé/désactivé entre-temps (id_product dangling) :
     * on retombe sur l'état "non lié".
     *
     * @param array<string,mixed> $h
     */
    protected function enrichWithProduct(array &$h, int $idProduct): void
    {
        $product = new Product($idProduct, false, (int) $this->context->language->id);
        if (!Validate::isLoadedObject($product) || !(bool) $product->active) {
            return; // dangling → reste orphelin côté affichage
        }

        $price = (float) Product::getPriceStatic($idProduct, true);
        $qty   = (int) StockAvailable::getQuantityAvailableByProduct($idProduct);

        $h['has_product']  = true;
        $h['id_product']   = $idProduct;
        $h['product_url']  = $this->context->link->getProductLink($product);
        $h['price']        = $this->context->currentLocale
            ? $this->context->currentLocale->formatPrice($price, $this->context->currency->iso_code)
            : Tools::displayPrice($price);
        $h['available']    = $qty > 0 || (int) $product->out_of_stock === 1; // 1 = autoriser commande
        $h['quantity']     = $qty;
    }

    /**
     * Autres microfiches de la même moto (carrousel "pièces d'origine
     * compatibles"). Exclut la microfiche courante. Limité à 8.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function fetchRelatedMicrofiches(): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT mf.`id_microfiche`, mf.`nom_constructeur`, mf.`nom_fr`,
                    mf.`image_thumb_url`, mf.`image_full_url`,
                    COUNT(h.`id_hotspot`) AS nb_pieces
             FROM `' . _DB_PREFIX_ . 'ms_microfiche` mf
             LEFT JOIN `' . _DB_PREFIX_ . 'ms_microfiche_hotspot` h
                    ON h.`id_microfiche` = mf.`id_microfiche`
             WHERE mf.`id_moto` = ' . (int) $this->moto->id . '
               AND mf.`active` = 1
               AND mf.`id_microfiche` <> ' . (int) $this->microfiche->id . '
             GROUP BY mf.`id_microfiche`
             ORDER BY mf.`nom_constructeur` ASC
             LIMIT 8'
        ) ?: [];

        foreach ($rows as &$r) {
            $r['id_microfiche'] = (int) $r['id_microfiche'];
            $r['nb_pieces']     = (int) $r['nb_pieces'];
            $r['display_name']  = ($r['nom_fr'] !== null && $r['nom_fr'] !== '')
                ? $r['nom_fr'] : $r['nom_constructeur'];
            $r['thumb']         = ($r['image_thumb_url'] !== null && $r['image_thumb_url'] !== '')
                ? $r['image_thumb_url'] : $r['image_full_url'];
            $r['url']           = $this->context->link->getModuleLink(
                'megaservice_microfiches', 'microfiche',
                ['id_microfiche' => $r['id_microfiche']]
            );
        }
        unset($r);

        return $rows;
    }

    /** @return array<string,mixed> */
    protected function microficheToTemplate(): array
    {
        $name = ($this->microfiche->nom_fr !== null && $this->microfiche->nom_fr !== '')
            ? $this->microfiche->nom_fr
            : $this->microfiche->nom_constructeur;

        return [
            'id_microfiche' => (int) $this->microfiche->id,
            'display_name'  => $name,
            'image_url'     => $this->microfiche->image_full_url,
            'image_width'   => (int) $this->microfiche->image_width,
            'image_height'  => (int) $this->microfiche->image_height,
            'partie'        => Validate::isLoadedObject($this->categorie) ? $this->categorie->partie : '',
        ];
    }

    /** @return array<string,mixed> */
    protected function motoToTemplate(): array
    {
        return [
            'id_moto'   => (int) $this->moto->id,
            'marque'    => $this->moto->marque,
            'annee'     => (int) $this->moto->annee,
            'nom_fr'    => $this->moto->nom_fr,
            'core_name' => $this->moto->core_name,
            'picture'   => $this->moto->picture_main,
            'url'       => $this->context->link->getModuleLink(
                'megaservice_microfiches', 'moto',
                ['id_moto' => (int) $this->moto->id]
            ),
        ];
    }

    /**
     * Fil d'Ariane : Accueil > Pièces détachées > [moto] > [microfiche].
     */
    public function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();

        $breadcrumb['links'][] = [
            'title' => $this->module->l('Pièces détachées d\'origine', 'microfiche'),
            'url'   => '#',
        ];
        $breadcrumb['links'][] = [
            'title' => $this->moto->nom_fr,
            'url'   => $this->context->link->getModuleLink(
                'megaservice_microfiches', 'moto', ['id_moto' => (int) $this->moto->id]
            ),
        ];
        $breadcrumb['links'][] = [
            'title' => $this->microfiche->nom_fr ?: $this->microfiche->nom_constructeur,
            'url'   => $this->context->link->getModuleLink(
                'megaservice_microfiches', 'microfiche', ['id_microfiche' => (int) $this->microfiche->id]
            ),
        ];

        return $breadcrumb;
    }
}
