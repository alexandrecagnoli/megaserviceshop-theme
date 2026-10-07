<?php
/**
 * Téléchargement d'une microfiche (REC — Fonction téléchargement à améliorer).
 *
 * Cause : `image_full_url` pointe vers les sites constructeur (ktmdealer.net,
 * husqvarnadealer.net, gasgasdealer.net), jamais vers notre propre domaine.
 * L'attribut HTML `download` ne fonctionne QUE pour une URL de même origine —
 * sur une URL cross-origin, le navigateur l'ignore silencieusement et ouvre
 * l'image au lieu de la télécharger. C'est exactement le symptôme remonté :
 * « l'image s'affiche mais ne se télécharge pas ».
 *
 * `image_local` existe dans le schéma mais n'est rempli pour aucune des
 * 38 725 microfiches : pas de copie locale à servir directement. On relaie
 * donc la requête côté serveur (cURL, pas de restriction cross-origin entre
 * deux serveurs) et on la ressert depuis NOTRE domaine avec les en-têtes qui
 * déclenchent un vrai téléchargement.
 *
 * Liste blanche d'hôtes : seuls les 3 domaines constructeur réellement
 * observés dans `image_full_url` sont relayés — jamais une URL arbitraire,
 * pour ne pas faire de ce contrôleur un proxy ouvert.
 */
class Megaservice_microfichesDownloadModuleFrontController extends ModuleFrontController
{
    const ALLOWED_HOSTS = [
        'www.ktmdealer.net',
        'www.husqvarnadealer.net',
        'www.gasgasdealer.net',
    ];

    const FETCH_TIMEOUT = 15;

    public function initContent()
    {
        $idMicrofiche = (int) Tools::getValue('id_microfiche');
        if (!$idMicrofiche) {
            $this->fail();
        }

        $microfiche = new MsMicrofiche($idMicrofiche);
        if (!Validate::isLoadedObject($microfiche) || !$microfiche->active) {
            $this->fail();
        }

        $url = (string) $microfiche->image_full_url;
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host || !in_array($host, self::ALLOWED_HOSTS, true)) {
            $this->fail();
        }

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => self::FETCH_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121 Safari/537.36',
        ]);
        $body = curl_exec($curl);
        $code = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $contentType = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        curl_close($curl);

        if ($body === false || $body === '' || $code >= 400) {
            $this->fail();
        }

        $ext = 'png';
        if (preg_match('#\.([a-z0-9]+)$#i', parse_url($url, PHP_URL_PATH) ?: '', $m)) {
            $ext = strtolower($m[1]);
        }
        $filename = ($microfiche->nom_fr !== '' ? $microfiche->nom_fr : $microfiche->nom_constructeur) . '.' . $ext;
        $filename = preg_replace('/[^A-Za-z0-9_\-. ]/', '', $filename);

        header('Content-Type: ' . ($contentType !== '' ? $contentType : 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($body));
        header('Cache-Control: private, max-age=3600');
        echo $body;
        exit;
    }

    /** Échec propre : retour à la fiche plutôt qu'une page blanche. */
    private function fail()
    {
        Tools::redirect($this->context->link->getPageLink('index'));
        exit;
    }
}
