<?php
/**
 * PR6 — PLP moto : page listant les microfiches (vues éclatées) d'une moto.
 *
 * URL V1 : /module/megaservice_microfiches/moto?id_moto=2587
 *   (URL propre /motos/{marque}/{annee}/{slug} ajoutée ensuite via hookModuleRoutes)
 *
 * CONTRAT avec everyparts : la moto courante arrive en GET `id_moto`. Le garage /
 * sélecteur de modèle (header) est construit par everyparts et n'aura qu'à linker
 * vers cette URL. Ce controller ne dépend pas du sélecteur ni d'un cookie.
 *
 * Conforme à la maquette PLP_CAT_SPAREPARTS : bannière modèle, sidebar filtre
 * catégorie, grille de cartes microfiches (vignette + nom + marque + nb pièces).
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

// Filet de sécurité : 1er front controller du module, on garantit le chargement
// des ObjectModels indépendamment de l'ordre de dispatch (require_once idempotent,
// déjà fait dans megaservice_microfiches.php au boot du module).
require_once _PS_MODULE_DIR_ . 'megaservice_microfiches/classes/MsMoto.php';
require_once _PS_MODULE_DIR_ . 'megaservice_microfiches/classes/MsMicroficheCategorie.php';

class Megaservice_microfichesMotoModuleFrontController extends ModuleFrontController
{
    /** @var MsMoto|null Moto courante hydratée */
    protected $moto;

    public function init()
    {
        $idMoto = (int) Tools::getValue('id_moto');

        if ($idMoto > 0) {
            $moto = new MsMoto($idMoto);
            if (Validate::isLoadedObject($moto) && (bool) $moto->active) {
                $this->moto = $moto;
            }
        }

        if ($this->moto === null) {
            // Pas de moto valide → 404 propre (plutôt qu'une page vide).
            Tools::redirect('index.php?controller=404');
        }

        parent::init();
    }

    public function initContent()
    {
        parent::initContent();

        $partie = (string) Tools::getValue('partie'); // '', 'cycle' ou 'moteur'
        if (!in_array($partie, MsMicroficheCategorie::PARTIES, true)) {
            $partie = '';
        }

        // Pas de partie → page HUB moto (3 accès + blocs). Sinon → PLP scopée.
        if ($partie === '') {
            $this->initHub();
            return;
        }

        $microfiches = $this->fetchMicrofiches((int) $this->moto->id, $partie);
        $categories  = $this->buildCategoryFacets($microfiches);

        $this->context->smarty->assign([
            'ms_moto'        => $this->motoToTemplate($this->moto),
            'ms_microfiches' => $microfiches,
            'ms_categories'  => $categories,
            'ms_partie'      => $partie,
            'ms_total'       => count($microfiches),
        ]);

        $this->setTemplate('module:megaservice_microfiches/views/templates/front/moto.tpl');
    }

    /**
     * Page "hub moto" (URL /motos/{id}-{slug} sans partie) — maquette composite :
     * hero + bannière modèle, 3 accès (Cycle / Moteur / Powerparts), bloc
     * "Dernières microfiches". Le bloc "Accessoires Powerparts" + la cible de la
     * carte Powerparts = Phase 2 (intégration ukooparts, produits filtrés moto).
     */
    protected function initHub(): void
    {
        $idMoto = (int) $this->moto->id;
        $slug   = Tools::str2url($this->moto->nom_fr);

        $partieLink = function ($p) use ($idMoto, $slug) {
            return $this->context->link->getModuleLink(
                'megaservice_microfiches', 'moto',
                ['id_moto' => $idMoto, 'slug' => $slug, 'partie' => $p]
            );
        };

        $this->context->smarty->assign([
            'ms_moto'        => $this->motoToTemplate($this->moto),
            'ms_cycle_url'   => $partieLink('cycle'),
            'ms_moteur_url'  => $partieLink('moteur'),
            // Phase 2 : cible Powerparts (ukooparts, filtré moto). Placeholder pour l'instant.
            'ms_powerparts_url' => '#',
            'ms_latest'      => $this->fetchLatestMicrofiches($idMoto, 4),
            'ms_latest_more' => $partieLink('cycle'),
        ]);

        $this->setTemplate('module:megaservice_microfiches/views/templates/front/moto-hub.tpl');
    }

    /**
     * N dernières microfiches de la moto (toutes parties confondues) pour le hub.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function fetchLatestMicrofiches(int $idMoto, int $limit): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT mf.`id_microfiche`, mf.`nom_constructeur`, mf.`nom_fr`,
                    mf.`image_thumb_url`, mf.`image_full_url`,
                    COUNT(h.`id_hotspot`) AS nb_pieces
             FROM `' . _DB_PREFIX_ . 'ms_microfiche` mf
             LEFT JOIN `' . _DB_PREFIX_ . 'ms_microfiche_hotspot` h
                    ON h.`id_microfiche` = mf.`id_microfiche`
             WHERE mf.`id_moto` = ' . $idMoto . ' AND mf.`active` = 1
             GROUP BY mf.`id_microfiche`
             ORDER BY mf.`date_add` DESC, mf.`id_microfiche` DESC
             LIMIT ' . (int) $limit
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
                ['id_microfiche' => $r['id_microfiche'], 'slug' => Tools::str2url($r['display_name'])]
            );
        }
        unset($r);

        return $rows;
    }

    /**
     * Microfiches actives de la moto + catégorie + nombre de hotspots (pièces).
     * Un seul JOIN agrégé : pas de N+1.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function fetchMicrofiches(int $idMoto, string $partie): array
    {
        $sql = 'SELECT mf.`id_microfiche`, mf.`nom_constructeur`, mf.`nom_fr`,
                       mf.`image_thumb_url`, mf.`image_full_url`,
                       cat.`id_categorie`, cat.`partie`, cat.`nom_fr` AS cat_nom_fr,
                       cat.`code` AS cat_code, cat.`ordre_affichage`,
                       COUNT(h.`id_hotspot`) AS nb_pieces
                FROM `' . _DB_PREFIX_ . 'ms_microfiche` mf
                INNER JOIN `' . _DB_PREFIX_ . 'ms_microfiche_categorie` cat
                       ON cat.`id_categorie` = mf.`id_categorie`
                LEFT JOIN `' . _DB_PREFIX_ . 'ms_microfiche_hotspot` h
                       ON h.`id_microfiche` = mf.`id_microfiche`
                WHERE mf.`id_moto` = ' . $idMoto . '
                  AND mf.`active` = 1';

        if ($partie !== '') {
            $sql .= ' AND cat.`partie` = "' . pSQL($partie) . '"';
        }

        $sql .= ' GROUP BY mf.`id_microfiche`
                  ORDER BY cat.`partie` ASC, cat.`ordre_affichage` ASC, mf.`nom_constructeur` ASC';

        $rows = Db::getInstance()->executeS($sql) ?: [];

        foreach ($rows as &$row) {
            $row['id_microfiche'] = (int) $row['id_microfiche'];
            $row['id_categorie']  = (int) $row['id_categorie'];
            $row['nb_pieces']     = (int) $row['nb_pieces'];
            // Libellé affiché : nom_fr si saisi en BO, sinon le nom constructeur.
            $row['display_name']  = $row['nom_fr'] !== null && $row['nom_fr'] !== ''
                ? $row['nom_fr']
                : $row['nom_constructeur'];
            $row['cat_label']     = $row['cat_nom_fr'] !== null && $row['cat_nom_fr'] !== ''
                ? $row['cat_nom_fr']
                : $row['cat_code'];
            // Vignette : thumb si dispo, sinon l'image pleine (hotlink constructeur).
            $row['thumb']         = $row['image_thumb_url'] !== null && $row['image_thumb_url'] !== ''
                ? $row['image_thumb_url']
                : $row['image_full_url'];
            // Lien vers la PDP microfiche (PR7) — URL propre /microfiches/{id}-{slug}.
            $row['url']           = $this->context->link->getModuleLink(
                'megaservice_microfiches',
                'microfiche',
                ['id_microfiche' => $row['id_microfiche'], 'slug' => Tools::str2url($row['display_name'])]
            );
        }
        unset($row);

        return $rows;
    }

    /**
     * Facettes catégorie pour le filtre sidebar : catégories réellement présentes
     * parmi les microfiches de cette moto, avec leur compte, groupées par partie.
     *
     * @param array<int,array<string,mixed>> $microfiches
     * @return array<int,array<string,mixed>>
     */
    protected function buildCategoryFacets(array $microfiches): array
    {
        $facets = [];
        foreach ($microfiches as $mf) {
            $id = $mf['id_categorie'];
            if (!isset($facets[$id])) {
                $facets[$id] = [
                    'id_categorie'    => $id,
                    'partie'          => $mf['partie'],
                    'label'           => $mf['cat_label'],
                    'ordre_affichage' => (int) $mf['ordre_affichage'],
                    'count'           => 0,
                ];
            }
            $facets[$id]['count']++;
        }

        usort($facets, static function ($a, $b) {
            return [$a['partie'], $a['ordre_affichage'], $a['label']]
               <=> [$b['partie'], $b['ordre_affichage'], $b['label']];
        });

        return $facets;
    }

    /**
     * Projection moto pour le template (champs nécessaires à la bannière).
     *
     * @return array<string,mixed>
     */
    protected function motoToTemplate(MsMoto $moto): array
    {
        return [
            'id_moto'     => (int) $moto->id,
            'marque'      => $moto->marque,
            'annee'       => (int) $moto->annee,
            'nom_fr'      => $moto->nom_fr,
            'core_name'   => $moto->core_name,
            'type'        => $moto->type,
            'picture'     => $moto->picture_main,
        ];
    }

    /**
     * Fil d'Ariane : Accueil > Pièces détachées d'origine > [moto].
     */
    public function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();

        $breadcrumb['links'][] = [
            'title' => $this->module->l('Pièces détachées d\'origine', 'moto'),
            'url'   => '#',
        ];
        $motoSlug = Tools::str2url($this->moto->nom_fr);
        $breadcrumb['links'][] = [
            'title' => $this->moto->nom_fr,
            'url'   => $this->context->link->getModuleLink(
                'megaservice_microfiches',
                'moto',
                ['id_moto' => (int) $this->moto->id, 'slug' => $motoSlug]
            ),
        ];

        // Niveau "Partie" (cycle / moteur) après la moto, si la PLP est scopée.
        $partie = (string) Tools::getValue('partie');
        if (in_array($partie, MsMicroficheCategorie::PARTIES, true)) {
            $breadcrumb['links'][] = [
                'title' => $partie === 'moteur'
                    ? $this->module->l('Partie moteur', 'moto')
                    : $this->module->l('Partie cycle', 'moto'),
                'url'   => $this->context->link->getModuleLink(
                    'megaservice_microfiches',
                    'moto',
                    ['id_moto' => (int) $this->moto->id, 'slug' => $motoSlug, 'partie' => $partie]
                ),
            ];
        }

        return $breadcrumb;
    }
}
