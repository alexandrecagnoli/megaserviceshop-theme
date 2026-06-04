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
    /**
     * Traite les soumissions du form de l editeur visuel (save batch + revert).
     * Appelee par PrestaShop avant le rendu. Le redirect a la fin garde
     * l admin sur la meme page (pattern POST-Redirect-GET classique pour
     * eviter le "voulez-vous reposter le formulaire ?" au reload).
     */
    public function postProcess()
    {
        // --- Save batch des modifications de positions ---------------------
        if (Tools::isSubmit('ms_save_hotspots')) {
            $idMicrofiche = (int) Tools::getValue('id_microfiche');
            $rawJson      = Tools::getValue('ms_save_hotspots');
            $changes      = json_decode((string) $rawJson, true);

            $applied = 0;
            $errors  = 0;
            if (is_array($changes)) {
                foreach ($changes as $idHotspot => $pos) {
                    $idHotspot = (int) $idHotspot;
                    if ($idHotspot <= 0 || !is_array($pos)) {
                        $errors++; continue;
                    }
                    $posX = isset($pos['x']) ? (int) $pos['x'] : -1;
                    $posY = isset($pos['y']) ? (int) $pos['y'] : -1;
                    if ($posX < 0 || $posY < 0) {
                        $errors++; continue;
                    }
                    $ok = (bool) Db::getInstance()->execute(
                        'UPDATE `' . _DB_PREFIX_ . 'ms_microfiche_hotspot` '
                        . 'SET `position_x` = ' . $posX . ', '
                        . '    `position_y` = ' . $posY . ', '
                        . '    `manually_edited` = 1 '
                        . 'WHERE `id_hotspot` = ' . $idHotspot
                    );
                    if ($ok && Db::getInstance()->Affected_Rows() > 0) {
                        $applied++;
                    } else {
                        $errors++;
                    }
                }
            }
            $this->confirmations[] = $applied . ' hotspot(s) mis a jour.'
                . ($errors > 0 ? ' (' . $errors . ' erreur(s) ignoree(s))' : '');

            if ($idMicrofiche > 0) {
                Tools::redirectAdmin(
                    $this->context->link->getAdminLink('AdminMsMicrofiches', true)
                    . '&id_microfiche=' . $idMicrofiche . '&viewms_microfiche'
                );
            }
        }

        // --- Revert d un hotspot vers sa position constructeur -------------
        if (Tools::isSubmit('ms_revert_hotspot')) {
            $idHotspot    = (int) Tools::getValue('ms_revert_hotspot');
            $idMicrofiche = (int) Tools::getValue('id_microfiche');
            if ($idHotspot > 0) {
                $row = Db::getInstance()->getRow(
                    'SELECT `position_x_original`, `position_y_original` '
                    . 'FROM `' . _DB_PREFIX_ . 'ms_microfiche_hotspot` '
                    . 'WHERE `id_hotspot` = ' . $idHotspot
                );
                if ($row && $row['position_x_original'] !== null && $row['position_y_original'] !== null) {
                    Db::getInstance()->execute(
                        'UPDATE `' . _DB_PREFIX_ . 'ms_microfiche_hotspot` '
                        . 'SET `position_x` = ' . (int) $row['position_x_original'] . ', '
                        . '    `position_y` = ' . (int) $row['position_y_original'] . ', '
                        . '    `manually_edited` = 0 '
                        . 'WHERE `id_hotspot` = ' . $idHotspot
                    );
                    $this->confirmations[] = 'Hotspot #' . $idHotspot . ' revert OK.';
                } else {
                    $this->errors[] = 'Hotspot #' . $idHotspot . ' : aucune position constructeur en reference.';
                }
            }
            if ($idMicrofiche > 0) {
                Tools::redirectAdmin(
                    $this->context->link->getAdminLink('AdminMsMicrofiches', true)
                    . '&id_microfiche=' . $idMicrofiche . '&viewms_microfiche'
                );
            }
        }

        return parent::postProcess();
    }

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
            'SELECT `id_hotspot`, `id_microfiche`, `id_product`, `article_ref`, `article_label`, '
            . '`sequence_number`, `position_x`, `position_y`, '
            . '`position_x_original`, `position_y_original`, '
            . '`qty_recommended`, `manually_edited` '
            . 'FROM `' . _DB_PREFIX_ . 'ms_microfiche_hotspot` '
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
        $overlaysHtml   = '';
        $countEdited    = 0;
        foreach ($hotspots as $h) {
            $leftPct       = ((int) $h['position_x']) / $imgWidth  * 100;
            $bottomPct     = ((int) $h['position_y']) / $imgHeight * 100;
            $manuallyEdited = (bool) ($h['manually_edited'] ?? false);
            if ($manuallyEdited) {
                $countEdited++;
            }
            $title = sprintf(
                '#%d — %s%s (qté: %d)%s',
                (int) $h['sequence_number'],
                (string) $h['article_ref'],
                $h['article_label'] ? ' — ' . $h['article_label'] : '',
                (int) $h['qty_recommended'],
                $manuallyEdited ? ' [modifié manuellement]' : ''
            );
            if ($manuallyEdited) {
                $cls = 'ms-hotspot ms-hotspot--edited';
            } elseif (((int) ($h['id_product'] ?? 0)) > 0) {
                $cls = 'ms-hotspot ms-hotspot--linked';
            } else {
                $cls = 'ms-hotspot ms-hotspot--orphan';
            }
            $overlaysHtml .= sprintf(
                '<div class="%s" style="left:%.3f%%;bottom:%.3f%%" title="%s" '
                . 'data-id="%d" data-seq="%d" data-ref="%s">%d</div>',
                $cls,
                $leftPct, $bottomPct,
                htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
                (int) $h['id_hotspot'],
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
.ms-hotspot--edited { background: rgba(30,144,255,0.90); }                  /* bleu : modifié manuellement */
.ms-hotspot:hover { transform: scale(1.4); z-index:10; box-shadow:0 0 8px rgba(0,0,0,0.8); color:#fff; }
/* Mode "édition activée" : curseur main + bordure plus visible pour signaler le drag possible */
.ms-microfiche-viewer.ms-editing .ms-hotspot { cursor: move; border-color: #1e90ff; }
.ms-microfiche-viewer.ms-editing .ms-hotspot.ms-dragging { z-index:20; transform: scale(1.4); opacity:0.85; box-shadow:0 0 10px rgba(30,144,255,1); }
.ms-edit-bar { margin:10px 0; padding:8px; background:#f5f5f5; border:1px solid #ddd; border-radius:3px; display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
.ms-edit-bar .ms-edit-status { font-size:13px; color:#666; }
.ms-edit-bar .ms-edit-status .ms-counter-edited { color:#1e90ff; font-weight:bold; }
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
            $manuallyEdited = (bool) ($h['manually_edited'] ?? false);
            $editCell = sprintf(
                '<a href="%s" class="btn btn-default btn-xs" title="Modifier ce hotspot"><i class="icon-edit"></i></a>',
                htmlspecialchars($editLink, ENT_QUOTES, 'UTF-8')
            );
            if ($manuallyEdited) {
                $editCell .= sprintf(
                    ' <button type="submit" form="ms-revert-form-%d" '
                    . 'class="btn btn-default btn-xs" name="ms_revert_hotspot" value="%d" '
                    . 'title="Revert vers la position constructeur (CSV)" '
                    . 'onclick="return confirm(\'Revert ce hotspot vers la position constructeur ?\');">'
                    . '<i class="icon-undo"></i></button>',
                    $idHotspot, $idHotspot
                );
                // Form séparé par bouton pour permettre un submit ciblé.
                $editCell .= sprintf(
                    '<form id="ms-revert-form-%d" method="post" action="" style="display:inline">'
                    . '<input type="hidden" name="id_microfiche" value="%d" />'
                    . '</form>',
                    $idHotspot,
                    (int) $micro->id_microfiche
                );
            }

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
            . ' &nbsp;&nbsp; <span class="ms-seq-badge ms-seq-badge--orphan">●</span> pas encore lié'
            . ' &nbsp;&nbsp; <span class="ms-seq-badge" style="background:#1e90ff">●</span> modifié manuellement</dd>'
            . '</dl>'
            . '<form id="ms-save-form" method="post" action="" class="ms-edit-bar">'
            . '<input type="hidden" name="id_microfiche" value="' . (int) $micro->id_microfiche . '" />'
            . '<input type="hidden" name="ms_save_hotspots" id="ms-pending-changes" value="" />'
            . '<label><input type="checkbox" id="ms-toggle-edit" /> <strong>Activer le drag &amp; drop</strong></label>'
            . ' <span class="ms-edit-status">'
            . '<span class="ms-counter-edited">' . $countEdited . '</span> modifié(s) en BDD / '
            . count($hotspots) . ' au total'
            . '</span>'
            . ' <button type="submit" id="ms-save-btn" class="btn btn-primary btn-sm" disabled>'
            . '<i class="icon-save"></i> Enregistrer (<span id="ms-pending-count">0</span>)'
            . '</button>'
            . '<span id="ms-edit-feedback" style="margin-left:auto"></span>'
            . '</form>'
            . '<div class="ms-microfiche-layout">'
            . '<div class="ms-microfiche-viewer-wrap">'
            . '<div class="ms-microfiche-viewer" id="ms-microfiche-viewer" data-img-w="' . $imgWidth . '" data-img-h="' . $imgHeight . '">'
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
            . '(via le futur cron de rematching, PR8). '
            . 'Cocher <strong>Activer le drag &amp; drop</strong> pour déplacer un cercle (modification protégée contre l\'écrasement au prochain réimport CSV — récupérable via le bouton ↶ Revert).</em></p>'
            . '</div>'
            . $this->renderHotspotsEditorJs();
    }

    /**
     * JS inline pour le drag/drop des hotspots + revert AJAX.
     * Autonome (pas de dépendance jQuery custom, on utilise jQuery déjà
     * inclus par PrestaShop pour le token).
     */
    private function renderHotspotsEditorJs(): string
    {
        // Plus d'AJAX : on accumule les changements cote JS dans un objet
        // pendingChanges, le bouton "Enregistrer (N)" submit un form POST
        // classique avec tout le batch en JSON. postProcess() cote PHP
        // applique en BDD puis redirect vers la meme page.
        // Pareil pour le revert : submit POST classique avec un input
        // hidden ms_revert_hotspot = idHotspot.
        return <<<'JS'
<script>
(function() {
    var viewer = document.getElementById('ms-microfiche-viewer');
    if (!viewer) return;

    var toggle      = document.getElementById('ms-toggle-edit');
    var feedback    = document.getElementById('ms-edit-feedback');
    var saveBtn     = document.getElementById('ms-save-btn');
    var pendingInpt = document.getElementById('ms-pending-changes');
    var pendingCnt  = document.getElementById('ms-pending-count');
    var saveForm    = document.getElementById('ms-save-form');
    var imgW        = parseInt(viewer.dataset.imgW, 10) || 1;
    var imgH        = parseInt(viewer.dataset.imgH, 10) || 1;

    /** @type {Object<number, {x: number, y: number}>} */
    var pendingChanges = {};

    function setFeedback(msg, isError) {
        if (!feedback) return;
        feedback.textContent = msg || '';
        feedback.style.color = isError ? '#c00' : '#28a745';
    }

    function updatePending() {
        var count = Object.keys(pendingChanges).length;
        if (pendingCnt) pendingCnt.textContent = count;
        if (saveBtn)   saveBtn.disabled = count === 0;
        if (pendingInpt) pendingInpt.value = JSON.stringify(pendingChanges);
    }

    // -- Toggle drag mode -------------------------------------------------
    if (toggle) {
        toggle.addEventListener('change', function() {
            if (this.checked) {
                viewer.classList.add('ms-editing');
                setFeedback('Drag activé. Déplacer les cercles, puis cliquer Enregistrer.', false);
            } else {
                viewer.classList.remove('ms-editing');
                setFeedback('', false);
            }
        });
    }

    // -- Drag & drop (uniquement update visuel + accumulation) -----------
    var dragging = null, startX = 0, startY = 0, startLeft = 0, startBottom = 0;

    function onPointerDown(e) {
        if (!viewer.classList.contains('ms-editing')) return;
        if (!e.target.classList || !e.target.classList.contains('ms-hotspot')) return;
        dragging = e.target;
        dragging.classList.add('ms-dragging');
        var rect = viewer.getBoundingClientRect();
        startX = e.clientX - rect.left;
        startY = e.clientY - rect.top;
        var st = window.getComputedStyle(dragging);
        startLeft   = parseFloat(st.left)   || 0;
        startBottom = parseFloat(st.bottom) || 0;
        e.preventDefault();
    }

    function onPointerMove(e) {
        if (!dragging) return;
        var rect = viewer.getBoundingClientRect();
        var dx = (e.clientX - rect.left) - startX;
        var dy = (e.clientY - rect.top)  - startY;
        dragging.style.left   = (startLeft + dx)   + 'px';
        dragging.style.bottom = (startBottom - dy) + 'px';
    }

    function onPointerUp(e) {
        if (!dragging) return;
        var hotspot = dragging;
        hotspot.classList.remove('ms-dragging');
        dragging = null;

        // Conversion px viewer -> px image originale (convention KTM :
        // position_y = depuis le bas, donc bottomInViewer * ratioY).
        var rect = viewer.getBoundingClientRect();
        var hRect = hotspot.getBoundingClientRect();
        var leftInViewer = hRect.left - rect.left;
        var topInViewer  = hRect.top  - rect.top;
        var imgEl = viewer.querySelector('img');
        var imgRect = imgEl ? imgEl.getBoundingClientRect() : null;
        var displayedW = imgRect ? imgRect.width  : 0;
        var displayedH = imgRect ? imgRect.height : 0;

        if (!displayedW || !displayedH) {
            setFeedback('Image non chargée - réessayer.', true);
            hotspot.style.left   = startLeft   + 'px';
            hotspot.style.bottom = startBottom + 'px';
            return;
        }
        var ratioX = imgW / displayedW;
        var ratioY = imgH / displayedH;
        var posX = Math.round(leftInViewer * ratioX);
        var bottomInViewer = displayedH - (topInViewer + hRect.height);
        var posY = Math.round(bottomInViewer * ratioY);

        if (!Number.isFinite(posX) || !Number.isFinite(posY)) {
            setFeedback('Positions calculées invalides.', true);
            hotspot.style.left   = startLeft   + 'px';
            hotspot.style.bottom = startBottom + 'px';
            return;
        }
        if (posX < 0) posX = 0; if (posX > imgW) posX = imgW;
        if (posY < 0) posY = 0; if (posY > imgH) posY = imgH;

        // Re-positionnement en % (responsive)
        hotspot.style.left   = ((posX / imgW)  * 100).toFixed(3) + '%';
        hotspot.style.bottom = ((posY / imgH) * 100).toFixed(3) + '%';
        // Visuel "en attente d'enregistrement" : passe en bleu
        hotspot.classList.remove('ms-hotspot--linked', 'ms-hotspot--orphan');
        hotspot.classList.add('ms-hotspot--edited');

        // Accumulation du changement
        var idHotspot = parseInt(hotspot.dataset.id, 10);
        pendingChanges[idHotspot] = { x: posX, y: posY };
        updatePending();
        setFeedback(Object.keys(pendingChanges).length + ' modification(s) en attente. Cliquer Enregistrer.', false);
    }

    viewer.addEventListener('mousedown', onPointerDown);
    document.addEventListener('mousemove', onPointerMove);
    document.addEventListener('mouseup',   onPointerUp);

    // -- Garde-fou : avertir l'admin s'il quitte la page avec des changements
    //                en attente non sauvegardés.
    window.addEventListener('beforeunload', function(e) {
        if (Object.keys(pendingChanges).length === 0) return;
        // Sauf si c'est notre form save qui submit (geré via un flag)
        if (saveForm && saveForm.dataset.submitting === '1') return;
        e.preventDefault();
        e.returnValue = '';
    });
    if (saveForm) {
        saveForm.addEventListener('submit', function() {
            this.dataset.submitting = '1';
            updatePending(); // s'assurer que le hidden input est bien rempli avant submit
        });
    }

    updatePending();
})();
</script>
JS;
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
