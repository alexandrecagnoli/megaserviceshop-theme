<?php

class CategoryController extends CategoryControllerCore
{
    /**
     * Mapping ID catégorie => template
     *
     * Templates disponibles :
     *   'default' — 3 colonnes + sidebar filtres
     *   'full'    — 4 colonnes pleine largeur, sous-catégories en cards
     */
    private static $CATEGORY_TEMPLATES = [
        14 => 'full', // Lifestyle (vêtements)
        15 => 'full', // Équipements pilotes
    ];

    /**
     * Catégories racines qui affichent le contexte "moto sélectionnée"
     * (bandeau moto + badge "Compatible"). Le FILTRAGE réel des produits/facettes
     * se fait dans ps_facetedsearch via le hook actionFacetedSearchFilters du
     * module megaservice_mountability — pas ici.
     */
    private static $MOTO_CONTEXT_ROOT_IDS = [
        41,  // Accessoires Powerparts
        488, // Kits de pièces d'origine — les kits ne figurent sur aucune vue
             // éclatée, la compatibilité moto est leur seul filtre d'accès.
    ];

    /**
     * Racines dont le sous-arbre propose le grand sélecteur moto (celui de la
     * home) quand AUCUN filtre n'est actif.
     *
     *   12  Pièces détachées d'origine — couvre partie cycle (39), partie
     *       moteur (40) et les kits (488), qui en sont les enfants
     *   41  Accessoires Powerparts
     *
     * Sur ces pages, naviguer sans moto n'a guère de sens : le client tombe sur
     * des dizaines de milliers de références indifférenciées. On lui propose
     * donc de choisir sa moto avant de parcourir, plutôt qu'après.
     *
     * Volontairement plus large que $MOTO_CONTEXT_ROOT_IDS : le sélecteur est
     * une invitation, le bandeau de contexte une conséquence du filtre actif.
     */
    private static $MOTO_FINDER_ROOT_IDS = [12, 41];

    /**
     * Catégories dont la page devient un hub d'orientation au lieu d'une liste.
     *
     *   12  Pièces détachées d'origine
     *
     * Sous le sélecteur, cette page remontait 40 897 produits sur 3 409 pages —
     * et les premiers affichés étaient des manuels d'utilisateur sans photo. Une
     * liste n'a aucun sens ici : sans moto, la compatibilité n'est pas connue, et
     * la compatibilité des pièces d'origine passe de toute façon par les vues
     * éclatées, pas par la montabilité (cf. $MOTO_CONTEXT_ROOT_IDS).
     *
     * On remplace donc la liste par deux chemins vers la même fin : le sélecteur
     * pour qui connaît sa moto, la navigation par gamme pour qui la cherche. Les
     * deux mènent au hub moto, inchangé.
     *
     * Volontairement limité à 12 : ses enfants (39 partie cycle, 40 partie
     * moteur) souffrent du même défaut, mais les y étendre se décide, cf. le
     * commentaire de showPartsHub().
     */
    private static $PARTS_HUB_CATEGORY_IDS = [12];

    /** Millésimes affichés sur une carte modèle avant le repli « + N ». */
    const HUB_YEARS_SHOWN = 6;

    /** Modèles affichés quand aucune gamme n'est choisie. */
    const HUB_MODELS_DEFAULT = 12;

    /** Garde-fou : une gamme très fournie ne doit pas rendre 300 cartes. */
    const HUB_MODELS_MAX = 60;

    /** @var int|null id_moto du "garage" (cookie), mémoïsé. */
    private $motoFilterId;

    public function init()
    {
        parent::init();

        // Retrait du filtre garage : ?ms_clear_moto=1 → efface le cookie + URL propre.
        if ((int) Tools::getValue('ms_clear_moto') === 1) {
            $this->context->cookie->ms_moto = 0;
            $this->context->cookie->write();
            Tools::redirect($this->context->link->getCategoryLink((int) $this->category->id));
        }
    }

