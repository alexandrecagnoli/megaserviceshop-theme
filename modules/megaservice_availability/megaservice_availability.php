<?php
/**
 * Megaservice — Disponibilité produit.
 *
 * `show_price` (colonne persistante ps_product/ps_product_shop, PAS un calcul
 * dynamique) masque le prix ET le bouton d'achat quand elle vaut 0 — natif
 * PrestaShop, indépendamment de `available_for_order`, `out_of_stock` ou du
 * stock. Le module d'import du catalogue (ba_importer, alias BO « Advance
 * Importing Pro ») l'écrit à 0 sur la quasi-totalité des produits importés,
 * par un mécanisme non identifiable (pas dans son mapping de champs, pas dans
 * ses réglages généraux — code source non accessible, module tiers).
 *
 * Ce module maintient `show_price = available_for_order` : un produit
 * commandable reste visible avec son prix, un produit non commandable peut
 * légitimement le rester masqué. Deux mécanismes complémentaires, pas un pari
 * sur un seul :
 *   - un hook temps réel, qui corrige à chaque écriture produit tant que
 *     l'import passe par l'ORM PrestaShop (Product::add()/update()) ;
 *   - une resynchronisation en masse (BO + CLI), qui sert de filet si ce
 *     n'est pas le cas — un import qui écrit en SQL direct ne déclenche aucun
 *     hook ObjectModel — et qui rattrape le catalogue déjà importé.
 *
 * Contexte complet, mesures et diagnostic : docs/SPEC_disponibilite_stock.md
 * §8-11.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/MsAvailabilitySync.php';

class Megaservice_availability extends Module
{
    public function __construct()
    {
        $this->name          = 'megaservice_availability';
        $this->tab           = 'administration';
        $this->version       = '1.0.0';
        $this->author        = 'Megaservice';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap     = true;

        parent::__construct();

        $this->displayName = 'Megaservice — Disponibilité produit';
        $this->description = 'Synchronise show_price sur available_for_order — évite que l\'import masque prix et bouton d\'achat sur des produits pourtant commandables.';
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('actionObjectProductAddAfter')
            && $this->registerHook('actionObjectProductUpdateAfter');
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Hook temps réel
    // ─────────────────────────────────────────────────────────────────────────

    public function hookActionObjectProductAddAfter($params)
    {
        $this->syncFromHookParams($params);
    }

    public function hookActionObjectProductUpdateAfter($params)
    {
        $this->syncFromHookParams($params);
    }

    private function syncFromHookParams($params)
    {
        if (empty($params['object']) || !($params['object'] instanceof Product)) {
            return;
        }
        MsAvailabilitySync::syncProduct((int) $params['object']->id);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Back-office : resynchronisation manuelle + self-heal des hooks
    // ─────────────────────────────────────────────────────────────────────────

    public function getContent()
    {
        $out = '';

        // Self-heal : cf. même pattern dans megaservice_mountability. Un
        // install() historique aurait pu échouer avant l'enregistrement des
        // hooks ; un passage sur cet écran les raccroche sans réinstaller.
        $healed = [];
        foreach (['actionObjectProductAddAfter', 'actionObjectProductUpdateAfter'] as $h) {
            if (!$this->isRegisteredInHook($h)) {
                $this->registerHook($h);
                $healed[] = $h;
            }
        }
        if ($healed) {
            $out .= $this->displayConfirmation(
                $this->l('Hooks ré-enregistrés : ') . implode(', ', $healed)
            );
        }

        if (Tools::isSubmit('submitMsAvailabilitySyncAll')) {
            $out .= $this->handleSyncAll();
        }

        return $out . $this->renderForm();
    }

    private function handleSyncAll()
    {
        $r = MsAvailabilitySync::syncAll();

        return $this->displayConfirmation(sprintf(
            $this->l('Resynchronisation terminée — %d produits étaient incohérents ; %d lignes ps_product et %d lignes ps_product_shop corrigées.'),
            $r['incoherents_avant'], $r['ps_product_corriges'], $r['ps_product_shop_corriges']
        ));
    }

    private function renderForm()
    {
        $db = Db::getInstance();
        $incoherents = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product` WHERE `show_price` != `available_for_order`'
        );

        $status = $incoherents > 0
            ? '<p class="alert alert-warning">' . sprintf(
                $this->l('%d produits ont actuellement show_price ≠ available_for_order.'),
                $incoherents
            ) . '</p>'
            : '<p class="alert alert-success">' . $this->l('Tout le catalogue est cohérent (show_price = available_for_order).') . '</p>';

        return '<div class="panel">
            <h3><i class="icon-refresh"></i> ' . $this->l('Disponibilité produit') . '</h3>
            ' . $status . '
            <p>' . $this->l('Le hook temps réel corrige chaque produit à l\'écriture. Ce bouton rattrape le catalogue déjà importé, ou sert de filet si un import n\'a pas déclenché le hook (écriture SQL directe hors ORM PrestaShop).') . '</p>
            <form method="post">
                <button type="submit" name="submitMsAvailabilitySyncAll" class="btn btn-default">
                    <i class="process-icon-refresh"></i> ' . $this->l('Resynchroniser tout le catalogue') . '
                </button>
            </form>
        </div>';
    }
}
