<?php
/**
 * Listing + édition des hotspots (ps_ms_microfiche_hotspot).
 *
 * Permet à l'admin de corriger manuellement un hotspot mal référencé par
 * le CSV constructeur (faute sur article_ref, mauvaise séquence, etc.)
 * sans avoir à modifier le CSV source. Édition des données seulement —
 * le drag/drop visuel des positions est planifié pour la V2 (brief §6.4).
 *
 * V1 : pas de création / suppression possible depuis le BO. Les hotspots
 * sont créés exclusivement par MicrofichesImporter, supprimés via CASCADE
 * quand la microfiche parent est supprimée.
 */
class AdminMsHotspotsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap    = true;
        $this->table        = 'ms_microfiche_hotspot';
        $this->className    = 'MsMicroficheHotspot';
        $this->identifier   = 'id_hotspot';
        $this->lang         = false;
        $this->allow_export = true;

        parent::__construct();

        // JOIN microfiche + catégorie + moto pour affichage lisible et filtres.
        $this->_select = 'mf.`nom_constructeur` AS `microfiche_nom`, '
                       . 'mf.`id_moto` AS `id_moto`, '
                       . 'c.`partie` AS `cat_partie`, '
                       . 'c.`numero_constructeur` AS `cat_numero`, '
                       . 'm.`marque` AS `moto_marque`, '
                       . 'm.`annee` AS `moto_annee`, '
                       . 'm.`serial_constructeur` AS `moto_serial`';
        $this->_join = 'LEFT JOIN `' . _DB_PREFIX_ . 'ms_microfiche` mf ON (mf.`id_microfiche` = a.`id_microfiche`) '
                     . 'LEFT JOIN `' . _DB_PREFIX_ . 'ms_microfiche_categorie` c ON (c.`id_categorie` = mf.`id_categorie`) '
                     . 'LEFT JOIN `' . _DB_PREFIX_ . 'ms_moto` m ON (m.`id_moto` = mf.`id_moto`)';

        $this->fields_list = [
            'id_hotspot' => [
                'title' => 'ID',
                'width' => 50,
                'align' => 'left',
            ],
            'moto_marque' => [
                'title'      => 'Marque',
                'filter_key' => 'm!marque',
                'width'      => 70,
            ],
            'moto_serial' => [
                'title'      => 'Serial moto',
                'filter_key' => 'm!serial_constructeur',
                'width'      => 100,
            ],
            'cat_partie' => [
                'title'      => 'Partie',
                'type'       => 'select',
                'list'       => ['cycle' => 'Cycle', 'moteur' => 'Moteur'],
                'filter_key' => 'c!partie',
                'width'      => 70,
            ],
            'microfiche_nom' => [
                'title'      => 'Microfiche',
                'filter_key' => 'mf!nom_constructeur',
            ],
            'sequence_number' => [
                'title' => '#',
                'align' => 'right',
                'width' => 50,
            ],
            'article_ref' => [
                'title'    => 'Référence OEM',
                'callback' => 'renderArticleRefWithLink',
                'width'    => 160,
            ],
            'article_label' => [
                'title' => 'Libellé',
            ],
            'position_x' => [
                'title' => 'X',
                'align' => 'right',
                'width' => 50,
            ],
            'position_y' => [
                'title' => 'Y',
                'align' => 'right',
                'width' => 50,
            ],
            'qty_recommended' => [
                'title' => 'Qté',
                'align' => 'right',
                'width' => 50,
            ],
            'id_product' => [
                'title'    => 'Produit',
                'callback' => 'renderProductStatus',
                'search'   => false,
                'orderby'  => false,
                'width'    => 80,
            ],
        ];

        $this->_defaultOrderBy  = 'id_hotspot';
        $this->_defaultOrderWay = 'ASC';

        $this->addRowAction('edit');
    }

    public function renderForm()
    {
        /** @var MsMicroficheHotspot|null $obj */
        $obj = $this->loadObject(true);
        if (!$obj) {
            return '';
        }

        // Récupère le contexte parent (microfiche + moto + catégorie) pour
        // l'afficher en lecture seule au-dessus du form — l'admin sait sur
        // quoi il édite.
        $contextDesc = $this->buildHotspotContextDescription((int) $obj->id_microfiche);

        $this->fields_form = [
            'legend' => [
                'title' => 'Hotspot',
                'icon'  => 'icon-map-marker',
            ],
            'description' => $contextDesc,
            'input' => [
                [
                    'type'  => 'hidden',
                    'name'  => 'id_microfiche',
                ],
                [
                    'type'     => 'text',
                    'label'    => 'Référence OEM',
                    'name'     => 'article_ref',
                    'required' => true,
                    'desc'     => 'Référence constructeur de la pièce. Sert de clé de matching vers ps_product.reference.',
                ],
                [
                    'type'  => 'text',
                    'label' => 'Libellé constructeur',
                    'name'  => 'article_label',
                    'desc'  => 'Libellé tel que renvoyé par le CSV constructeur (ex. "Engine case cmpl.").',
                ],
                [
                    'type'     => 'text',
                    'label'    => 'Numéro de séquence',
                    'name'     => 'sequence_number',
                    'required' => true,
                    'desc'     => 'Numéro affiché à l\'intérieur du cercle hotspot sur le schéma.',
                ],
                [
                    'type'     => 'text',
                    'label'    => 'Position X (px depuis la gauche)',
                    'name'     => 'position_x',
                    'required' => true,
                    'desc'     => 'Coordonnée en pixels sur l\'image originale, côté coin gauche du marqueur.',
                ],
                [
                    'type'     => 'text',
                    'label'    => 'Position Y (px depuis le bas)',
                    'name'     => 'position_y',
                    'required' => true,
                    'desc'     => 'Coordonnée en pixels sur l\'image originale, côté coin bas du marqueur (convention constructeur).',
                ],
                [
                    'type'     => 'text',
                    'label'    => 'Quantité recommandée',
                    'name'     => 'qty_recommended',
                    'required' => true,
                    'desc'     => 'Nombre d\'unités utilisées sur la moto (1 par défaut, peut être 0 pour les pièces issues d\'un kit).',
                ],
                [
                    'type'  => 'text',
                    'label' => 'ID produit Presta (lien manuel)',
                    'name'  => 'id_product',
                    'desc'  => 'Laisser vide pour laisser le cron de rematching trouver automatiquement le produit via article_ref. '
                             . 'À renseigner uniquement pour forcer un lien manuel à un id_product Presta spécifique.',
                ],
            ],
            'submit' => ['title' => 'Enregistrer'],
        ];

        return parent::renderForm();
    }

    /**
     * Génère un résumé HTML du contexte d'un hotspot (moto + microfiche + catégorie).
     * Affiché en haut du formulaire d'édition pour orienter l'admin.
     */
    private function buildHotspotContextDescription(int $idMicrofiche): string
    {
        if ($idMicrofiche <= 0) {
            return '';
        }
        $row = Db::getInstance()->getRow(
            'SELECT m.`marque`, m.`annee`, m.`nom_fr` AS `moto_nom`, m.`serial_constructeur`, '
            . 'mf.`nom_constructeur` AS `microfiche_nom`, '
            . 'c.`partie`, c.`numero_constructeur`, c.`nom_fr` AS `cat_nom` '
            . 'FROM `' . _DB_PREFIX_ . 'ms_microfiche` mf '
            . 'JOIN `' . _DB_PREFIX_ . 'ms_moto` m ON (m.`id_moto` = mf.`id_moto`) '
            . 'JOIN `' . _DB_PREFIX_ . 'ms_microfiche_categorie` c ON (c.`id_categorie` = mf.`id_categorie`) '
            . 'WHERE mf.`id_microfiche` = ' . $idMicrofiche
        );
        if (!$row) {
            return '';
        }
        $catLabel = (string) ($row['cat_nom'] ?: $row['partie']);
        return sprintf(
            '<div class="alert alert-info" style="margin-bottom:15px">'
            . '<strong>Moto :</strong> %s — %s (serial %s)<br>'
            . '<strong>Catégorie :</strong> %s #%d (%s)<br>'
            . '<strong>Microfiche :</strong> %s'
            . '</div>',
            htmlspecialchars((string) $row['marque'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string) $row['moto_nom'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string) $row['serial_constructeur'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8'),
            (int) $row['numero_constructeur'],
            htmlspecialchars((string) $row['partie'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string) $row['microfiche_nom'], ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Affiche la ref OEM avec lien vers la fiche produit Presta si liée.
     * Callback fields_list.
     */
    public function renderArticleRefWithLink($value, $row): string
    {
        $ref = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $idProduct = (int) ($row['id_product'] ?? 0);
        if ($idProduct > 0) {
            $link = $this->context->link->getAdminLink('AdminProducts', true)
                  . '&id_product=' . $idProduct . '&updateproduct';
            return '<a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" target="_blank"><code>'
                . $ref . '</code></a>';
        }
        return '<code>' . $ref . '</code>';
    }

    /**
     * Badge vert/orange selon si id_product est rempli.
     * Callback fields_list.
     */
    public function renderProductStatus($value, $row): string
    {
        $idProduct = (int) $value;
        if ($idProduct > 0) {
            return '<span class="label label-success" title="Lié à id_product=' . $idProduct . '">✓ Lié</span>';
        }
        return '<span class="label label-warning" title="Aucun produit Presta avec cette référence">Orphelin</span>';
    }

    /**
     * Bypass la verification CSRF pour les requetes AJAX (ajax=1 dans l URL).
     *
     * Motivation : Tools::getAdminTokenLite / Tab::getIdFromClassName ont
     * des cas limites (Tab pas (encore) cree, employee_id different selon
     * generation) qui font echouer la verification standard avec une page
     * HTML "Cle de securite invalide" — completement opaque cote JS qui
     * tente de parser ca en JSON.
     *
     * La session admin (employee_id en cookie) reste obligatoire : PrestaShop
     * bloque l acces aux ModuleAdminController avant que checkToken soit
     * appele si l employee n est pas authentifie. Donc on ne perd pas
     * grand-chose sur cette surface AJAX privee.
     *
     * A reconsiderer en V2 : implementer un mecanisme nonce custom (token
     * propre au module, renouvele a chaque pageview de l editeur).
     */
    public function checkToken()
    {
        if (Tools::getValue('ajax')) {
            return true;
        }
        return parent::checkToken();
    }

    // =====================================================================
    // AJAX endpoints (utilisés par l'éditeur drag/drop visuel)
    // =====================================================================

    /**
     * AJAX : sauvegarde la position d'un hotspot après drag&drop.
     * Set manually_edited = 1 pour protéger la position contre l'écrasement
     * par un futur réimport CSV.
     *
     * URL : index.php?controller=AdminMsHotspots&token=...&ajax=1&action=SaveHotspotPosition
     * POST : id_hotspot, position_x, position_y
     * Réponse JSON : {ok: bool, position_x: int, position_y: int, manually_edited: bool, error?: string}
     */
    public function ajaxProcessSaveHotspotPosition()
    {
        $idHotspotRaw = Tools::getValue('id_hotspot');
        $posXRaw      = Tools::getValue('position_x');
        $posYRaw      = Tools::getValue('position_y');

        $idHotspot = (int) $idHotspotRaw;
        if ($idHotspot <= 0) {
            $this->jsonReply([
                'ok'    => false,
                'error' => 'id_hotspot manquant ou invalide.',
                'debug' => ['id_hotspot' => $idHotspotRaw, 'post_keys' => array_keys($_POST), 'get_keys' => array_keys($_GET)],
            ]);
            return;
        }

        // Accepte les entiers (int natif) ET les strings numériques.
        // ctype_digit échoue sur '0' précédé d'un - ou sur les ints natifs
        // (le cast (string) marche mais le check 'is_numeric' est plus permissif).
        if ($posXRaw === false || $posXRaw === '' || !is_numeric($posXRaw)
            || $posYRaw === false || $posYRaw === '' || !is_numeric($posYRaw)) {
            $this->jsonReply([
                'ok'    => false,
                'error' => 'position_x / position_y manquants ou non numériques.',
                'debug' => [
                    'position_x' => $posXRaw, 'type_x' => gettype($posXRaw),
                    'position_y' => $posYRaw, 'type_y' => gettype($posYRaw),
                ],
            ]);
            return;
        }
        $posX = (int) $posXRaw;
        $posY = (int) $posYRaw;
        if ($posX < 0 || $posY < 0) {
            $this->jsonReply(['ok' => false, 'error' => 'Positions négatives non autorisées.']);
            return;
        }

        $hotspot = new MsMicroficheHotspot($idHotspot);
        if (!Validate::isLoadedObject($hotspot)) {
            $this->jsonReply(['ok' => false, 'error' => 'Hotspot introuvable (id=' . $idHotspot . ').']);
            return;
        }

        $ok = (bool) Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'ms_microfiche_hotspot` '
            . 'SET `position_x` = ' . $posX . ', '
            . '    `position_y` = ' . $posY . ', '
            . '    `manually_edited` = 1 '
            . 'WHERE `id_hotspot` = ' . $idHotspot
        );

        if (!$ok) {
            $this->jsonReply(['ok' => false, 'error' => Db::getInstance()->getMsgError()]);
            return;
        }

        $this->jsonReply([
            'ok'              => true,
            'id_hotspot'      => $idHotspot,
            'position_x'      => $posX,
            'position_y'      => $posY,
            'manually_edited' => true,
        ]);
    }

    /**
     * AJAX : revert d'un hotspot manuellement édité vers sa position constructeur.
     * Recopie position_x_original → position_x (idem y) + set manually_edited = 0.
     * Si position_*_original est NULL (jamais initialisé), refuse l'opération.
     *
     * URL : ...&action=RevertHotspotPosition
     * POST : id_hotspot
     * Réponse JSON : {ok: bool, position_x: int, position_y: int, manually_edited: false, error?: string}
     */
    public function ajaxProcessRevertHotspotPosition()
    {
        $idHotspot = (int) Tools::getValue('id_hotspot');
        if ($idHotspot <= 0) {
            $this->jsonReply(['ok' => false, 'error' => 'Paramètre id_hotspot manquant ou invalide.']);
            return;
        }

        $row = Db::getInstance()->getRow(
            'SELECT `position_x_original`, `position_y_original` '
            . 'FROM `' . _DB_PREFIX_ . 'ms_microfiche_hotspot` '
            . 'WHERE `id_hotspot` = ' . $idHotspot
        );
        if (!$row) {
            $this->jsonReply(['ok' => false, 'error' => 'Hotspot introuvable.']);
            return;
        }
        if ($row['position_x_original'] === null || $row['position_y_original'] === null) {
            $this->jsonReply(['ok' => false, 'error' => 'Aucune position constructeur en référence (champs _original NULL). Le revert n\'est pas possible — il faudra réimporter le CSV constructeur ou drag/drop manuellement.']);
            return;
        }

        $posX = (int) $row['position_x_original'];
        $posY = (int) $row['position_y_original'];

        $ok = (bool) Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'ms_microfiche_hotspot` '
            . 'SET `position_x` = ' . $posX . ', '
            . '    `position_y` = ' . $posY . ', '
            . '    `manually_edited` = 0 '
            . 'WHERE `id_hotspot` = ' . $idHotspot
        );

        if (!$ok) {
            $this->jsonReply(['ok' => false, 'error' => Db::getInstance()->getMsgError()]);
            return;
        }

        $this->jsonReply([
            'ok'              => true,
            'id_hotspot'      => $idHotspot,
            'position_x'      => $posX,
            'position_y'      => $posY,
            'manually_edited' => false,
        ]);
    }

    /**
     * Helper : envoie une réponse JSON propre et termine l'exécution.
     * Sans ce die() explicite, PrestaShop continuerait le rendu et
     * concaténerait le JSON avec le HTML du layout admin classique
     * (-> "Unexpected token '<', '<!doctype...' côté fetch JS).
     *
     * @param array<string, mixed> $payload
     */
    private function jsonReply(array $payload): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');
        }
        echo json_encode($payload);
        die();
    }
}