    public function initContent()
    {
        // AVANT parent::initContent() : sur un clic de facette, ps_facetedsearch
        // ne redemande que la liste (ajax=1&action=productlist) et le parent rend
        // puis interrompt la requête. Posées après, ces variables n'existaient pas
        // dans ce rendu — le badge « Compatible » disparaissait jusqu'au prochain
        // chargement complet de la page (d'où sa réapparition au retour navigateur).
        $this->assignMotoContext();

        parent::initContent();
    }

    /**
     * Variables de contexte moto pour les templates de listing et les miniatures.
     */
    private function assignMotoContext()
    {
        $category_id = (int) $this->category->id;
        $template = isset(self::$CATEGORY_TEMPLATES[$category_id])
            ? self::$CATEGORY_TEMPLATES[$category_id]
            : 'default';

        $motoFilter = $this->motoFilterBanner();

        $this->context->smarty->assign([
            'ms_category_template'  => $template,
            'ms_is_full_width'      => $template === 'full',
            // Badge "Compatible" + contexte : uniquement quand un filtre moto est ACTIF.
            'ms_show_moto_context'  => (bool) $motoFilter,
            'ms_moto_filter'        => $motoFilter,
            // Listing vide FAUTE DE DONNÉES pour cette moto (≠ moto connue sans
            // pièce dans cette catégorie). Sans ce flag, le template servait le
            // générique "Aucun produit disponible pour le moment" — illisible
            // face à une catégorie de 4 000 produits.
            'ms_moto_no_data'       => $this->motoHasNoMountabilityData(),
            // Grand sélecteur moto (section de la home) quand aucun filtre n'est actif.
            'ms_show_moto_finder'   => $this->showMotoFinder(),
            // Racine de branche pour le bloc « parent catégorie » de la sidebar.
            'ms_moto_context_root'  => $this->motoContextRoot(),
            // Produits réellement compatibles : le badge « Compatible » de la
            // carte doit vérifier, pas supposer (cf. compatibleProductIds).
            'ms_compatible_ids'     => $this->compatibleProductIds(),
        ]);

        $this->assignPartsHub();
    }

    /**
     * Données du hub d'orientation qui remplace la liste produits (cf.
     * $PARTS_HUB_CATEGORY_IDS). Rien n'est posé hors de ces catégories : le
     * template ne rend le hub que sur `ms_show_parts_hub`.
     */
    private function assignPartsHub()
    {
        if (!$this->showPartsHub()) {
            $this->context->smarty->assign('ms_show_parts_hub', false);

            return;
        }

        // La gamme choisie reste dans l'URL (?gamme=Enduro) : pas de nouvelle
        // route à déclarer, et le lien est partageable et indexable.
        $gamme = trim((string) Tools::getValue('gamme'));
        if ($gamme !== '' && !in_array($gamme, MsMoto::TYPES, true)) {
            $gamme = '';
        }

        $this->context->smarty->assign([
            'ms_show_parts_hub' => true,
            'ms_hub_gammes'     => $this->partsHubGammes(),
            'ms_hub_gamme'      => $gamme,
            'ms_hub_models'     => $this->partsHubModels($gamme),
        ]);
    }

    /**
     * Le hub remplace-t-il la liste sur cette page ?
     *
     * Vrai avec ou sans moto en garage : sur ces branches le filtre moto ne
     * s'applique pas aux produits (cf. $MOTO_CONTEXT_ROOT_IDS), donc une liste y
     * est tout aussi indifférenciée dans les deux cas. Une moto en garage change
     * seulement ce que propose le hub, pas le fait qu'il s'affiche.
     *
     * Test sur l'identifiant exact, pas sur le sous-arbre : étendre aux enfants
     * 39 et 40 supprimerait leur liste produits, ce qui se décide avant de se
     * coder — elles sont des catégories marchandes à part entière.
     */
    private function showPartsHub()
    {
        return $this->category->id
            && in_array((int) $this->category->id, self::$PARTS_HUB_CATEGORY_IDS, true)
            && $this->motoClassesAvailable();
    }

