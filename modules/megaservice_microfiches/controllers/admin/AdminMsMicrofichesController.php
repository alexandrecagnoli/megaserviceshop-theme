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
.ms-microfiche-layout { display:flex; gap:20px; align-items:flex-start; flex-wrap:wrap; }
.ms-microfiche-layout > .ms-microfiche-viewer-wrap { flex:1 1 600px; min-width:300px; }
.ms-microfiche-layout > .ms-microfiche-list-wrap   { flex:1 1 380px; min-width:280px; }
.ms-microfiche-viewer { position:relative; display:inline-block; max-width:100%; line-height:0; background:#fafafa; border:1px solid #ddd; }
.ms-microfiche-viewer > img { display:block; max-width:100%; height:auto; }
/* Convention constructeur (KTM) : position_x/position_y designent le COIN
   du marqueur (pas son centre). On colle le coin du cercle a la position,
   avec 1px d ajustement empirique pour aligner pile poil. */
.ms-hotspot { position:absolute; width:24px; height:24px; margin-left:-1px; margin-bottom:-1px;
              box-sizing: border-box;
              border-radius:50%; color:#fff; font-size:11px; font-weight:bold;
              text-align:center; line-height:20px; cursor:default;
              border:2px solid #fff; box-shadow:0 0 4px rgba(0,0,0,0.5);
              transition: transform 0.1s ease-out; user-select:none; text-decoration:none; }
.ms-hotspot--linked { background: rgba(40,167,69,0.85); cursor:pointer; }  /* vert : lié à un produit */
.ms-hotspot--orphan { background: rgba(255,128,0,0.85); }                   /* orange : pas lié */
.ms-hotspot:hover { transform: scale(1.4); z-index:10; box-shadow:0 0 8px rgba(0,0,0,0.8); color:#fff; }
.ms-microfiche-meta { margin: 10px 0 20px; }
.ms-microfiche-meta dt { font-weight:bold; float:left; clear:left; width:160px; }
.ms-microfiche-meta dd { margin-left:170px; margin-bottom:4px; }
.ms-hotspots-table { width:100%; font-size:12px; }
.ms-hotspots-table th, .ms-hotspots-table td { padding:4px 6px; vertical-align:top; }
.ms-hotspots-table tbody tr:hover { background: #fffae6; }
.ms-hotspots-table .ms-seq-badge { display:inline-block; min-width:22px; padding:1px 4px; border-radius:11px;
                                   color:#fff; font-weight:bold; text-align:center; font-size:11px; }
.ms-hotspots-table .ms-seq-badge--linked { background:#28a745; }
.ms-hotspots-table .ms-seq-badge--orphan { background:#ff8000; }
</style>';

        // Construction de la table détaillée à droite de l'image.
        $listRows = '';
        $countLinked = 0;
        foreach ($hotspots as $h) {
            $idHotspot  = (int) $h['id_hotspot'];
            $seq        = (int) $h['sequence_number'];
            $articleRef = (string) $h['article_ref'];
            $label      = (string) ($h['article_label'] ?? '');
            $qty        = (int) $h['qty_recommended'];
            $idProduct  = (int) ($h['id_product'] ?? 0);

            if ($idProduct > 0) {
                $countLinked++;
                $productLink = $this->context->link->getAdminLink('AdminProducts', true)
                             . '&id_product=' . $idProduct . '&updateproduct';
                $refCell = sprintf(
                    '<a href="%s" target="_blank" title="Ouvrir la fiche produit Presta"><code>%s</code></a>',
                    htmlspecialchars($productLink, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($articleRef, ENT_QUOTES, 'UTF-8')
                );
                $badgeCls = 'ms-seq-badge ms-seq-badge--linked';
            } else {
                $refCell = '<code>' . htmlspecialchars($articleRef, ENT_QUOTES, 'UTF-8') . '</code>'
                    . ' <small class="text-muted" title="Aucun produit Presta avec cette référence (en attente cron rematching)">(orphelin)</small>';
                $badgeCls = 'ms-seq-badge ms-seq-badge--orphan';
            }

            $editLink = $this->context->link->getAdminLink('AdminMsHotspots', true)
                      . '&id_hotspot=' . $idHotspot . '&updatems_microfiche_hotspot';
            $editCell = sprintf(
                '<a href="%s" class="btn btn-default btn-xs" title="Modifier ce hotspot"><i class="icon-edit"></i></a>',
                htmlspecialchars($editLink, ENT_QUOTES, 'UTF-8')
            );

            $listRows .= sprintf(
                '<tr id="ms-hotspot-row-%d">'
                . '<td><span class="%s">%d</span></td>'
                . '<td>%s</td>'
                . '<td>%s</td>'
                . '<td style="text-align:right">×%d</td>'
                . '<td style="text-align:center">%s</td>'
                . '</tr>',
                $seq,
                $badgeCls, $seq,
                $refCell,
                htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
                $qty,
                $editCell
            );
        }

        $catLabel = (string) ($cat->nom_fr ?: $cat->code);

        return $css
            . '<div class="panel">'
            . '<h3>' . htmlspecialchars($moto->nom_fr, ENT_QUOTES, 'UTF-8')
            . ' <small>(' . htmlspecialchars((string) $moto->serial_constructeur, ENT_QUOTES, 'UTF-8') . ')</small></h3>'
            . '<h4>' . htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8')
            . ' #' . (int) $cat->numero_constructeur
            . ' <small>(partie : ' . htmlspecialchars((string) $cat->partie, ENT_QUOTES, 'UTF-8') . ')</small></h4>'
            . '<h5>' . htmlspecialchars((string) $micro->nom_constructeur, ENT_QUOTES, 'UTF-8')
            . ' &mdash; <strong>' . count($hotspots) . ' hotspots</strong>'
            . ' <small>(' . $countLinked . ' liés à un produit, ' . (count($hotspots) - $countLinked) . ' orphelins)</small>'
            . '</h5>'
            . '<dl class="ms-microfiche-meta">'
            . '<dt>Image</dt><dd>' . $imgWidth . ' × ' . $imgHeight . ' px '
            . '— <a href="' . $imageUrl . '" target="_blank">URL d\'origine</a></dd>'
            . '<dt>Légende</dt>'
            . '<dd><span class="ms-seq-badge ms-seq-badge--linked">●</span> lié à un produit Presta'
            . ' &nbsp;&nbsp; <span class="ms-seq-badge ms-seq-badge--orphan">●</span> pas encore lié</dd>'
            . '</dl>'
            . '<div class="ms-microfiche-layout">'
            . '<div class="ms-microfiche-viewer-wrap">'
            . '<div class="ms-microfiche-viewer">'
            . '<img src="' . $imageUrl . '" alt="" width="' . $imgWidth . '" height="' . $imgHeight . '" />'
            . $overlaysHtml
            . '</div>'
            . '</div>'
            . '<div class="ms-microfiche-list-wrap">'
            . '<table class="table ms-hotspots-table">'
            . '<thead><tr><th>#</th><th>Référence OEM</th><th>Libellé</th><th>Qté</th><th></th></tr></thead>'
            . '<tbody>' . $listRows . '</tbody>'
            . '</table>'
            . '</div>'
            . '</div>'
            . '<p class="help-block" style="margin-top:15px"><em>Survoler un cercle sur l\'image pour voir le détail. '
            . 'Cliquer une référence en vert dans le tableau ouvre la fiche produit PrestaShop dans un nouvel onglet. '
            . 'Les hotspots orphelins (orange) seront liés automatiquement quand le catalogue spareparts sera importé '
            . '(via le futur cron de rematching, PR8).</em></p>'
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
