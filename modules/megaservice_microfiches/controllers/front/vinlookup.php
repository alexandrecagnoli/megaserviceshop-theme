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
     * Rapproche la réponse d'un constructeur de notre référentiel : les mots de
     * 6 à 8 caractères du bandeau de résultat sont testés contre
     * ms_moto.serial_constructeur (de la même marque).
     *
     * @return array<string,mixed>|null
     */
    private function matchMoto($marque, $html)
    {
        $tokens = $this->headerTokens($html);
        if (!$tokens) {
            return null;
        }

        $in = implode(',', array_map(function ($t) {
            return '"' . pSQL($t) . '"';
        }, $tokens));

        $row = Db::getInstance()->getRow(
            'SELECT `id_moto`, `annee`, `type`, `core_name`
             FROM `' . _DB_PREFIX_ . 'ms_moto`
             WHERE `marque` = "' . pSQL($marque) . '" AND `active` = 1
               AND `serial_constructeur` IN (' . $in . ')
             ORDER BY `id_moto` ASC'
        );

        return $row ?: null;
    }

    /** @return string[] mots candidats (majuscules, dédoublonnés, 40 max) */
    private function headerTokens($html)
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
        if ($text === '') {
            return [];
        }

        // Le texte peut arriver encodé deux fois (&amp;lt;) : on décode d'abord.
        $text = strtoupper(html_entity_decode(html_entity_decode($text, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8'));
        preg_match_all('/\b[A-Z0-9]{6,8}\b/', $text, $m);

        return array_slice(array_values(array_unique($m[0])), 0, 40);
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
