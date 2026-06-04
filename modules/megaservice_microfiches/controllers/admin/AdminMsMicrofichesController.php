<?php
/**
 * Listing read-only des microfiches (ps_ms_microfiche).
 *
 * Brief §6.1.D : permet de visualiser les microfiches importées (par moto,
 * catégorie, nom constructeur, dimensions image, nb hotspots).
 *
 * V1 strict read-only :
 *   - pas de création (microfiches créées exclusivement par MicrofichesImporter)
 *   - pas d'édition (mappings constructeur sont la source de vérité)
 *   - pas de suppression (préservation données — supprimer en SQL si vraiment besoin)
 *
 * L'édition (renommage FR, désactivation, hotspots) viendra en V2 avec
 * l'éditeur visuel d'hotspots (PR5 du brief).
 */
class AdminMsMicrofichesController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap    = true;
        $this->table        = 'ms_microfiche';
        $this->className    = 'MsMicrofiche';
        $this->identifier   = 'id_microfiche';
        $this->lang         = false;
        $this->allow_export = true;

        parent::__construct();

        // JOIN moto + catégorie pour affichage lisible + filtres.
        // + sous-requête nb_hotspots.
        $this->_select = 'm.`marque` AS `moto_marque`, '
                       . 'm.`annee` AS `moto_annee`, '
                       . 'm.`nom_fr` AS `moto_nom`, '
                       . 'm.`serial_constructeur` AS `moto_serial`, '
                       . 'c.`partie` AS `cat_partie`, '
                       . 'c.`numero_constructeur` AS `cat_numero`, '
                       . 'c.`nom_fr` AS `cat_nom_fr`, '
                       . '(SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'ms_microfiche_hotspot` h '
                       . 'WHERE h.`id_microfiche` = a.`id_microfiche`) AS `nb_hotspots`';
        $this->_join = 'LEFT JOIN `' . _DB_PREFIX_ . 'ms_moto` m ON (m.`id_moto` = a.`id_moto`) '
                     . 'LEFT JOIN `' . _DB_PREFIX_ . 'ms_microfiche_categorie` c ON (c.`id_categorie` = a.`id_categorie`)';

        $this->fields_list = [
            'id_microfiche' => [
                'title' => 'ID',
                'width' => 50,
                'align' => 'left',
            ],
            'moto_marque' => [
                'title'      => 'Marque',
                'filter_key' => 'm!marque',
                'width'      => 80,
            ],
            'moto_annee' => [
                'title'      => 'Année',
                'filter_key' => 'm!annee',
                'align'      => 'right',
                'width'      => 70,
            ],
            'moto_nom' => [
                'title'      => 'Moto',
                'filter_key' => 'm!nom_fr',
            ],
            'moto_serial' => [
                'title'      => 'Serial constructeur',
                'filter_key' => 'm!serial_constructeur',
                'width'      => 120,
            ],
            'cat_partie' => [
                'title'      => 'Partie',
                'type'       => 'select',
                'list'       => ['cycle' => 'Cycle', 'moteur' => 'Moteur'],
                'filter_key' => 'c!partie',
                'width'      => 80,
            ],
            'cat_numero' => [
                'title'      => 'N° cat.',
                'filter_key' => 'c!numero_constructeur',
                'align'      => 'right',
                'width'      => 70,
            ],
            'cat_nom_fr' => [
                'title'      => 'Catégorie',
                'filter_key' => 'c!nom_fr',
                'callback'   => 'highlightMissingCategoryName',
            ],
            'nom_constructeur' => [
                'title' => 'Nom constructeur',
            ],
            'image_width' => [
                'title'  => 'L',
                'align'  => 'right',
                'search' => false,
                'width'  => 50,
            ],
            'image_height' => [
                'title'  => 'H',
                'align'  => 'right',
                'search' => false,
                'width'  => 50,
            ],
            'nb_hotspots' => [
                'title'   => 'Hotspots',
                'align'   => 'right',
                'search'  => false,
                'orderby' => false,
                'width'   => 80,
            ],
            'active' => [
                'title'  => 'Actif',
                'active' => 'status',
                'type'   => 'bool',
                'align'  => 'center',
                'width'  => 60,
            ],
        ];

        $this->_defaultOrderBy  = 'id_microfiche';
        $this->_defaultOrderWay = 'ASC';

        // Pas de bouton edit/delete — listing pure read-only en V1.
        // Mais on ajoute "view" pour l'éditeur visuel hotspots (PR5).
        $this->addRowAction('view');
    }

    /**
     * Page d'inspection visuelle d'une microfiche : affiche l'image de la
     * vue éclatée avec tous ses hotspots overlay en cercles cliquables aux
     * coordonnées (position_x, position_y) issues du CSV constructeur.
     *
     * Conventions de position (brief §4.3) :
     *   - position_x   : pixels depuis la gauche de l'image (CSS `left`)
     *   - position_y   : pixels depuis le BAS de l'image     (CSS `bottom`)
     *   - image_width  : largeur native de l'image
     *   - image_height : hauteur native de l'image
     *
     * On positionne en pourcentages plutôt qu'en pixels pour que les
     * hotspots restent alignés quand l'image est rétrécie (responsive).
     */
    public function renderView()
    {
        /** @var MsMicrofiche|null $micro */
        $micro = $this->loadObject(true);
        if (!$micro) {
            return '';
        }

        $moto = new MsMoto((int) $micro->id_moto);
        $cat  = new MsMicroficheCategorie((int) $micro->id_categorie);

        $hotspots = (array) Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'ms_microfiche_hotspot` '
            . 'WHERE `id_microfiche` = ' . (int) $micro->id_microfiche
            . ' ORDER BY `sequence_number`, `id_hotspot`'
        );

        return $this->renderHotspotsView($micro, $moto, $cat, $hotspots);
    }

    /**
     * @param array<int, array<string, mixed>> $hotspots
     */
    private function renderHotspotsView(MsMicrofiche $micro, MsMoto $moto, MsMicroficheCategorie $cat, array $hotspots): string
    {
        $imageUrl  = htmlspecialchars((string) $micro->image_full_url, ENT_QUOTES, 'UTF-8');
        $imgWidth  = max(1, (int) $micro->image_width);
        $imgHeight = max(1, (int) $micro->image_height);

        // Génération des overlays hotspot — positions en pourcentage pour
        // suivre le redimensionnement responsive de l'image.
        $overlaysHtml = '';
        foreach ($hotspots as $h) {
            $leftPct   = ((int) $h['position_x']) / $imgWidth  * 100;
            $bottomPct = ((int) $h['position_y']) / $imgHeight * 100;
            $title     = sprintf(
                '#%d — %s%s (qté: %d)',
                (int) $h['sequence_number'],
                (string) $h['article_ref'],
                $h['article_label'] ? ' — ' . $h['article_label'] : '',
                (int) $h['qty_recommended']
            );
            $cls = ((int) ($h['id_product'] ?? 0)) > 0
                ? 'ms-hotspot ms-hotspot--linked'
                : 'ms-hotspot ms-hotspot--orphan';
            $overlaysHtml .= sprintf(
                '<div class="%s" style="left:%.3f%%;bottom:%.3f%%" title="%s" '
                . 'data-seq="%d" data-ref="%s">%d</div>',
                $cls,
                $leftPct, $bottomPct,
                htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
                (int) $h['sequence_number'],
                htmlspecialchars((string) $h['article_ref'], ENT_QUOTES, 'UTF-8'),
                (int) $h['sequence_number']
            );
        }

        // CSS inline (1 fichier admin, autonome, pas de dépendance externe).
        $css = '<style>
.ms-microfiche-viewer { position:relative; display:inline-block; max-width:100%; line-height:0; background:#fafafa; border:1px solid #ddd; }
.ms-microfiche-viewer > img { display:block; max-width:100%; height:auto; }
.ms-hotspot { position:absolute; width:24px; height:24px; margin-left:-12px; margin-bottom:-12px;
              border-radius:50%; color:#fff; font-size:11px; font-weight:bold;
              text-align:center; line-height:20px; cursor:default;
              border:2px solid #fff; box-shadow:0 0 4px rgba(0,0,0,0.5);
              transition: transform 0.1s ease-out; user-select:none; }
.ms-hotspot--linked { background: rgba(40,167,69,0.85); }  /* vert : lié à un produit */
.ms-hotspot--orphan { background: rgba(255,128,0,0.85); }  /* orange : pas lié */
.ms-hotspot:hover { transform: scale(1.4); z-index:10; box-shadow:0 0 8px rgba(0,0,0,0.8); }
.ms-microfiche-meta { margin: 10px 0 20px; }
.ms-microfiche-meta dt { font-weight:bold; float:left; clear:left; width:160px; }
.ms-microfiche-meta dd { margin-left:170px; margin-bottom:4px; }
</style>';

        $catLabel = (string) ($cat->nom_fr ?: $cat->code);

        return $css
            . '<div class="panel">'
            . '<h3>' . htmlspecialchars($moto->nom_fr, ENT_QUOTES, 'UTF-8')
            . ' <small>(' . htmlspecialchars((string) $moto->serial_constructeur, ENT_QUOTES, 'UTF-8') . ')</small></h3>'
            . '<h4>' . htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8')
            . ' #' . (int) $cat->numero_constructeur
            . ' <small>(partie : ' . htmlspecialchars((string) $cat->partie, ENT_QUOTES, 'UTF-8') . ')</small></h4>'
            . '<h5>' . htmlspecialchars((string) $micro->nom_constructeur, ENT_QUOTES, 'UTF-8')
            . ' &mdash; <strong>' . count($hotspots) . ' hotspots</strong></h5>'
            . '<dl class="ms-microfiche-meta">'
            . '<dt>Image</dt><dd>' . $imgWidth . ' × ' . $imgHeight . ' px '
            . '— <a href="' . $imageUrl . '" target="_blank">URL d\'origine</a></dd>'
            . '<dt>Légende</dt>'
            . '<dd><span class="ms-hotspot ms-hotspot--linked" style="position:relative;display:inline-block;margin:0 8px 0 0;">●</span> lié à un produit Presta'
            . ' &nbsp;&nbsp; <span class="ms-hotspot ms-hotspot--orphan" style="position:relative;display:inline-block;margin:0 8px 0 0;">●</span> pas encore lié</dd>'
            . '</dl>'
            . '<div class="ms-microfiche-viewer">'
            . '<img src="' . $imageUrl . '" alt="" width="' . $imgWidth . '" height="' . $imgHeight . '" />'
            . $overlaysHtml
            . '</div>'
            . '<p class="help-block" style="margin-top:15px"><em>Survoler un cercle pour voir le détail (référence + libellé + quantité). '
            . 'V1 : visualisation seule, l\'édition des positions viendra dans une PR ultérieure.</em></p>'
            . '</div>';
    }

    /**
     * Affiche "(non renommée)" si la catégorie a nom_fr NULL/vide
     * (catégorie auto-créée pas encore complétée en BO).
     * Callback fields_list.
     */
    public function highlightMissingCategoryName($nom, $row): string
    {
        if ($nom === null || $nom === '') {
            return '<em class="text-muted">(non renommée)</em>';
        }
        return htmlspecialchars((string) $nom, ENT_QUOTES, 'UTF-8');
    }
}
