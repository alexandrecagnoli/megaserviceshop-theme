<?php
/**
 * Listing + édition partielle des motos (ps_ms_moto).
 *
 * Brief §6.1.A : Tableau avec filtres marque/année/type/search, colonnes
 * incluant nb microfiches associées. Pagination 50/page (défaut PS).
 *
 * V1 édition partielle (décision actée) : seuls `type` (pour corriger les
 * motos classées Autres) et `active` sont modifiables. Les autres champs
 * (modelnumber, marque, annee, nom_fr, core_name, cylindree…) restent
 * readonly — ils sont la source de vérité de l'import constructeur.
 *
 * Pas de création/suppression possible depuis le BO : les motos sont
 * créées exclusivement par MotosImporter sur import des CSV constructeur.
 */
class AdminMsMotosController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap    = true;
        $this->table        = 'ms_moto';
        $this->className    = 'MsMoto';
        $this->identifier   = 'id_moto';
        $this->lang         = false;
        $this->allow_export = true;

        parent::__construct();

        // Colonne calculée "Nb microfiches" : sous-requête évaluée par row.
        // Acceptable sur 1830 motos × 1 sous-requête simple (index id_moto).
        $this->_select = '(SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'ms_microfiche` '
                       . 'WHERE `id_moto` = a.`id_moto`) AS `nb_microfiches`';

        $this->fields_list = [
            'id_moto' => [
                'title' => 'ID',
                'width' => 50,
                'align' => 'left',
            ],
            'marque' => [
                'title'      => 'Marque',
                'type'       => 'select',
                'list'       => array_combine(MsMoto::MARQUES, MsMoto::MARQUES),
                'filter_key' => 'a!marque',
                'width'      => 80,
            ],
            'annee' => [
                'title' => 'Année',
                'align' => 'right',
                'width' => 70,
            ],
            'modelnumber' => [
                'title' => 'MODELNUMBER',
                'width' => 180,
            ],
            'nom_fr' => [
                'title' => 'Nom',
            ],
            'type' => [
                'title'      => 'Type',
                'type'       => 'select',
                'list'       => array_combine(MsMoto::TYPES, MsMoto::TYPES),
                'filter_key' => 'a!type',
                'callback'   => 'highlightAutresType',
                'width'      => 100,
            ],
            'cylindree' => [
                'title' => 'Cyl.',
                'align' => 'right',
                'width' => 60,
            ],
            'is_electric' => [
                'title' => 'E',
                'type'  => 'bool',
                'align' => 'center',
                'width' => 30,
            ],
            'nb_microfiches' => [
                'title'        => 'Microfiches',
                'align'        => 'right',
                'search'       => false,
                'orderby'      => false,
                'width'        => 90,
            ],
            'active' => [
                'title'  => 'Actif',
                'active' => 'status',
                'type'   => 'bool',
                'align'  => 'center',
                'width'  => 60,
            ],
        ];

        $this->_defaultOrderBy  = 'marque';
        $this->_defaultOrderWay = 'ASC';

        $this->addRowAction('edit');
    }

    public function renderForm()
    {
        if (!$this->loadObject(true)) {
            return '';
        }

        $typeOptions = [];
        foreach (MsMoto::TYPES as $t) {
            $typeOptions[] = ['id' => $t, 'name' => $t];
        }

        $this->fields_form = [
            'legend' => [
                'title' => 'Moto',
                'icon'  => 'icon-motorcycle',
            ],
            'input' => [
                // -- Source de vérité (readonly) ---------------------------
                [
                    'type'     => 'text',
                    'label'    => 'MODELNUMBER',
                    'name'     => 'modelnumber',
                    'readonly' => true,
                    'desc'     => 'Identifiant constructeur unique. Défini à l\'import, non modifiable.',
                ],
                [
                    'type'     => 'text',
                    'label'    => 'Serial constructeur',
                    'name'     => 'serial_constructeur',
                    'readonly' => true,
                    'desc'     => 'Sert de pivot pour l\'import microfiches (= nom de fichier sans extension).',
                ],
                [
                    'type'     => 'text',
                    'label'    => 'Marque',
                    'name'     => 'marque',
                    'readonly' => true,
                ],
                [
                    'type'     => 'text',
                    'label'    => 'Année',
                    'name'     => 'annee',
                    'readonly' => true,
                ],
                [
                    'type'     => 'text',
                    'label'    => 'Nom FR',
                    'name'     => 'nom_fr',
                    'readonly' => true,
                    'desc'     => 'Nom officiel constructeur. Édition complète prévue en V2.',
                ],
                [
                    'type'     => 'text',
                    'label'    => 'Core name',
                    'name'     => 'core_name',
                    'readonly' => true,
                    'desc'     => 'Catégorie constructeur sans l\'année (utilisé pour la taxonomie type).',
                ],
                [
                    'type'     => 'text',
                    'label'    => 'Cylindrée',
                    'name'     => 'cylindree',
                    'readonly' => true,
                ],
                // -- Modifiable ---------------------------------------------
                [
                    'type'     => 'select',
                    'label'    => 'Type',
                    'name'     => 'type',
                    'required' => true,
                    'options'  => [
                        'query' => $typeOptions,
                        'id'    => 'id',
                        'name'  => 'name',
                    ],
                    'desc'     => 'Catégorie commerciale. À corriger si la taxonomie auto a mis "Autres" à tort.',
                ],
                [
                    'type'    => 'switch',
                    'label'   => 'Électrique',
                    'name'    => 'is_electric',
                    'is_bool' => true,
                    'values'  => [
                        ['id' => 'electric_on',  'value' => 1, 'label' => 'Oui'],
                        ['id' => 'electric_off', 'value' => 0, 'label' => 'Non'],
                    ],
                    'desc'    => 'Automatiquement true si type = Électrique.',
                ],
                [
                    'type'    => 'switch',
                    'label'   => 'Actif',
                    'name'    => 'active',
                    'is_bool' => true,
                    'values'  => [
                        ['id' => 'active_on',  'value' => 1, 'label' => 'Oui'],
                        ['id' => 'active_off', 'value' => 0, 'label' => 'Non'],
                    ],
                    'desc'    => 'Une moto désactivée n\'apparaît pas côté front. Un réimport CSV ne réactive pas (cf. MotosImporter).',
                ],
            ],
            'submit' => ['title' => 'Enregistrer'],
        ];

        return parent::renderForm();
    }

    /**
     * Met en évidence le type "Autres" (à corriger manuellement).
     * Callback fields_list.
     */
    public function highlightAutresType($type, $row): string
    {
        if ($type === 'Autres') {
            return '<span class="label label-warning" title="Taxonomie auto en échec — à corriger">'
                . '<i class="icon-warning-sign"></i> Autres</span>';
        }
        return htmlspecialchars((string) $type, ENT_QUOTES, 'UTF-8');
    }
}
