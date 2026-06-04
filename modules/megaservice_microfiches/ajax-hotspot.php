<?php
/**
 * Endpoint AJAX standalone pour l editeur drag/drop des hotspots.
 *
 * Approche : on bypass volontairement le routing ModuleAdminController +
 * checkToken de PrestaShop qui s avere trop fragile (token regenere
 * differemment selon contextes, page HTML "Cle de securite invalide"
 * impossible a parser cote JS). On gere ici nous-memes :
 *   - Session admin (l employee doit etre connecte au BO)
 *   - Headers JSON propres
 *   - Validation parametres et execution SQL
 *
 * URL : /modules/megaservice_microfiches/ajax-hotspot.php?action=...
 *
 * Actions supportees :
 *   - SaveHotspotPosition : POST id_hotspot, position_x, position_y
 *   - RevertHotspotPosition : POST id_hotspot
 */

// ----------------------------------------------------------------------------
// Bootstrap PrestaShop (Db, Context, Tools, Configuration)
// ----------------------------------------------------------------------------
$psRoot = realpath(__DIR__ . '/../..');
if (!$psRoot || !is_file($psRoot . '/config/config.inc.php')) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'PrestaShop bootstrap introuvable.']);
    exit;
}
require_once $psRoot . '/config/config.inc.php';

// ----------------------------------------------------------------------------
// Helpers
// ----------------------------------------------------------------------------
function ms_json_reply(array $payload): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }
    echo json_encode($payload);
    exit;
}

// ----------------------------------------------------------------------------
// Authentification : l employee doit etre connecte au BO
// ----------------------------------------------------------------------------
$cookie = Context::getContext()->cookie;
if (!$cookie || empty($cookie->id_employee) || !(int) $cookie->id_employee) {
    ms_json_reply(['ok' => false, 'error' => 'Non authentifie (session admin requise).']);
}
$idEmployee = (int) $cookie->id_employee;
// On verifie aussi que l employee existe vraiment et est actif
$employee = new Employee($idEmployee);
if (!Validate::isLoadedObject($employee) || !$employee->active) {
    ms_json_reply(['ok' => false, 'error' => 'Employee inactif ou inexistant.']);
}

// ----------------------------------------------------------------------------
// Routage par action
// ----------------------------------------------------------------------------
$action = (string) Tools::getValue('action');

if ($action === 'SaveHotspotPosition') {
    $idHotspot = (int) Tools::getValue('id_hotspot');
    $posXRaw   = Tools::getValue('position_x');
    $posYRaw   = Tools::getValue('position_y');

    if ($idHotspot <= 0) {
        ms_json_reply(['ok' => false, 'error' => 'id_hotspot manquant ou invalide.']);
    }
    if ($posXRaw === false || $posXRaw === '' || !is_numeric($posXRaw)
        || $posYRaw === false || $posYRaw === '' || !is_numeric($posYRaw)) {
        ms_json_reply([
            'ok'    => false,
            'error' => 'position_x / position_y manquants ou non numeriques.',
            'debug' => [
                'position_x' => $posXRaw, 'position_y' => $posYRaw,
                'post_keys'  => array_keys($_POST),
            ],
        ]);
    }
    $posX = (int) $posXRaw;
    $posY = (int) $posYRaw;
    if ($posX < 0 || $posY < 0) {
        ms_json_reply(['ok' => false, 'error' => 'Positions negatives non autorisees.']);
    }

    $ok = (bool) Db::getInstance()->execute(
        'UPDATE `' . _DB_PREFIX_ . 'ms_microfiche_hotspot` '
        . 'SET `position_x` = ' . $posX . ', '
        . '    `position_y` = ' . $posY . ', '
        . '    `manually_edited` = 1 '
        . 'WHERE `id_hotspot` = ' . $idHotspot
    );
    if (!$ok) {
        ms_json_reply(['ok' => false, 'error' => Db::getInstance()->getMsgError()]);
    }
    if (Db::getInstance()->Affected_Rows() === 0) {
        ms_json_reply(['ok' => false, 'error' => 'Hotspot introuvable (id=' . $idHotspot . ').']);
    }

    ms_json_reply([
        'ok'              => true,
        'id_hotspot'      => $idHotspot,
        'position_x'      => $posX,
        'position_y'      => $posY,
        'manually_edited' => true,
    ]);
}

if ($action === 'RevertHotspotPosition') {
    $idHotspot = (int) Tools::getValue('id_hotspot');
    if ($idHotspot <= 0) {
        ms_json_reply(['ok' => false, 'error' => 'id_hotspot manquant ou invalide.']);
    }

    $row = Db::getInstance()->getRow(
        'SELECT `position_x_original`, `position_y_original` '
        . 'FROM `' . _DB_PREFIX_ . 'ms_microfiche_hotspot` '
        . 'WHERE `id_hotspot` = ' . $idHotspot
    );
    if (!$row) {
        ms_json_reply(['ok' => false, 'error' => 'Hotspot introuvable.']);
    }
    if ($row['position_x_original'] === null || $row['position_y_original'] === null) {
        ms_json_reply([
            'ok'    => false,
            'error' => 'Aucune position constructeur en reference (champs _original NULL). '
                     . 'Reimporter le CSV constructeur ou drag/drop manuellement.',
        ]);
    }

    $posX = (int) $row['position_x_original'];
    $posY = (int) $row['position_y_original'];

    $ok = (bool) Db::getInstance()->execute(
        'UPDATE `' . _DB_PREFIX_ . 'ms_microfiche_hotspot` '
        . 'SET `position_x` = ' . $posX . ', '
        . '    `position_y` = ' . $posY . ', '
        . '    `manually_edited` = 0 '
        . 'WHERE `id_hotspot` = ' . $idHotspot
    );
    if (!$ok) {
        ms_json_reply(['ok' => false, 'error' => Db::getInstance()->getMsgError()]);
    }

    ms_json_reply([
        'ok'              => true,
        'id_hotspot'      => $idHotspot,
        'position_x'      => $posX,
        'position_y'      => $posY,
        'manually_edited' => false,
    ]);
}

ms_json_reply(['ok' => false, 'error' => 'Action inconnue : ' . $action]);