    /**
     * MsMoto vit dans megaservice_microfiches, pas dans le thème : si le module
     * est désinstallé, le hub s'efface au lieu de casser la catégorie.
     */
    private function motoClassesAvailable()
    {
        if (class_exists('MsMoto')) {
            return true;
        }

        $file = _PS_MODULE_DIR_ . 'megaservice_microfiches/classes/MsMoto.php';
        if (is_file($file)) {
            require_once $file;
        }

        return class_exists('MsMoto');
    }

    /**
     * Les gammes (type de moto), avec leur nombre de modèles distincts et une
     * photo représentative — celle du millésime le plus récent qui en a une.
     *
     * @return array<int, array{type:string,label:string,nb:int,picture:string,url:string}>
     */
    private function partsHubGammes()
    {
        $table = _DB_PREFIX_ . 'ms_moto';

        $rows = Db::getInstance()->executeS(
            'SELECT t.`type`, t.`nb`,
                    (SELECT m2.`picture_main` FROM `' . $table . '` m2
                      WHERE m2.`type` = t.`type` AND m2.`active` = 1
                        AND m2.`picture_main` IS NOT NULL AND m2.`picture_main` <> ""
                      ORDER BY m2.`annee` DESC LIMIT 1) AS picture
               FROM (SELECT `type`, COUNT(DISTINCT CONCAT(`marque`, "|", `core_name`)) AS nb
                       FROM `' . $table . '`
                      WHERE `active` = 1
                      GROUP BY `type`) t'
        ) ?: [];

        $byType = [];
        foreach ($rows as $r) {
            $byType[(string) $r['type']] = $r;
        }

        // Ordre de MsMoto::TYPES plutôt que celui du SQL : l'ordre d'affichage
        // ne doit pas bouger au gré du catalogue.
        $out = [];
        foreach (MsMoto::TYPES as $type) {
            if (empty($byType[$type]) || (int) $byType[$type]['nb'] === 0) {
                continue;
            }
            $out[] = [
                'type'    => $type,
                'label'   => $this->gammeLabel($type),
                'nb'      => (int) $byType[$type]['nb'],
                'picture' => $this->motoPictureUrl((string) $byType[$type]['picture']),
                'url'     => $this->hubGammeUrl($type),
            ];
        }

        return $out;
    }

    /** `Electrique` est stocké sans accent (enum SQL) ; l'affichage en porte un. */
    private function gammeLabel($type)
    {
        return $type === 'Electrique' ? 'Électrique' : (string) $type;
    }

    /**
     * URL de la page courante filtrée sur une gamme.
     *
     * Le paramètre est ajouté à la main plutôt que passé à getCategoryLink() :
     * sa signature n'accepte pas de paramètres libres — son 4e argument est la
     * chaîne de facettes de ps_facetedsearch, pas un tableau de query string.
     */
    private function hubGammeUrl($type)
    {
        $url = $this->context->link->getCategoryLink($this->category);

        return $url
            . (strpos($url, '?') === false ? '?' : '&')
            . 'gamme=' . urlencode((string) $type);
    }

