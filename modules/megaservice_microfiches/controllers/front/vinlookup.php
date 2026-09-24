<?php
/**
 * Recherche d'une moto par VIN (onglet « par VIN » de la modale du header).
 *
 * Le VIN ne contient pas notre référentiel : il faut interroger le « spare parts
 * finder » public de chaque constructeur, qui renvoie le CODE MODÈLE (ex. F8203Y2)
 * de la moto. Ce code est exactement `ms_moto.serial_constructeur` — un code par
 * moto, année comprise, sans doublon (mesuré sur 1 817 motos actives) — d'où la
 * jointure directe.
 *
 * Inspiré du script de scraping de l'ancien site (docs/scraping_vin), avec trois
 * différences voulues :
 *   - la vérification SSL reste ACTIVE (l'ancien script la coupait) ;
 *   - les trois constructeurs sont interrogés EN PARALLÈLE, sans que l'utilisateur
 *     ait à choisir la marque ;
 *   - le code est cherché parmi les mots du bandeau de réponse qu'on rapproche de
 *     notre base, plutôt qu'en découpant un format de texte précis : une retouche
 *     de la mise en page du constructeur ne casse pas la recherche.
 *
 * POST : vin
 * Réponse : { status, [vin, label, url, marque] }
 *   status = ok | invalid | not_found | unavailable | throttled
 *
 * Le VIN est envoyé en POST (pas dans l'URL, donc pas dans les journaux d'accès),
 * jamais journalisé ni stocké.
 */
class Megaservice_microfichesVinlookupModuleFrontController extends ModuleFrontController
{
    /** Valeur de ms_moto.marque → [page de résultat du constructeur, libellé]. */
    const SOURCES = [
        'KTM'    => ['https://sparepartsfinder.ktm.com/Result', 'KTM'],
        'HQV'    => ['https://sparepartsfinder.husqvarna-motorcycles.com/Result', 'Husqvarna'],
        'GASGAS' => ['https://sparepartsfinder.gasgas.com/Result', 'GasGas'],
    ];

    /** Le point d'entrée relaie vers des tiers : borné par IP pour ne pas servir de rebond. */
    const THROTTLE_MAX    = 10;
    const THROTTLE_WINDOW = 600; // secondes

    const HEADER_ID = 'ComponentGroupTemplateContentContainerHeader';

