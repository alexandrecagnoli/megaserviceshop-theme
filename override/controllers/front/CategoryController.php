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
        41, // Accessoires Powerparts
    ];

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
        parent::initContent();

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
        ]);
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
            'clear_url' => $clear,
        ];
    }

    private function isInMotoContextSubtree()
    {
        if (empty(self::$MOTO_CONTEXT_ROOT_IDS) || !$this->category->id) {
            return false;
        }

        foreach (self::$MOTO_CONTEXT_ROOT_IDS as $root_id) {
            $root = new Category((int) $root_id);
            if (!$root->id) {
                continue;
            }
            if ($this->category->nleft >= $root->nleft && $this->category->nright <= $root->nright) {
                return true;
            }
        }

        return false;
    }
}