    /**
     * Les modèles à afficher : ceux de la gamme choisie, sinon les plus récents.
     *
     * Une ligne de ps_ms_moto = un couple (modèle, millésime). On regroupe par
     * modèle et on porte les millésimes SUR la carte : une pièce dépend de
     * l'année, le choix doit rester explicite — mais sans page intermédiaire
     * entre le modèle et son hub.
     *
     * @return array<int, array{marque:string,name:string,picture:string,years:array}>
     */
    private function partsHubModels($gamme)
    {
        $table = _DB_PREFIX_ . 'ms_moto';

        // Deux passes, et c'est voulu. Une seule requête avec LIMIT donnerait des
        // millésimes TRONQUÉS : limiter les lignes limite les couples
        // (modèle, année), pas les modèles. Une carte aurait annoncé « 2026,
        // 2025 » pour une moto produite depuis 2017 — faux, et sur un site de
        // pièces une année manquante se paie en retour client.
        //
        // Passe 1 : quels modèles afficher.
        $where = '`active` = 1';
        if ($gamme !== '') {
            $where .= ' AND `type` = "' . pSQL($gamme) . '"';
        }

        $max = $gamme !== '' ? self::HUB_MODELS_MAX : self::HUB_MODELS_DEFAULT;

        $keys = Db::getInstance()->executeS(
            'SELECT `marque`, `core_name`, MAX(`annee`) AS recent
               FROM `' . $table . '`
              WHERE ' . $where . '
              GROUP BY `marque`, `core_name`
              ORDER BY ' . ($gamme !== '' ? '`marque`, `core_name`' : 'recent DESC, `marque`, `core_name`') . '
              LIMIT ' . (int) $max
        ) ?: [];

        if (empty($keys)) {
            return [];
        }

        // Passe 2 : TOUS les millésimes de ces modèles, tronqués par personne.
        $pairs = [];
        foreach ($keys as $k) {
            $pairs[] = '(`marque` = "' . pSQL($k['marque']) . '"'
                     . ' AND `core_name` = "' . pSQL($k['core_name']) . '")';
        }

        $rows = Db::getInstance()->executeS(
            'SELECT `id_moto`, `marque`, `core_name`, `type`, `annee`, `picture_main`
               FROM `' . $table . '`
              WHERE `active` = 1 AND (' . implode(' OR ', $pairs) . ')
              ORDER BY `annee` DESC'
        ) ?: [];

        // L'ordre des cartes est celui de la passe 1, pas celui des millésimes.
        $models = [];
        foreach ($keys as $k) {
            $models[$k['marque'] . '|' . $k['core_name']] = [
                'marque'  => (string) $k['marque'],
                'name'    => (string) $k['core_name'],
                'picture' => '',
                'years'   => [],
            ];
        }

        foreach ($rows as $r) {
            $key = $r['marque'] . '|' . $r['core_name'];
            if (!isset($models[$key])) {
                continue;
            }

            if ($models[$key]['picture'] === '') {
                $models[$key]['picture'] = $this->motoPictureUrl((string) $r['picture_main']);
            }

            $models[$key]['years'][] = [
                'annee' => (int) $r['annee'],
                'url'   => MsMoto::hubUrl(
                    $this->context,
                    (int) $r['id_moto'],
                    $r['marque'],
                    $r['core_name'],
                    $r['annee'],
                    $r['type']
                ),
            ];
        }

        // Millésimes du plus récent au plus ancien, quel que soit l'ordre SQL,
        // puis coupés : le surplus est annoncé, pas rendu. Le découpage est fait
        // ici et non dans le template, pour que le seuil reste une constante et
        // non un nombre recopié des deux côtés.
        foreach ($models as &$model) {
            usort($model['years'], function ($a, $b) {
                return $b['annee'] - $a['annee'];
            });

            $total = count($model['years']);
            $model['years_more'] = max(0, $total - self::HUB_YEARS_SHOWN);
            $model['years']      = array_slice($model['years'], 0, self::HUB_YEARS_SHOWN);
        }
        unset($model);

        return array_values($models);
    }