    public function initContent()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        die(json_encode($this->resolve()));
    }

    private function resolve()
    {
        $vin = strtoupper(preg_replace('/\s+/', '', (string) Tools::getValue('vin')));

        // 17 caractères, sans I, O ni Q (norme ISO 3779).
        if (!preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vin)) {
            return ['status' => 'invalid'];
        }
        if ($this->throttled()) {
            return ['status' => 'throttled'];
        }

        $responses = $this->fetchAll($vin);
        $anyAnswered = false;

        foreach (self::SOURCES as $marque => $src) {
            if (!isset($responses[$marque])) {
                continue; // transport en échec pour ce constructeur
            }
            $anyAnswered = true;

            $moto = $this->matchMoto($marque, $responses[$marque]);
            if ($moto !== null) {
                $name = (string) $moto['core_name'];

                return [
                    'status' => 'ok',
                    'vin'    => $vin,
                    'marque' => $marque,
                    'label'  => trim($moto['annee'] . ' ' . $src[1] . ' ' . $name),
                    'url'    => MsMoto::hubUrl($this->context, $moto['id_moto'], $marque, $name, $moto['annee'], $moto['type']),
                ];
            }
        }

        // Au moins un constructeur a répondu sans reconnaître le VIN → VIN inconnu.
        // Aucun n'a répondu → le service est en cause, pas la saisie.
        return ['status' => $anyAnswered ? 'not_found' : 'unavailable'];
    }

    /**
     * Interroge les trois constructeurs en parallèle.
     *
     * @return array<string,string> marque => HTML de la réponse (absente si échec)
     */
    private function fetchAll($vin)
    {
        $multi = curl_multi_init();
        $handles = [];

        foreach (self::SOURCES as $marque => $src) {
            $ch = curl_init($src[0]);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_ENCODING       => '',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121 Safari/537.36',
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => 'VehicleIdentificationNumber=' . rawurlencode($vin) . '&ModelIdentification=&IsEngine=false',
            ]);
            curl_multi_add_handle($multi, $ch);
            $handles[$marque] = $ch;
        }

        do {
            $status = curl_multi_exec($multi, $running);
            if ($running) {
                curl_multi_select($multi, 1.0);
            }
        } while ($running && $status === CURLM_OK);

        $out = [];
        foreach ($handles as $marque => $ch) {
            $body = curl_multi_getcontent($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            if ($body !== false && $body !== '' && $code > 0 && $code < 400) {
                $out[$marque] = (string) $body;
            }
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }
        curl_multi_close($multi);

        return $out;
    }

    /**
     * Rapproche la réponse d'un constructeur de notre référentiel.
     *
     * 1. Par CODE MODÈLE : les mots de 6 à 8 caractères du bandeau de résultat
     *    sont testés contre ms_moto.serial_constructeur (même marque).
     * 2. À défaut, par NOM + ANNÉE. Un même modèle porte un code différent selon le
     *    marché (ex. 690 Enduro R 2016 : F9703P8 en Europe, F9775P8 aux USA) et notre
     *    référentiel vient du catalogue européen — un VIN US, ou hors Europe, ne
     *    retombe donc jamais sur un code connu alors que le modèle, lui, l'est.
     *
     * @return array<string,mixed>|null
     */
    private function matchMoto($marque, $html)
    {
        $info = $this->headerInfo($html);
        if ($info === null) {
            return null;
        }

        if ($info['tokens']) {
            $in = implode(',', array_map(function ($t) {
                return '"' . pSQL($t) . '"';
            }, $info['tokens']));

            $row = Db::getInstance()->getRow(
                'SELECT `id_moto`, `annee`, `type`, `core_name`
                 FROM `' . _DB_PREFIX_ . 'ms_moto`
                 WHERE `marque` = "' . pSQL($marque) . '" AND `active` = 1
                   AND `serial_constructeur` IN (' . $in . ')
                 ORDER BY `id_moto` ASC'
            );
            if ($row) {
                return $row;
            }
        }

        return $this->matchByName($marque, $info);
    }

    /**
     * Repli : le nom annoncé par le constructeur (« 690 ENDURO R ABS ») commence par
     * le core_name d'une moto de la même année (« 690 Enduro R »). Le plus long
     * gagne, pour ne pas confondre « 690 Duke » et « 690 Duke R » ; une égalité que
     * le suffixe du code (année + révision, ex. P8) ne départage pas → aucun résultat
     * plutôt qu'une moto au hasard.
     *
     * @param array<string,mixed> $info
     * @return array<string,mixed>|null
     */
    private function matchByName($marque, array $info)
    {
        if ($info['name'] === '' || !$info['year']) {
            return null;
        }

        $rows = Db::getInstance()->executeS(
            'SELECT `id_moto`, `annee`, `type`, `core_name`, `serial_constructeur`
             FROM `' . _DB_PREFIX_ . 'ms_moto`
             WHERE `marque` = "' . pSQL($marque) . '" AND `annee` = ' . (int) $info['year'] . ' AND `active` = 1'
        ) ?: [];

        $wanted = $this->normalize($info['name']);
        $bestLen = 0;
        $best = [];
        foreach ($rows as $r) {
            $n = $this->normalize($r['core_name']);
            if ($n === '' || strpos($wanted, $n) !== 0) {
                continue;
            }
            if (strlen($n) > $bestLen) {
                $bestLen = strlen($n);
                $best = [$r];
            } elseif (strlen($n) === $bestLen) {
                $best[] = $r;
            }
        }

        if (count($best) === 1) {
            return $best[0];
        }
        if (count($best) > 1) {
            $suffixes = array_map(function ($t) {
                return substr($t, -2);
            }, $info['tokens']);
            $bySuffix = array_values(array_filter($best, function ($r) use ($suffixes) {
                return in_array(substr((string) $r['serial_constructeur'], -2), $suffixes, true);
            }));
            if (count($bySuffix) === 1) {
                return $bySuffix[0];
            }
        }

        return null;
    }

    private function normalize($text)
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $text));
    }

    /**
     * Lit le bandeau de résultat du constructeur, ex.
     *   « 690 ENDURO R ABS 2016 <2016><US><F9775P8> »
     *
     * @return array{name:string,year:int,tokens:string[]}|null null si pas de bandeau
     */
    private function headerInfo($html)
    {
        $prev = libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $text = '';
        $xpath = new DOMXPath($doc);
        foreach ($xpath->query('//*[@id="' . self::HEADER_ID . '"]') as $node) {
            $text .= ' ' . $node->textContent;
        }
        if (trim($text) === '') {
            return null;
        }

        // Le texte peut arriver encodé deux fois (&amp;lt;) : on décode d'abord.
        $text = html_entity_decode(html_entity_decode($text, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
        $upper = strtoupper($text);

        preg_match_all('/\b[A-Z0-9]{6,8}\b/', $upper, $m);
        $tokens = array_slice(array_values(array_unique($m[0])), 0, 40);

        // Nom = ce qui précède le premier « < » ; l'année s'y trouve, et dans <2016>.
        $name = trim(preg_split('/</', $upper)[0]);
        $year = 0;
        if (preg_match('/<\s*((?:19|20)\d{2})\s*>/', $upper, $ym)) {
            $year = (int) $ym[1];
        } elseif (preg_match('/\b((?:19|20)\d{2})\b/', $name, $ym)) {
            $year = (int) $ym[1];
        }
        $name = trim(preg_replace('/\b(?:19|20)\d{2}\b/', '', $name));

        return ['name' => $name, 'year' => $year, 'tokens' => $tokens];
    }

    /**
     * Limite par IP (fichier dans le cache PS). Vrai = quota dépassé.
     * Volontairement simple : c'est un garde-fou contre l'usage comme relais, pas
     * une protection contre un attaquant déterminé.
     */
    private function throttled()
    {
        $dir = _PS_CACHE_DIR_ . 'ms_vin_throttle/';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false; // pas de dossier : on ne bloque pas le client pour ça
        }

        $file = $dir . md5((string) Tools::getRemoteAddr()) . '.json';
        $now = time();
        $hits = [];
        if (is_file($file)) {
            $hits = json_decode((string) @file_get_contents($file), true) ?: [];
        }
        $hits = array_values(array_filter($hits, function ($t) use ($now) {
            return $t > $now - self::THROTTLE_WINDOW;
        }));

        if (count($hits) >= self::THROTTLE_MAX) {
            return true;
        }
        $hits[] = $now;
        @file_put_contents($file, json_encode($hits), LOCK_EX);

        return false;
    }
}
