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
            'serial_constructeur' => [
                'title' => 'Serial constructeur',
                'width' => 120,
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
            'picture_cycle' => [
                'title'    => 'Cycle',
                'callback' => 'renderPictureCell',
                'search'   => false,
                'orderby'  => false,
                'align'    => 'center',
                'width'    => 60,
            ],
            'picture_moteur' => [
                'title'    => 'Moteur',
                'callback' => 'renderPictureCell',
                'search'   => false,
                'orderby'  => false,
                'align'    => 'center',
                'width'    => 60,
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

    /** Extensions image acceptées pour upload picture_cycle / picture_moteur. */
    private const ALLOWED_IMAGE_EXT = ['jpg', 'jpeg', 'png', 'webp'];

    public function renderForm()
    {
        /** @var MsMoto|null $obj */
        $obj = $this->loadObject(true);
        if (!$obj) {
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
                // -- Uploads images partielles -----------------------------
                [
                    'type'  => 'file',
                    'label' => 'Photo partie cycle',
                    'name'  => 'picture_cycle_upload',
                    'desc'  => $this->renderCurrentImageHint($obj->picture_cycle, 'cycle'),
                ],
                [
                    'type'  => 'file',
                    'label' => 'Photo partie moteur',
                    'name'  => 'picture_moteur_upload',
                    'desc'  => $this->renderCurrentImageHint($obj->picture_moteur, 'moteur'),
                ],
            ],
            'submit' => ['title' => 'Enregistrer'],
        ];

        return parent::renderForm();
    }

    /**
     * HTML pour le champ desc d'un input file : preview de l'image actuelle
     * (si présente) + rappel des formats acceptés.
     */
    private function renderCurrentImageHint(?string $relativePath, string $type): string
    {
        $formats = 'JPG, PNG ou WebP.';
        if (!$relativePath) {
            return 'Aucune image actuelle. Téléverser un fichier (' . $formats . ')';
        }
        $url = __PS_BASE_URI__ . 'img/ms_moto/' . htmlspecialchars($relativePath, ENT_QUOTES, 'UTF-8');
        return sprintf(
            'Image actuelle : <a href="%s" target="_blank">%s</a><br>'
            . '<img src="%s" alt="%s actuelle" style="max-height:120px;border:1px solid #ddd;padding:2px;margin:4px 0;"><br>'
            . 'Téléverser un fichier pour remplacer (%s)',
            $url, htmlspecialchars($relativePath, ENT_QUOTES, 'UTF-8'),
            $url, htmlspecialchars($type, ENT_QUOTES, 'UTF-8'),
            $formats
        );
    }

    /**
     * Intercepte la save pour gérer les uploads picture_cycle / picture_moteur.
     * On laisse le save standard de l'ObjectModel se faire (parent::postProcess),
     * puis on traite les éventuels fichiers uploadés.
     */
    public function postProcess()
    {
        $result = parent::postProcess();

        $isSave = Tools::isSubmit('submitAdd' . $this->table)
               || Tools::isSubmit('submitAdd' . $this->table . 'AndStay');
        if ($isSave) {
            $idMoto = (int) Tools::getValue('id_moto');
            if ($idMoto > 0) {
                $this->handleImageUpload($idMoto, 'cycle');
                $this->handleImageUpload($idMoto, 'moteur');
            }
        }
        return $result;
    }

    /**
     * Gère l'upload d'une image partielle (cycle ou moteur) sur une moto.
     * Stockage : <PS root>/img/ms_moto/<id_moto>/{cycle,moteur}.<ext>
     * Path BDD : <id_moto>/cycle.<ext> (relatif à img/ms_moto/)
     */
    private function handleImageUpload(int $idMoto, string $type): void
    {
        $field = 'picture_' . $type . '_upload';
        if (empty($_FILES[$field]['name']) || empty($_FILES[$field]['tmp_name'])) {
            return; // pas d'upload pour ce champ
        }
        $err = (int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            $this->errors[] = sprintf('Upload %s : erreur PHP %d (vérifier upload_max_filesize / post_max_size)', $type, $err);
            return;
        }

        $originalName = (string) $_FILES[$field]['name'];
        $ext = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        if (!in_array($ext, self::ALLOWED_IMAGE_EXT, true)) {
            $this->errors[] = sprintf(
                'Upload %s : extension "%s" non autorisée (attendu : %s)',
                $type, $ext, implode(', ', self::ALLOWED_IMAGE_EXT)
            );
            return;
        }

        $dir = _PS_IMG_DIR_ . 'ms_moto/' . $idMoto . '/';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            $this->errors[] = sprintf('Upload %s : impossible de créer le dossier %s', $type, $dir);
            return;
        }

        // Supprimer une ancienne image (toutes extensions possibles) avant d'écrire la nouvelle.
        foreach (self::ALLOWED_IMAGE_EXT as $oldExt) {
            $oldPath = $dir . $type . '.' . $oldExt;
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        $destFilename = $type . '.' . $ext;
        $destPath     = $dir . $destFilename;
        if (!move_uploaded_file((string) $_FILES[$field]['tmp_name'], $destPath)) {
            $this->errors[] = sprintf('Upload %s : impossible de déplacer le fichier vers %s', $type, $destPath);
            return;
        }
        @chmod($destPath, 0644);

        $bddPath = $idMoto . '/' . $destFilename;
        $ok = Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'ms_moto` '
            . "SET `picture_" . $type . "` = '" . pSQL($bddPath) . "', `date_upd` = NOW() "
            . 'WHERE `id_moto` = ' . $idMoto
        );
        if (!$ok) {
            $this->errors[] = sprintf('Upload %s : fichier déplacé mais update BDD échoué.', $type);
            return;
        }

        $this->confirmations[] = sprintf('Image %s mise à jour (%s).', $type, $destFilename);
    }

    /**
     * Mini-vignette si l'image est uploadée, tiret gris sinon.
     * Callback fields_list pour picture_cycle / picture_moteur.
     */
    public function renderPictureCell($value, $row): string
    {
        if ($value === null || $value === '') {
            return '<span class="text-muted" title="Aucune image uploadée">&mdash;</span>';
        }
        $url = __PS_BASE_URI__ . 'img/ms_moto/' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        return sprintf(
            '<a href="%s" target="_blank" title="Voir l\'image en grand">'
            . '<img src="%s" alt="" style="max-height:32px;max-width:60px;border:1px solid #ddd;padding:1px;background:#fff;" />'
            . '</a>',
            $url, $url
        );
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