    /**
     * URL publique d'une photo de moto, ou '' si le fichier manque sur le disque.
     *
     * Même garde-fou que MotoController::partieImageUrl : une image cassée est
     * pire que pas d'image, le template a sa réserve.
     */
    private function motoPictureUrl($relative)
    {
        $rel = trim((string) $relative);
        if ($rel === '' || strpos($rel, '..') !== false) {
            return '';
        }
        if (!is_file(_PS_ROOT_DIR_ . '/img/ms_moto/' . $rel)) {
            return '';
        }

        return __PS_BASE_URI__ . 'img/ms_moto/' . $rel;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SEO (Volet 2, étapes 1-2 + 5) : canonical, non-redirection, meta
    // ─────────────────────────────────────────────────────────────────────────

    /** Canonical « moto seule » (sans facette q=) quand le filtre moto est actif. */
    public function getCanonicalURL()
    {
        if ($this->getMotoFilterId() && $this->isInMotoContextSubtree() && class_exists('MsMountability')) {
            return MsMountability::motoFilteredCategoryUrl((int) $this->category->id, $this->getMotoFilterId());
        }

        return parent::getCanonicalURL();
    }

    /**
     * Filtre moto actif → on NE redirige PAS : sinon PS ferait un 301 vers le
     * canonical « moto seule » et écraserait le param ?moto= et/ou les facettes.
     * Le <link rel="canonical"> (getCanonicalURL) suffit pour concentrer le SEO.
     */
    public function canonicalRedirection($canonicalURL = '')
    {
        if ($this->getMotoFilterId() && $this->isInMotoContextSubtree()) {
            return;
        }

        parent::canonicalRedirection($canonicalURL);
    }

    /** Title / meta dédiés à la moto + noindex des combinaisons moto + facette. */
    public function getTemplateVarPage()
    {
        $page = parent::getTemplateVarPage();

        $banner = ($this->getMotoFilterId() && $this->isInMotoContextSubtree())
            ? $this->motoFilterBanner()
            : null;

        if ($banner) {
            $page['meta']['title']       = 'Accessoires Powerparts pour ' . $banner['seo_label'];
            $page['meta']['description'] = 'Tous les accessoires Powerparts compatibles avec '
                . $banner['seo_label'] . ' — Mega Service Shop.';

            // Étape 5 (anti-duplication) : une facette EN PLUS de la moto → noindex.
            // Le canonical pointe déjà sur la vue « moto seule ».
            if (Tools::getValue('q')) {
                $page['meta']['robots'] = 'noindex,follow';
            }
        }

        return $page;
    }

    /** id_moto du filtre actif (URL prioritaire → cookie secours), 0 si absent. */
    private function getMotoFilterId()
    {
        if ($this->motoFilterId === null) {
            $this->motoFilterId = $this->resolveMoto();
        }

        return $this->motoFilterId;
    }

    /** Délègue au module montabilité (même logique URL/cookie que le hook facetedsearch). */
    private function resolveMoto()
    {
        if (!class_exists('MsMountability')) {
            $file = _PS_MODULE_DIR_ . 'megaservice_mountability/classes/MsMountability.php';
            if (is_file($file)) {
                require_once $file;
            }
        }
        if (class_exists('MsMountability')) {
            return (int) MsMountability::resolveActiveMoto();
        }

        return (int) $this->context->cookie->ms_moto;
    }

    /**
     * Données du bandeau "Catalogue filtré sur X" (ou null si pas de filtre actif
     * sur cette catégorie). "changer" rouvre la modale (js-model-trigger),
     * "retirer" efface le cookie.
     *
     * @return array{label:string,clear_url:string}|null
     */
    private function motoFilterBanner()
    {
        $idMoto = $this->getMotoFilterId();
        if (!$idMoto || !$this->isInMotoContextSubtree()) {
            return null;
        }

        $row = Db::getInstance()->getRow(
            'SELECT `marque`, `annee`, `core_name`, `nom_fr`
             FROM `' . _DB_PREFIX_ . 'ms_moto`
             WHERE `id_moto` = ' . (int) $idMoto . ' AND `active` = 1'
        );
        if (!$row) {
            return null;
        }

        $name  = $row['core_name'] !== '' ? $row['core_name'] : $row['nom_fr'];
        $base  = $this->context->link->getCategoryLink((int) $this->category->id);
        $clear = $base . (strpos($base, '?') !== false ? '&' : '?') . 'ms_clear_moto=1';

        return [
            'label'     => trim($row['annee'] . ' ' . $name),                        // bandeau (affichage)
            'seo_label' => trim($row['marque'] . ' ' . $name . ' ' . $row['annee']),  // title/meta
            // Fil d'Ariane : modèle PUIS année (« EC 300 2024 »), l'inverse du
            // bandeau. Le fil se lit comme un chemin, on y nomme d'abord la moto.
            'crumb'     => trim($name . ' ' . $row['annee']),
            'hub_url'   => $this->motoHubUrl((int) $idMoto),
            'clear_url' => $clear,
        ];
    }

    /**
     * Page « hub » de la moto (module microfiches). Chaîne vide si le module est
     * absent : l'override ne doit jamais dépendre de sa présence pour fonctionner.
     */
    private function motoHubUrl($idMoto)
    {
        if (!Module::isInstalled('megaservice_microfiches')) {
            return '';
        }
        $params = ['id_moto' => (int) $idMoto];
        if (class_exists('MsMountability')) {
            $slug = MsMountability::motoSlug($idMoto);
            if ($slug !== '') {
                $params['slug'] = $slug;
            }
        }

        return $this->context->link->getModuleLink('megaservice_microfiches', 'moto', $params);
    }

    /**
     * Fil d'Ariane sous filtre moto : on remplace le chemin de catégories par le
     * chemin de NAVIGATION réellement emprunté par le client.
     *
     *   Accueil > Pièces détachées d'origine > EC 300 2024 > Accessoires powerparts
     *
     * Sans ça, le fil affichait l'arborescence catalogue seule et le client
     * perdait la trace de la moto sur laquelle il filtre, sans moyen d'y revenir.
     * La structure reprend celle du fil du module microfiches (moto.php) pour que
     * les deux parcours se ressemblent.
     */
    public function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();

        $moto = $this->motoFilterBanner();
        if (!$moto || empty($breadcrumb['links'])) {
            return $breadcrumb;
        }

        // On ne garde que l'Accueil du fil natif, puis on reconstruit.
        $home = $breadcrumb['links'][0];
        $breadcrumb['links'] = [$home];

        // Même libellé et même url ('#') que le fil du module microfiches : ce
        // niveau est un intitulé de parcours, pas une catégorie navigable.
        $breadcrumb['links'][] = [
            'title' => 'Pièces détachées d\'origine',
            'url'   => '#',
        ];

        $breadcrumb['links'][] = [
            'title' => $moto['crumb'],
            'url'   => $moto['hub_url'] !== '' ? $moto['hub_url'] : '#',
        ];

        $breadcrumb['links'][] = [
            'title' => $this->category->name,
            'url'   => $this->context->link->getCategoryLink((int) $this->category->id),
        ];

        return $breadcrumb;
    }

