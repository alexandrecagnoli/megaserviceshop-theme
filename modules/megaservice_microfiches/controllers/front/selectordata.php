<?php
/**
 * Endpoint JSON du sélecteur de moto (modale header).
 * Cascade : Marque → Année → Pratique → Modèle. Chaque niveau affine le suivant.
 * Alimente megaservice/assets/js/model-selector.js en AJAX.
 *
 * Paramètres GET :
 *   marque                → renvoie les années disponibles
 *   marque + annee        → renvoie les pratiques (ms_moto.type)
 *   marque + annee + type → renvoie les modèles [{value:url, label}]
 *
 * Réponse : { "items": [ {value, label}, ... ] }
 * Le `value` d'un modèle est directement l'URL de sa page hub (le JS y navigue).
 */
class Megaservice_microfichesSelectordataModuleFrontController extends ModuleFrontController
{
    /** Code du formulaire → valeur ENUM de ms_moto.marque. */
    const MARQUES = [
        'ktm'       => 'KTM',
        'husqvarna' => 'HQV',
        'hqv'       => 'HQV',
        'gasgas'    => 'GASGAS',
    ];

    public function initContent()
    {
        header('Content-Type: application/json; charset=utf-8');
        die(json_encode(['items' => $this->resolve()]));
    }

    private function resolve()
    {
        $marque = $this->marque();
        if ($marque === null) {
            return [];
        }
        $annee = (int) Tools::getValue('annee');
        $type  = trim((string) Tools::getValue('type'));

        if (!$annee) {
            return $this->years($marque);
        }
        if ($type === '') {
            return $this->practices($marque, $annee);
        }
        return $this->models($marque, $annee, $type);
    }

    /** Normalise le code marque du formulaire vers l'ENUM ms_moto. */
    private function marque()
    {
        $raw = Tools::strtolower(trim((string) Tools::getValue('marque')));
        if ($raw === '') {
            return null;
        }
        if (isset(self::MARQUES[$raw])) {
            return self::MARQUES[$raw];
        }
        $up = Tools::strtoupper($raw);

        return in_array($up, ['KTM', 'HQV', 'GASGAS'], true) ? $up : null;
    }

    private function years($marque)
    {
        $rows = Db::getInstance()->executeS(
            'SELECT DISTINCT `annee` FROM `' . _DB_PREFIX_ . 'ms_moto`
             WHERE `marque` = "' . pSQL($marque) . '" AND `active` = 1 AND `annee` > 0
             ORDER BY `annee` DESC'
        ) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $y = (int) $r['annee'];
            $out[] = ['value' => $y, 'label' => (string) $y];
        }

        return $out;
    }

    private function practices($marque, $annee)
    {
        $rows = Db::getInstance()->executeS(
            'SELECT DISTINCT `type` FROM `' . _DB_PREFIX_ . 'ms_moto`
             WHERE `marque` = "' . pSQL($marque) . '" AND `annee` = ' . (int) $annee . '
               AND `active` = 1 AND `type` <> ""
             ORDER BY `type` ASC'
        ) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $out[] = ['value' => (string) $r['type'], 'label' => (string) $r['type']];
        }

        return $out;
    }

    private function models($marque, $annee, $type)
    {
        // Un modèle = un core_name ; id_moto représentatif = le plus petit.
        $rows = Db::getInstance()->executeS(
            'SELECT MIN(`id_moto`) AS id_moto, `core_name`
             FROM `' . _DB_PREFIX_ . 'ms_moto`
             WHERE `marque` = "' . pSQL($marque) . '" AND `annee` = ' . (int) $annee . '
               AND `type` = "' . pSQL($type) . '" AND `active` = 1
             GROUP BY `core_name`
             ORDER BY `core_name` ASC'
        ) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $id   = (int) $r['id_moto'];
            $name = (string) $r['core_name'];
            $out[] = [
                'value' => $this->context->link->getModuleLink('megaservice_microfiches', 'moto', [
                    'id_moto' => $id,
                    'slug'    => MsMoto::buildSlug($marque, $name, $annee, $type),
                ]),
                'label' => $name,
            ];
        }

        return $out;
    }
}
