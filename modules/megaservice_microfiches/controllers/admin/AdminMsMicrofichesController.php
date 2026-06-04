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