    /**
     * Vrai quand un filtre moto est actif sur une catégorie Powerparts alors
     * qu'AUCUNE donnée de montabilité n'existe pour cette moto.
     *
     * Le hook actionFacetedSearchFilters force alors un résultat vide (filtre
     * `id_product IN (NULL)`), ce qui est le bon comportement — mais la page
     * doit le dire, sinon 4 000 produits disparaissent derrière un message
     * générique de catalogue vide. Cas courant tant que toutes les marques ne
     * sont pas importées : au moment de l'écriture, seul KTM l'était.
     */
    private function motoHasNoMountabilityData()
    {
        $idMoto = $this->getMotoFilterId();
        if (!$idMoto || !$this->isInMotoContextSubtree()) {
            return false;
        }
        if (!class_exists('MsMountability')) {
            return false;
        }

        return !MsMountability::hasMountabilityData($idMoto);
    }

    private function isInMotoContextSubtree()
    {
        return $this->isInSubtreeOf(self::$MOTO_CONTEXT_ROOT_IDS);
    }

    /**
     * Racine de la branche à contexte moto dont dépend la catégorie courante.
     *
     * Alimente le bloc « parent catégorie » de la sidebar, dont le libellé était
     * écrit en dur (« Accessoires powerparts ») du temps où la branche 41 était
     * seule concernée. Depuis l'ajout des Kits (488), il annonçait la mauvaise
     * catégorie.
     *
     * On renvoie la RACINE et non la catégorie courante : sur 41 > Freinage, le
     * bloc doit annoncer la branche (« Accessoires powerparts »), pas la
     * sous-catégorie où l'on se trouve.
     *
     * @return array{id:int,name:string}|null
     */
    private function motoContextRoot()
    {
        if (!$this->category->id) {
            return null;
        }

        foreach (self::$MOTO_CONTEXT_ROOT_IDS as $root_id) {
            $root = new Category((int) $root_id, (int) $this->context->language->id);
            if (!$root->id) {
                continue;
            }
            if ($this->category->nleft >= $root->nleft && $this->category->nright <= $root->nright) {
                return ['id' => (int) $root->id, 'name' => $root->name];
            }
        }

        return null;
    }

