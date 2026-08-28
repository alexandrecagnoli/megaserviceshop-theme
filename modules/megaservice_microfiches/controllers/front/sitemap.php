<?php
/**
 * Sitemap dédié aux pages moto (Volet 1 OEM + Volet 2 Powerparts).
 *
 * gsitemap ne connaît ni les routes /motos/… ni les URL ?moto= : ce contrôleur
 * les expose dans un sitemap INDEX segmenté, à déclarer dans robots.txt et à
 * soumettre dans Google Search Console.
 *
 *   /module/megaservice_microfiches/sitemap                → index
 *   …/sitemap?type=motos&p=1                               → hubs moto
 *   …/sitemap?type=microfiches&p=1                         → PDP microfiches
 *   …/sitemap?type=powerparts&p=1                          → URL ?moto= (racine 41)
 *
 * v1 : hubs (jamais vides) + PDP microfiches (le contenu OEM) + Powerparts des
 * seules motos ayant ≥1 produit compatible (pas de page filtrée vide). Les PLP
 * parties (cycle/moteur) restent crawlables via le hub, non listées ici.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'megaservice_microfiches/classes/MsMoto.php';

class Megaservice_microfichesSitemapModuleFrontController extends ModuleFrontController
{
    const POWERPARTS_CATEGORY_ID = 41;
    /** URLs par fichier (< 50 000, limite Google). */
    const CHUNK = 40000;

    public function initContent()
    {
        // Un sitemap DOIT être du XML pur, quel que soit l'environnement (une
        // préprod reste légitimement en mode dev). On coupe l'affichage des
        // erreurs et on jette tout output déjà bufferisé (warnings, BOM, espaces)
        // avant d'émettre — sinon le moindre notice invaliderait le XML.
        @ini_set('display_errors', '0');
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/xml; charset=utf-8');

        $type = (string) Tools::getValue('type');
        $page = max(1, (int) Tools::getValue('p'));

        switch ($type) {
            case '':
                exit($this->renderIndex());
            case 'motos':
                exit($this->renderUrlset($this->motosUrls($page)));
            case 'microfiches':
                exit($this->renderUrlset($this->microfichesUrls($page)));
            case 'powerparts':
                exit($this->renderUrlset($this->powerpartsUrls($page)));
        }

        header('HTTP/1.1 404 Not Found');
        exit();
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function renderIndex()
    {
        $link = $this->context->link;
        $now  = date('c');

        $counts = [
            'motos'       => (int) $this->count('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'ms_moto` WHERE `active`=1'),
            'microfiches' => (int) $this->count('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'ms_microfiche` WHERE `active`=1'),
            'powerparts'  => (int) $this->count(
                'SELECT COUNT(DISTINCT mo.`id_moto`)
                 FROM `' . _DB_PREFIX_ . 'ms_moto` mo
                 JOIN `' . _DB_PREFIX_ . 'ms_mountability` c ON c.`id_moto_constructeur` = mo.`serial_constructeur`
                 WHERE mo.`active`=1'
            ),
        ];

        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($counts as $type => $count) {
            $pages = max(1, (int) ceil($count / self::CHUNK));
            for ($p = 1; $p <= $pages; ++$p) {
                $loc = $link->getModuleLink('megaservice_microfiches', 'sitemap', ['type' => $type, 'p' => $p]);
                $out .= '  <sitemap><loc>' . $this->xml($loc) . '</loc><lastmod>' . $now . '</lastmod></sitemap>' . "\n";
            }
        }

        return $out . '</sitemapindex>';
    }

    /** @return array<int,array{loc:string,lastmod:string}> */
    private function motosUrls($page)
    {
        $rows = Db::getInstance()->executeS(
            'SELECT `id_moto`, `marque`, `core_name`, `annee`, `type`, `date_upd`
             FROM `' . _DB_PREFIX_ . 'ms_moto` WHERE `active`=1
             ORDER BY `id_moto` ' . $this->limit($page)
        ) ?: [];

        $link = $this->context->link;
        $urls = [];
        foreach ($rows as $r) {
            $slug = MsMoto::buildSlug($r['marque'], $r['core_name'], $r['annee'], $r['type']);
            $urls[] = [
                'loc'     => $link->getModuleLink('megaservice_microfiches', 'moto', ['id_moto' => (int) $r['id_moto'], 'slug' => $slug]),
                'lastmod' => $this->w3c($r['date_upd']),
            ];
        }

        return $urls;
    }

    /** @return array<int,array{loc:string,lastmod:string}> */
    private function microfichesUrls($page)
    {
        $rows = Db::getInstance()->executeS(
            'SELECT `id_microfiche`, `nom_fr`, `nom_constructeur`, `date_upd`
             FROM `' . _DB_PREFIX_ . 'ms_microfiche` WHERE `active`=1
             ORDER BY `id_microfiche` ' . $this->limit($page)
        ) ?: [];

        $link = $this->context->link;
        $urls = [];
        foreach ($rows as $r) {
            $name = ($r['nom_fr'] !== null && $r['nom_fr'] !== '') ? $r['nom_fr'] : $r['nom_constructeur'];
            $urls[] = [
                'loc'     => $link->getModuleLink('megaservice_microfiches', 'microfiche', ['id_microfiche' => (int) $r['id_microfiche'], 'slug' => Tools::str2url($name)]),
                'lastmod' => $this->w3c($r['date_upd']),
            ];
        }

        return $urls;
    }

    /** @return array<int,array{loc:string,lastmod:string}> */
    private function powerpartsUrls($page)
    {
        // Seules les motos ayant au moins un produit compatible (pas de page vide).
        $rows = Db::getInstance()->executeS(
            'SELECT DISTINCT mo.`id_moto`, mo.`marque`, mo.`core_name`, mo.`annee`, mo.`type`, mo.`date_upd`
             FROM `' . _DB_PREFIX_ . 'ms_moto` mo
             JOIN `' . _DB_PREFIX_ . 'ms_mountability` c ON c.`id_moto_constructeur` = mo.`serial_constructeur`
             WHERE mo.`active`=1
             ORDER BY mo.`id_moto` ' . $this->limit($page)
        ) ?: [];

        $base = $this->context->link->getCategoryLink(self::POWERPARTS_CATEGORY_ID);
        $urls = [];
        foreach ($rows as $r) {
            $slug  = MsMoto::buildSlug($r['marque'], $r['core_name'], $r['annee'], $r['type']);
            $token = (int) $r['id_moto'] . ($slug !== '' ? '-' . $slug : '');
            $loc   = $base . (strpos($base, '?') !== false ? '&' : '?') . 'moto=' . rawurlencode($token);
            $urls[] = ['loc' => $loc, 'lastmod' => $this->w3c($r['date_upd'])];
        }

        return $urls;
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function renderUrlset(array $urls)
    {
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $out .= '  <url><loc>' . $this->xml($u['loc']) . '</loc>';
            if ($u['lastmod'] !== '') {
                $out .= '<lastmod>' . $u['lastmod'] . '</lastmod>';
            }
            $out .= '</url>' . "\n";
        }

        return $out . '</urlset>';
    }

    private function limit($page)
    {
        $offset = ((int) $page - 1) * self::CHUNK;

        return 'LIMIT ' . (int) $offset . ',' . (int) self::CHUNK;
    }

    private function count($sql)
    {
        return Db::getInstance()->getValue($sql);
    }

    private function xml($s)
    {
        return htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function w3c($dt)
    {
        if (!$dt || $dt === '0000-00-00 00:00:00') {
            return '';
        }
        $ts = strtotime($dt);

        return $ts ? date('c', $ts) : '';
    }
}
