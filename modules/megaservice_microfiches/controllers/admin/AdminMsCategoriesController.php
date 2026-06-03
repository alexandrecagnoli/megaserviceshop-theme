<?php
/**
 * Listing + édition du référentiel ps_ms_microfiche_categorie.
 *
 * Usage immédiat principal : renommer les catégories auto-créées par
 * l'import microfiches (code `TODO_<partie>_<num>`, `nom_fr` NULL) avec
 * leur libellé français définitif (brief §6.1.E).
 *
 * V1 : pas de création / suppression possible depuis le BO (les catégories
 * sont créées exclusivement par l'importer microfiches sur découverte d'un
 * nouveau (partie, numero_constructeur)). Édition seule.
 */
class AdminMsCategoriesController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap   = true;
        $this->table       = 'ms_microfiche_categorie';
        $this->className   = 'MsMicroficheCategorie';
        $this->identifier  = 'id_categorie';
        $this->lang        = false;
        $this->allow_export = false;

        parent::__construct();

        $this->fields_list = [
            'id_categorie' => [
                'title' => 'ID',
                'width' => 40,
                'align' => 'left',
            ],
            'partie' => [
                'title'      => 'Partie',
                'type'       => 'select',
                'list'       => ['cycle' => 'Cycle', 'moteur' => 'Moteur'],
                'filter_key' => 'a!partie',
                'width'      => 80,
            ],
            'numero_constructeur' => [
                'title' => 'N° constructeur',
                'align' => 'right',
                'width' => 100,
            ],
            'code' => [
                'title'    => 'Code',
                'callback' => 'highlightTodoCode',
            ],
            'nom_fr' => [
                'title'    => 'Nom FR',
                'callback' => 'highlightMissingName',
            ],
            'ordre_affichage' => [
                'title' => 'Ordre',
                'align' => 'right',
                'width' => 60,
            ],
            'active' => [
                'title'  => 'Actif',
                'active' => 'status',
                'type'   => 'bool',
                'align'  => 'center',
                'width'  => 60,
            ],
        ];

        $this->_defaultOrderBy  = 'partie';
        $this->_defaultOrderWay = 'ASC';

        $this->addRowAction('edit');
    }

    public function renderForm()
    {
        if (!$this->loadObject(true)) {
            return '';
        }
        $this->fields_form = [
            'legend' => [
                'title' => 'Catégorie microfiche',
                'icon'  => 'icon-folder-open',
            ],
            'input' => [
                [
                    'type'     => 'text',
                    'label'    => 'Partie',
                    'name'     => 'partie',
                    'readonly' => true,
                    'desc'     => 'cycle ou moteur — défini à l\'import, non modifiable ici.',
                ],
                [
                    'type'     => 'text',
                    'label'    => 'N° constructeur',
                    'name'     => 'numero_constructeur',
                    'readonly' => true,
                    'desc'     => 'Numéro de la catégorie chez KTM/HQV/GASGAS (commun aux 3 marques).',
                ],
                [
                    'type'     => 'text',
                    'label'    => 'Code',
                    'name'     => 'code',
                    'required' => true,
                    'desc'     => 'Identifiant interne. Les codes commençant par "TODO_" indiquent des catégories auto-créées à renommer.',
                ],
                [
                    'type'     => 'text',
                    'label'    => 'Nom FR',
                    'name'     => 'nom_fr',
                    'desc'     => 'Libellé affiché côté front. Ex : "Carter moteur", "Embrayage", "Cadre".',
                ],
                [
                    'type'  => 'text',
                    'label' => 'Ordre d\'affichage',
                    'name'  => 'ordre_affichage',
                    'desc'  => 'Plus petit = affiché en premier. Par défaut = numero_constructeur.',
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
                ],
            ],
            'submit' => ['title' => 'Enregistrer'],
        ];

        return parent::renderForm();
    }

    /**
     * Met en évidence un code TODO_ (catégorie auto-créée à renommer).
     * Callback fields_list.
     */
    public function highlightTodoCode($code, $row): string
    {
        if (is_string($code) && strncmp($code, 'TODO_', 5) === 0) {
            return '<span class="label label-warning" title="Auto-créée à l\'import, à renommer">'
                . '<i class="icon-warning-sign"></i> '
                . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</span>';
        }
        return htmlspecialchars((string) $code, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Affiche "(à compléter)" en italique si nom_fr est NULL ou vide.
     * Callback fields_list.
     */
    public function highlightMissingName($nom, $row): string
    {
        if ($nom === null || $nom === '') {
            return '<em class="text-muted">(à compléter)</em>';
        }
        return htmlspecialchars((string) $nom, ENT_QUOTES, 'UTF-8');
    }
}