    /**
     * La catégorie courante est-elle dans le sous-arbre de l'une des racines ?
     *
     * S'appuie sur le nested set (nleft/nright). Attention : il doit être
     * cohérent — une corruption de l'arbre fausse silencieusement ce test, ce
     * qui s'est produit et a bloqué les facettes catégorie (cf.
     * data/sql/2026-09-23_reparation_arbre_categories.sql).
     *
     * @param int[] $rootIds
     */
    private function isInSubtreeOf(array $rootIds)
    {
        if (empty($rootIds) || !$this->category->id) {
            return false;
        }

        foreach ($rootIds as $root_id) {
            $root = new Category((int) $root_id);
            if (!$root->id) {
                continue; // racine supprimée ou pas encore créée
            }
            if ($this->category->nleft >= $root->nleft && $this->category->nright <= $root->nright) {
                return true;
            }
        }

        return false;
    }

    /**
     * Faut-il proposer le grand sélecteur moto (section de la home) ?
     *
     * Oui quand on est dans une branche « pièces » ET qu'aucun filtre moto n'est
     * actif. Dès qu'une moto est choisie, le bandeau de contexte prend le relais
     * et le sélecteur n'a plus lieu d'être — les deux ne coexistent jamais.
     */
    private function showMotoFinder()
    {
        if (!$this->isInSubtreeOf(self::$MOTO_FINDER_ROOT_IDS)) {
            return false;
        }

        // On ne masque le sélecteur QUE là où le bandeau de contexte le remplace
        // réellement, c'est-à-dire dans les branches où le filtre moto s'applique
        // aux produits (41, 488).
        //
        // Ailleurs — pièces détachées (12, 39, 40) — aucun filtrage n'a lieu : la
        // compatibilité y passe par les microfiches, pas par la montabilité. Le
        // sélecteur n'y est donc pas un filtre mais une PORTE D'ENTRÉE : on choisit
        // sa moto et on part sur sa page. Il doit rester visible même avec une moto
        // en garage, sinon ces pages n'affichent plus rien de moto — ni sélecteur
        // (masqué par le filtre) ni bandeau (hors périmètre de contexte).
        if ($this->getMotoFilterId() && $this->isInMotoContextSubtree()) {
            return false;
        }

        return true;
    }

    /**
     * Identifiants des produits réellement compatibles avec la moto filtrée,
     * indexés en clés pour un test O(1) côté template.
     *
     * Le badge « Compatible » de la carte était affiché dès qu'un contexte moto
     * existait, sans jamais regarder le produit : sur une liste dont le filtre
     * de montabilité a sauté (facette Catégories), il affirmait donc du faux.
     * On lui donne de quoi vérifier.
     *
     * @return array<int, bool>
     */
    private function compatibleProductIds()
    {
        $idMoto = $this->getMotoFilterId();
        if (!$idMoto || !class_exists('MsMountability')) {
            return [];
        }

        $ids = MsMountability::getCompatibleProducts($idMoto);

        return $ids ? array_fill_keys(array_map('intval', $ids), true) : [];
    }
}
